<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;

class SteganographyController
{
    /**
     * Detect hidden data in images/files using steganography analysis
     */
    public function detect(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        if (!isset($_FILES['file'])) {
            Response::error('No file uploaded', 400);
            return;
        }

        $file = $_FILES['file'];

        try {
            $tmpFile = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileName = $file['name'];

            // Get file hash
            $fileHash = hash_file('sha256', $tmpFile);

            // Determine file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpFile);
            finfo_close($finfo);

            $detectedType = 'Unknown';
            $hiddenDataDetected = false;
            $suspicionScore = 0;
            $findings = [];

            // Analyze based on file type
            if (str_starts_with($mimeType, 'image/')) {
                $detectedType = 'Image';
                $this->analyzeImage($tmpFile, $mimeType, $hiddenDataDetected, $suspicionScore, $findings);
            } elseif (str_starts_with($mimeType, 'audio/')) {
                $detectedType = 'Audio';
                $this->analyzeAudio($tmpFile, $mimeType, $hiddenDataDetected, $suspicionScore, $findings);
            } else {
                $detectedType = 'Binary/Other';
                $this->analyzeGeneric($tmpFile, $hiddenDataDetected, $suspicionScore, $findings);
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('Steganography detection completed', [
                'file_name' => $fileName,
                'file_type' => $detectedType,
                'file_size' => $fileSize,
                'file_hash' => $fileHash,
                'hidden_data_detected' => $hiddenDataDetected,
                'suspicion_score' => $suspicionScore,
                'analysis_type' => 'Statistical & LSB Analysis',
                'findings' => $findings
            ]);

        } catch (\Exception $e) {
            Response::error('Steganography detection failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Analyze image for steganography
     */
    private function analyzeImage(string $file, string $mimeType, bool &$detected, int &$score, array &$findings): void
    {
        $imageData = file_get_contents($file);

        // Check for common steganography tools signatures
        $this->checkCommonSignatures($imageData, $detected, $score, $findings);

        // LSB (Least Significant Bit) analysis for PNG and BMP
        if ($mimeType === 'image/png' || $mimeType === 'image/bmp' || $mimeType === 'image/x-ms-bmp') {
            $this->lsbAnalysis($file, $mimeType, $detected, $score, $findings);
        }

        // Statistical analysis
        $this->statisticalAnalysis($imageData, $detected, $score, $findings);

        // Check for unusual file size
        $expectedSize = $this->estimateImageSize($file, $mimeType);
        $actualSize = filesize($file);

        if ($expectedSize > 0 && $actualSize > $expectedSize * 1.5) {
            $score += 20;
            $findings[] = [
                'type' => 'Size Anomaly',
                'description' => 'File size is unusually large for image dimensions'
            ];
        }
    }

    /**
     * Analyze audio for steganography
     */
    private function analyzeAudio(string $file, string $mimeType, bool &$detected, int &$score, array &$findings): void
    {
        $audioData = file_get_contents($file);

        // Check for common steganography tools signatures
        $this->checkCommonSignatures($audioData, $detected, $score, $findings);

        // Statistical analysis
        $this->statisticalAnalysis($audioData, $detected, $score, $findings);
    }

    /**
     * Generic analysis for other file types
     */
    private function analyzeGeneric(string $file, bool &$detected, int &$score, array &$findings): void
    {
        $data = file_get_contents($file);

        // Check for common steganography tools signatures
        $this->checkCommonSignatures($data, $detected, $score, $findings);

        // Statistical analysis
        $this->statisticalAnalysis($data, $detected, $score, $findings);
    }

    /**
     * Check for signatures of common steganography tools
     */
    private function checkCommonSignatures(string $data, bool &$detected, int &$score, array &$findings): void
    {
        $signatures = [
            'StegSecret' => 'StegSecret',
            'S-Tools' => 'S-Tools',
            'Hide4PGP' => 'Hide4PGP',
            'Steghide' => 'steghide',
            'OutGuess' => 'OutGuess',
            'JSteg' => 'JSTEG',
            'F5' => 'F5 Steganography'
        ];

        foreach ($signatures as $tool => $signature) {
            if (str_contains($data, $signature)) {
                $detected = true;
                $score += 50;
                $findings[] = [
                    'type' => 'Tool Signature',
                    'description' => "Signature of {$tool} steganography tool detected"
                ];
            }
        }
    }

    /**
     * LSB analysis for images
     */
    private function lsbAnalysis(string $file, string $mimeType, bool &$detected, int &$score, array &$findings): void
    {
        try {
            // Load image
            $image = null;
            if ($mimeType === 'image/png') {
                $image = @imagecreatefrompng($file);
            } elseif ($mimeType === 'image/bmp' || $mimeType === 'image/x-ms-bmp') {
                $image = @imagecreatefrombmp($file);
            }

            if (!$image) {
                return;
            }

            $width = imagesx($image);
            $height = imagesy($image);

            // Sample pixels and check LSB patterns
            $lsbBitCount = [0, 0]; // [0 count, 1 count]
            $sampleSize = min(1000, $width * $height);

            for ($i = 0; $i < $sampleSize; $i++) {
                $x = rand(0, $width - 1);
                $y = rand(0, $height - 1);

                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $lsbBitCount[$r & 1]++;
                $lsbBitCount[$g & 1]++;
                $lsbBitCount[$b & 1]++;
            }

            imagedestroy($image);

            // Calculate LSB ratio (should be close to 50/50 for natural images)
            $totalBits = $lsbBitCount[0] + $lsbBitCount[1];
            $ratio = $lsbBitCount[1] / $totalBits;

            // If ratio is significantly different from 0.5, might indicate steganography
            if (abs($ratio - 0.5) < 0.1) {
                // Suspiciously close to 50/50 (might be random data)
                $score += 15;
                $findings[] = [
                    'type' => 'LSB Analysis',
                    'description' => 'LSB bit distribution suggests possible embedded data (ratio: ' . round($ratio, 3) . ')'
                ];
            }

        } catch (\Exception $e) {
            // Ignore image processing errors
        }
    }

    /**
     * Statistical analysis of file data
     */
    private function statisticalAnalysis(string $data, bool &$detected, int &$score, array &$findings): void
    {
        $length = strlen($data);
        if ($length < 100) {
            return;
        }

        // Calculate entropy
        $entropy = 0;
        $counts = array_count_values(str_split($data));

        foreach ($counts as $count) {
            $p = $count / $length;
            $entropy -= $p * log($p, 2);
        }

        // High entropy might indicate compressed or encrypted data
        if ($entropy > 7.5) {
            $score += 25;
            $findings[] = [
                'type' => 'High Entropy',
                'description' => 'High data entropy detected (' . round($entropy, 2) . '/8.0) - may indicate compressed or encrypted hidden data'
            ];
        }

        // Check for unusual byte patterns
        $nullBytes = substr_count($data, "\x00");
        $nullRatio = $nullBytes / $length;

        if ($nullRatio > 0.3) {
            $score += 10;
            $findings[] = [
                'type' => 'Null Byte Pattern',
                'description' => 'Unusually high number of null bytes (' . round($nullRatio * 100, 1) . '%)'
            ];
        }
    }

    /**
     * Estimate expected image file size
     */
    private function estimateImageSize(string $file, string $mimeType): int
    {
        try {
            $image = null;
            if ($mimeType === 'image/png') {
                $image = @imagecreatefrompng($file);
            } elseif ($mimeType === 'image/jpeg') {
                $image = @imagecreatefromjpeg($file);
            } elseif ($mimeType === 'image/bmp' || $mimeType === 'image/x-ms-bmp') {
                $image = @imagecreatefrombmp($file);
            }

            if (!$image) {
                return 0;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            imagedestroy($image);

            // Rough estimate: width * height * 3 bytes (RGB) with compression
            $rawSize = $width * $height * 3;

            if ($mimeType === 'image/png') {
                return (int)($rawSize * 0.3); // PNG typically 30% compression
            } elseif ($mimeType === 'image/jpeg') {
                return (int)($rawSize * 0.1); // JPEG typically 10% compression
            } else {
                return $rawSize;
            }

        } catch (\Exception $e) {
            return 0;
        }
    }
}
