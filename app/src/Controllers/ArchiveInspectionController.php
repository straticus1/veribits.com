<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Database;

class ArchiveInspectionController {
    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB
    private const MAX_LIST_FILES = 1000;
    private const UPLOAD_DIR = '/tmp/veribits-archives';
    private const SUSPICIOUS_PATTERNS = [
        '../',
        '..\\',
        '/etc/',
        '/var/',
        '/usr/',
        'C:\\',
        '.exe',
        '.dll',
        '.bat',
        '.cmd',
        '.ps1',
        '.sh',
        '.bash'
    ];

    public function inspect(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;
        $apiKeyId = $claims['key_id'] ?? null;

        if (!RateLimit::checkUserQuota($userId, 'monthly')) {
            Response::error('Monthly quota exceeded', 429);
            return;
        }

        // Handle file upload
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('File upload required', 400);
            return;
        }

        $file = $_FILES['file'];
        $fileSize = $file['size'];
        $fileName = $file['name'];

        if ($fileSize > self::MAX_FILE_SIZE) {
            Response::error('File too large (max 100MB)', 413);
            return;
        }

        if ($fileSize === 0) {
            Response::error('Empty file', 400);
            return;
        }

        try {
            // Calculate file hash
            $fileHash = hash_file('sha256', $file['tmp_name']);

            // Detect archive type
            $archiveType = $this->detectArchiveType($fileName, $file['tmp_name']);
            if (!$archiveType) {
                Response::error('Unsupported archive type. Supported: zip, tar, tar.gz, tar.bz2, tar.xz', 400);
                return;
            }

            // Create upload directory if it doesn't exist
            if (!is_dir(self::UPLOAD_DIR)) {
                mkdir(self::UPLOAD_DIR, 0755, true);
            }

            // Move uploaded file to temp location
            $tempPath = self::UPLOAD_DIR . '/' . $fileHash;
            if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
                throw new \Exception('Failed to save uploaded file');
            }

            // Inspect archive contents
            $inspection = $this->inspectArchive($tempPath, $archiveType);

            // Clean up temp file
            @unlink($tempPath);

            // Analyze for suspicious content
            $suspicious = $this->detectSuspiciousContent($inspection['contents']);

            // Determine integrity status
            $integrityStatus = 'ok';
            if (!empty($suspicious)) {
                $integrityStatus = 'suspicious';
            } elseif (!$inspection['success']) {
                $integrityStatus = 'corrupted';
            }

            // Calculate compression ratio
            $compressionRatio = $fileSize > 0 ? round($inspection['total_size'] / $fileSize, 2) : 0;

            // Check for potential zip bomb
            if ($compressionRatio > 100) {
                $suspicious[] = 'Extremely high compression ratio (potential zip bomb)';
                $integrityStatus = 'suspicious';
            }

            // Generate badge ID
            $badgeId = 'arch_' . substr($fileHash, 0, 16);

            // Store inspection result in database
            $db = Database::getConnection();
            $stmt = $db->prepare('
                INSERT INTO archive_inspections
                (user_id, api_key_id, file_hash, archive_type, total_files,
                 total_size_bytes, compression_ratio, contents, suspicious_flags,
                 integrity_status, badge_id)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
                RETURNING id
            ');

            $contentsJson = json_encode($inspection['contents']);
            $suspiciousJson = json_encode($suspicious);

            $stmt->execute([
                $userId,
                $apiKeyId,
                $fileHash,
                $archiveType,
                $inspection['file_count'],
                $inspection['total_size'],
                $compressionRatio,
                $contentsJson,
                $suspiciousJson,
                $integrityStatus,
                $badgeId
            ]);

            RateLimit::incrementUserQuota($userId, 'monthly');

            Logger::info('Archive inspection completed', [
                'user_id' => $userId,
                'file_hash' => $fileHash,
                'archive_type' => $archiveType,
                'file_count' => $inspection['file_count'],
                'integrity' => $integrityStatus
            ]);

            Response::success([
                'type' => 'archive_inspection',
                'file_hash' => $fileHash,
                'archive_type' => $archiveType,
                'total_files' => $inspection['file_count'],
                'total_size_bytes' => $inspection['total_size'],
                'compressed_size_bytes' => $fileSize,
                'compression_ratio' => $compressionRatio,
                'contents' => $inspection['contents'],
                'suspicious_flags' => $suspicious,
                'integrity_status' => $integrityStatus,
                'is_safe' => $integrityStatus === 'ok',
                'badge_id' => $badgeId,
                'badge_url' => "/api/v1/badge/$badgeId",
                'inspected_at' => date('c')
            ]);

        } catch (\Exception $e) {
            Logger::error('Archive inspection failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            // Clean up temp file on error
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }

            Response::error('Inspection failed: ' . $e->getMessage(), 500);
        }
    }

    private function detectArchiveType(string $fileName, string $filePath): ?string {
        $fileName = strtolower($fileName);

        // Check by filename extension
        if (preg_match('/\.(tar\.gz|tgz)$/', $fileName)) {
            return 'tar.gz';
        } elseif (preg_match('/\.(tar\.bz2|tbz|tbz2)$/', $fileName)) {
            return 'tar.bz2';
        } elseif (preg_match('/\.(tar\.xz|txz)$/', $fileName)) {
            return 'tar.xz';
        } elseif (preg_match('/\.tar$/', $fileName)) {
            return 'tar';
        } elseif (preg_match('/\.zip$/', $fileName)) {
            return 'zip';
        } elseif (preg_match('/\.gz$/', $fileName)) {
            return 'gz';
        } elseif (preg_match('/\.bz2$/', $fileName)) {
            return 'bz2';
        }

        // Fallback: check magic bytes
        $fh = fopen($filePath, 'rb');
        if (!$fh) {
            return null;
        }

        $magic = fread($fh, 6);
        fclose($fh);

        if (substr($magic, 0, 4) === "PK\x03\x04") {
            return 'zip';
        } elseif (substr($magic, 0, 2) === "\x1f\x8b") {
            return 'tar.gz';
        } elseif (substr($magic, 0, 3) === "BZh") {
            return 'tar.bz2';
        } elseif (substr($magic, 0, 6) === "\xfd7zXZ\x00") {
            return 'tar.xz';
        }

        return null;
    }

    private function inspectArchive(string $filePath, string $type): array {
        $result = [
            'success' => false,
            'file_count' => 0,
            'total_size' => 0,
            'contents' => []
        ];

        try {
            if ($type === 'zip') {
                $result = $this->inspectZip($filePath);
            } elseif (in_array($type, ['tar', 'tar.gz', 'tar.bz2', 'tar.xz'])) {
                $result = $this->inspectTar($filePath, $type);
            } elseif (in_array($type, ['gz', 'bz2'])) {
                // Single compressed files
                $result = [
                    'success' => true,
                    'file_count' => 1,
                    'total_size' => filesize($filePath),
                    'contents' => [
                        [
                            'path' => 'compressed_file',
                            'size' => filesize($filePath),
                            'type' => 'file'
                        ]
                    ]
                ];
            }
        } catch (\Exception $e) {
            Logger::warning('Archive inspection error', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    private function inspectZip(string $filePath): array {
        $zip = new \ZipArchive();
        $result = [
            'success' => false,
            'file_count' => 0,
            'total_size' => 0,
            'contents' => []
        ];

        if ($zip->open($filePath) !== true) {
            return $result;
        }

        $result['success'] = true;
        $count = min($zip->numFiles, self::MAX_LIST_FILES);

        for ($i = 0; $i < $count; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $result['file_count']++;
            $result['total_size'] += $stat['size'];

            $result['contents'][] = [
                'path' => $stat['name'],
                'size' => $stat['size'],
                'compressed_size' => $stat['comp_size'],
                'modified' => date('c', $stat['mtime']),
                'crc' => sprintf('%08x', $stat['crc']),
                'type' => substr($stat['name'], -1) === '/' ? 'directory' : 'file'
            ];
        }

        $zip->close();
        return $result;
    }

    private function inspectTar(string $filePath, string $type): array {
        $result = [
            'success' => false,
            'file_count' => 0,
            'total_size' => 0,
            'contents' => []
        ];

        // Determine tar flags
        $flag = 't';
        if ($type === 'tar.gz' || $type === 'tgz') {
            $flag = 'tz';
        } elseif ($type === 'tar.bz2' || $type === 'tbz') {
            $flag = 'tj';
        } elseif ($type === 'tar.xz') {
            $flag = 'tJ';
        }

        $output = [];
        $returnCode = 0;
        $escapedPath = escapeshellarg($filePath);
        @exec("tar -{$flag}vf $escapedPath 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            return $result;
        }

        $result['success'] = true;
        $lineCount = 0;

        foreach ($output as $line) {
            if ($lineCount++ >= self::MAX_LIST_FILES) {
                break;
            }

            // Parse tar verbose output (format varies by system)
            // Example: -rw-r--r-- user/group size date time filename
            if (preg_match('/^([drwx-]{10})\s+\S+\s+(\d+)\s+\S+\s+\S+\s+(.+)$/', $line, $matches)) {
                $perms = $matches[1];
                $size = (int)$matches[2];
                $path = $matches[3];

                $result['file_count']++;
                $result['total_size'] += $size;

                $result['contents'][] = [
                    'path' => $path,
                    'size' => $size,
                    'permissions' => $perms,
                    'type' => substr($perms, 0, 1) === 'd' ? 'directory' : 'file'
                ];
            }
        }

        return $result;
    }

    private function detectSuspiciousContent(array $contents): array {
        $flags = [];

        foreach ($contents as $entry) {
            $path = $entry['path'];

            // Check for path traversal
            foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
                if (stripos($path, $pattern) !== false) {
                    $flags[] = "Suspicious path pattern detected: {$pattern} in {$path}";
                    break;
                }
            }

            // Check for absolute paths
            if (substr($path, 0, 1) === '/' || preg_match('/^[a-z]:/i', $path)) {
                $flags[] = "Absolute path detected: {$path}";
            }

            // Check for hidden files (might be suspicious in some contexts)
            $basename = basename($path);
            if (substr($basename, 0, 1) === '.' && strlen($basename) > 1) {
                // This is informational, not necessarily suspicious
                // Could add to a separate 'notes' field
            }
        }

        return array_unique($flags);
    }
}
