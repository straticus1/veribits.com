<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Database;

class FileMagicController {
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const BYTES_TO_READ = 512; // Read first 512 bytes for magic number detection

    // Common file magic numbers
    private const MAGIC_NUMBERS = [
        // Images
        ['bytes' => 'FFD8FF', 'ext' => 'jpg', 'mime' => 'image/jpeg', 'desc' => 'JPEG Image'],
        ['bytes' => '89504E47', 'ext' => 'png', 'mime' => 'image/png', 'desc' => 'PNG Image'],
        ['bytes' => '47494638', 'ext' => 'gif', 'mime' => 'image/gif', 'desc' => 'GIF Image'],
        ['bytes' => '424D', 'ext' => 'bmp', 'mime' => 'image/bmp', 'desc' => 'Bitmap Image'],
        ['bytes' => '49492A00', 'ext' => 'tif', 'mime' => 'image/tiff', 'desc' => 'TIFF Image (Little Endian)'],
        ['bytes' => '4D4D002A', 'ext' => 'tif', 'mime' => 'image/tiff', 'desc' => 'TIFF Image (Big Endian)'],
        ['bytes' => '52494646', 'ext' => 'webp', 'mime' => 'image/webp', 'desc' => 'WebP Image', 'offset' => 8, 'secondary' => '57454250'],

        // Documents
        ['bytes' => '25504446', 'ext' => 'pdf', 'mime' => 'application/pdf', 'desc' => 'PDF Document'],
        ['bytes' => '504B0304', 'ext' => 'zip', 'mime' => 'application/zip', 'desc' => 'ZIP Archive'],
        ['bytes' => 'D0CF11E0A1B11AE1', 'ext' => 'doc', 'mime' => 'application/msword', 'desc' => 'Microsoft Office Document'],
        ['bytes' => '504B030414000600', 'ext' => 'docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'desc' => 'Microsoft Word (DOCX)'],

        // Archives
        ['bytes' => '1F8B', 'ext' => 'gz', 'mime' => 'application/gzip', 'desc' => 'GZIP Archive'],
        ['bytes' => '526172211A07', 'ext' => 'rar', 'mime' => 'application/x-rar-compressed', 'desc' => 'RAR Archive'],
        ['bytes' => '377ABCAF271C', 'ext' => '7z', 'mime' => 'application/x-7z-compressed', 'desc' => '7-Zip Archive'],
        ['bytes' => '1F9D', 'ext' => 'z', 'mime' => 'application/x-compress', 'desc' => 'Unix Compress'],
        ['bytes' => '425A68', 'ext' => 'bz2', 'mime' => 'application/x-bzip2', 'desc' => 'BZIP2 Archive'],

        // Executables
        ['bytes' => '4D5A', 'ext' => 'exe', 'mime' => 'application/x-msdownload', 'desc' => 'Windows Executable'],
        ['bytes' => '7F454C46', 'ext' => 'elf', 'mime' => 'application/x-elf', 'desc' => 'Linux Executable (ELF)'],
        ['bytes' => 'CAFEBABE', 'ext' => 'class', 'mime' => 'application/java-vm', 'desc' => 'Java Class File'],

        // Video
        ['bytes' => '000000', 'ext' => 'mp4', 'mime' => 'video/mp4', 'desc' => 'MP4 Video', 'offset' => 4, 'secondary' => '66747970'],
        ['bytes' => '1A45DFA3', 'ext' => 'mkv', 'mime' => 'video/x-matroska', 'desc' => 'Matroska Video'],
        ['bytes' => '000001BA', 'ext' => 'mpg', 'mime' => 'video/mpeg', 'desc' => 'MPEG Video'],
        ['bytes' => '464C56', 'ext' => 'flv', 'mime' => 'video/x-flv', 'desc' => 'Flash Video'],

        // Audio
        ['bytes' => '494433', 'ext' => 'mp3', 'mime' => 'audio/mpeg', 'desc' => 'MP3 Audio'],
        ['bytes' => 'FFFB', 'ext' => 'mp3', 'mime' => 'audio/mpeg', 'desc' => 'MP3 Audio (No ID3)'],
        ['bytes' => '664C6143', 'ext' => 'flac', 'mime' => 'audio/flac', 'desc' => 'FLAC Audio'],
        ['bytes' => '4F676753', 'ext' => 'ogg', 'mime' => 'audio/ogg', 'desc' => 'OGG Audio'],

        // Database
        ['bytes' => '53514C69746520666F726D6174203300', 'ext' => 'sqlite', 'mime' => 'application/x-sqlite3', 'desc' => 'SQLite Database'],

        // Other
        ['bytes' => '3C3F786D6C', 'ext' => 'xml', 'mime' => 'application/xml', 'desc' => 'XML Document'],
        ['bytes' => '3C21444F43545950452068746D6C', 'ext' => 'html', 'mime' => 'text/html', 'desc' => 'HTML Document'],
        ['bytes' => '7B5C727466', 'ext' => 'rtf', 'mime' => 'application/rtf', 'desc' => 'Rich Text Format'],
    ];

    public function analyze(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!RateLimit::checkUserQuota($userId, 'monthly')) {
            Response::error('Monthly quota exceeded', 429);
            return;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('File upload required', 400);
            return;
        }

        $file = $_FILES['file'];
        $fileSize = $file['size'];

        if ($fileSize > self::MAX_FILE_SIZE) {
            Response::error('File too large (max 10MB)', 413);
            return;
        }

        if ($fileSize === 0) {
            Response::error('Empty file', 400);
            return;
        }

        try {
            $analysis = $this->analyzeFile($file['tmp_name'], $file['name']);

            // Store in database
            $badgeId = 'magic_' . substr(md5($file['name'] . time()), 0, 16);

            Database::getConnection()->prepare('
                INSERT INTO file_magic_checks
                (user_id, filename, file_size, magic_number, detected_type, detected_extension,
                 detected_mime, file_hash, badge_id, created_at)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, NOW())
            ')->execute([
                $userId,
                $file['name'],
                $fileSize,
                $analysis['magic_number'],
                $analysis['detected_type'],
                $analysis['detected_extension'],
                $analysis['detected_mime'],
                $analysis['file_hash'],
                $badgeId
            ]);

            RateLimit::incrementUserQuota($userId, 'monthly');

            Logger::info('File magic analysis completed', [
                'user_id' => $userId,
                'filename' => $file['name'],
                'detected_type' => $analysis['detected_type']
            ]);

            Response::success([
                'type' => 'file_magic_analysis',
                'filename' => $file['name'],
                'file_size' => $fileSize,
                'file_hash' => $analysis['file_hash'],
                'magic_number' => $analysis['magic_number'],
                'magic_number_hex' => $analysis['magic_number_hex'],
                'detected_type' => $analysis['detected_type'],
                'detected_extension' => $analysis['detected_extension'],
                'detected_mime' => $analysis['detected_mime'],
                'match_confidence' => $analysis['match_confidence'],
                'additional_info' => $analysis['additional_info'],
                'badge_id' => $badgeId,
                'badge_url' => "/api/v1/badge/$badgeId",
                'analyzed_at' => date('c')
            ]);

        } catch (\Exception $e) {
            Logger::error('File magic analysis failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Analysis failed: ' . $e->getMessage(), 500);
        }
    }

    private function analyzeFile(string $filePath, string $filename): array {
        $fileSize = filesize($filePath);
        $fileHash = hash_file('sha256', $filePath);

        // Read first bytes for magic number detection
        $handle = fopen($filePath, 'rb');
        $bytes = fread($handle, self::BYTES_TO_READ);
        fclose($handle);

        $hexBytes = bin2hex($bytes);

        // Detect file type based on magic number
        $detection = $this->detectFileType($hexBytes);

        // Get additional file information
        $additionalInfo = [
            'first_16_bytes' => substr($hexBytes, 0, 32),
            'first_32_bytes' => substr($hexBytes, 0, 64),
            'printable_header' => $this->getPrintableHeader($bytes),
            'extension_from_name' => pathinfo($filename, PATHINFO_EXTENSION),
            'matches_extension' => false
        ];

        if (!empty($additionalInfo['extension_from_name'])) {
            $additionalInfo['matches_extension'] =
                strtolower($additionalInfo['extension_from_name']) === strtolower($detection['extension']);
        }

        return [
            'file_hash' => $fileHash,
            'magic_number' => $detection['magic_bytes'],
            'magic_number_hex' => $detection['magic_hex'],
            'detected_type' => $detection['description'],
            'detected_extension' => $detection['extension'],
            'detected_mime' => $detection['mime'],
            'match_confidence' => $detection['confidence'],
            'additional_info' => $additionalInfo
        ];
    }

    private function detectFileType(string $hexBytes): array {
        foreach (self::MAGIC_NUMBERS as $magic) {
            $offset = $magic['offset'] ?? 0;
            $magicHex = strtoupper($magic['bytes']);
            $checkHex = strtoupper(substr($hexBytes, $offset * 2, strlen($magicHex)));

            if ($checkHex === $magicHex) {
                // Check secondary magic number if specified
                if (isset($magic['secondary'])) {
                    $secondaryOffset = $magic['offset'] ?? 0;
                    $secondaryHex = strtoupper($magic['secondary']);
                    $checkSecondary = strtoupper(substr($hexBytes, $secondaryOffset * 2, strlen($secondaryHex)));

                    if ($checkSecondary !== $secondaryHex) {
                        continue;
                    }
                }

                return [
                    'magic_bytes' => hex2bin($magicHex),
                    'magic_hex' => $magicHex,
                    'extension' => $magic['ext'],
                    'mime' => $magic['mime'],
                    'description' => $magic['desc'],
                    'confidence' => 'high'
                ];
            }
        }

        // No match found
        $unknownHex = substr($hexBytes, 0, 8);
        $unknownBytes = @hex2bin($unknownHex);
        if ($unknownBytes === false) {
            $unknownBytes = substr($hexBytes, 0, 4);
        }

        return [
            'magic_bytes' => $unknownBytes,
            'magic_hex' => $unknownHex,
            'extension' => 'unknown',
            'mime' => 'application/octet-stream',
            'description' => 'Unknown File Type',
            'confidence' => 'unknown'
        ];
    }

    private function getPrintableHeader(string $bytes): string {
        $printable = '';
        $length = min(strlen($bytes), 64);

        for ($i = 0; $i < $length; $i++) {
            $char = $bytes[$i];
            if (ctype_print($char)) {
                $printable .= $char;
            } else {
                $printable .= '.';
            }
        }

        return $printable;
    }
}
