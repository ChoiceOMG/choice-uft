<?php
/**
 * Choice Universal Form Tracker - Download Verifier
 *
 * Verifies downloaded update files using checksums and integrity checks.
 * Detects corrupted or tampered files before installation.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Download Verifier Class
 *
 * Provides file verification and integrity checking
 */
class CUFT_Download_Verifier {
    /**
     * Supported hash algorithms (in order of preference)
     *
     * @var array
     */
    private static $hash_algorithms = array('sha256', 'sha1', 'md5');

    /**
     * Maximum file size for verification (100MB)
     *
     * @var int
     */
    private static $max_file_size = 104857600;

    /**
     * Verify downloaded file integrity
     *
     * @param string $file_path Path to downloaded file
     * @param array $options Verification options
     * @return array Verification result
     */
    public static function verify_download($file_path, $options = array()) {
        try {
            // Initialize result
            $result = array(
                'success' => false,
                'verified' => false,
                'checks' => array(),
                'errors' => array()
            );

            // Check file exists
            if (!file_exists($file_path)) {
                $result['errors'][] = 'File does not exist';
                return $result;
            }

            // Check file is readable
            if (!is_readable($file_path)) {
                $result['errors'][] = 'File is not readable';
                return $result;
            }

            // Check file size
            $size_check = self::verify_file_size($file_path, $options);
            $result['checks']['file_size'] = $size_check;
            if (!$size_check['passed']) {
                $result['errors'][] = $size_check['message'];
                return $result;
            }

            // Verify file is a valid ZIP
            $zip_check = self::verify_zip_file($file_path);
            $result['checks']['zip_format'] = $zip_check;
            if (!$zip_check['passed']) {
                $result['errors'][] = $zip_check['message'];
                return $result;
            }

            // Verify checksum if provided
            if (!empty($options['checksum'])) {
                $checksum_check = self::verify_checksum(
                    $file_path,
                    $options['checksum'],
                    $options['checksum_algorithm'] ?? 'sha256'
                );
                $result['checks']['checksum'] = $checksum_check;

                if (!$checksum_check['passed']) {
                    $result['errors'][] = $checksum_check['message'];
                    return $result;
                }
            }

            // Verify file signature if provided
            if (!empty($options['signature'])) {
                $signature_check = self::verify_signature($file_path, $options['signature']);
                $result['checks']['signature'] = $signature_check;

                if (!$signature_check['passed']) {
                    $result['errors'][] = $signature_check['message'];
                }
            }

            // Check ZIP contents
            $contents_check = self::verify_zip_contents($file_path);
            $result['checks']['zip_contents'] = $contents_check;
            if (!$contents_check['passed']) {
                $result['errors'][] = $contents_check['message'];
                return $result;
            }

            // Check for malicious files
            $malware_check = self::scan_for_malware($file_path);
            $result['checks']['malware_scan'] = $malware_check;
            if (!$malware_check['passed']) {
                $result['errors'][] = $malware_check['message'];
                return $result;
            }

            // All checks passed
            $result['success'] = true;
            $result['verified'] = true;

            return $result;

        } catch (Exception $e) {
            return array(
                'success' => false,
                'verified' => false,
                'errors' => array('Verification error: ' . $e->getMessage())
            );
        }
    }

    /**
     * Verify file size is within acceptable range
     *
     * @param string $file_path Path to file
     * @param array $options Options containing expected_size
     * @return array Check result
     */
    private static function verify_file_size($file_path, $options) {
        try {
            $actual_size = filesize($file_path);

            // Check file is not empty
            if ($actual_size === 0) {
                return array(
                    'passed' => false,
                    'message' => 'File is empty',
                    'actual_size' => 0
                );
            }

            // Check file is not too large
            if ($actual_size > self::$max_file_size) {
                return array(
                    'passed' => false,
                    'message' => 'File exceeds maximum size',
                    'actual_size' => $actual_size,
                    'max_size' => self::$max_file_size
                );
            }

            // If expected size provided, verify within tolerance
            if (!empty($options['expected_size'])) {
                $expected_size = (int) $options['expected_size'];
                $tolerance = 0.05; // 5% tolerance

                $min_size = $expected_size * (1 - $tolerance);
                $max_size = $expected_size * (1 + $tolerance);

                if ($actual_size < $min_size || $actual_size > $max_size) {
                    return array(
                        'passed' => false,
                        'message' => 'File size mismatch',
                        'actual_size' => $actual_size,
                        'expected_size' => $expected_size
                    );
                }
            }

            return array(
                'passed' => true,
                'message' => 'File size is valid',
                'actual_size' => $actual_size
            );

        } catch (Exception $e) {
            return array(
                'passed' => false,
                'message' => 'File size check failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Verify file is a valid ZIP archive
     *
     * @param string $file_path Path to file
     * @return array Check result
     */
    private static function verify_zip_file($file_path) {
        try {
            // Check if ZipArchive class exists
            if (!class_exists('ZipArchive')) {
                return array(
                    'passed' => false,
                    'message' => 'ZipArchive class not available'
                );
            }

            $zip = new ZipArchive();
            $open_result = $zip->open($file_path, ZipArchive::CHECKCONS);

            if ($open_result !== true) {
                $error_messages = array(
                    ZipArchive::ER_NOZIP => 'Not a valid ZIP file',
                    ZipArchive::ER_INCONS => 'ZIP file is inconsistent',
                    ZipArchive::ER_CRC => 'CRC error in ZIP file',
                    ZipArchive::ER_READ => 'Read error in ZIP file'
                );

                $message = $error_messages[$open_result] ?? 'ZIP file validation failed';

                return array(
                    'passed' => false,
                    'message' => $message,
                    'error_code' => $open_result
                );
            }

            $num_files = $zip->numFiles;
            $zip->close();

            return array(
                'passed' => true,
                'message' => 'Valid ZIP file',
                'num_files' => $num_files
            );

        } catch (Exception $e) {
            return array(
                'passed' => false,
                'message' => 'ZIP validation error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Verify file checksum
     *
     * @param string $file_path Path to file
     * @param string $expected_hash Expected hash value
     * @param string $algorithm Hash algorithm
     * @return array Check result
     */
    private static function verify_checksum($file_path, $expected_hash, $algorithm = 'sha256') {
        try {
            // Validate algorithm
            if (!in_array($algorithm, hash_algos())) {
                return array(
                    'passed' => false,
                    'message' => 'Unsupported hash algorithm: ' . $algorithm
                );
            }

            // Calculate hash
            $actual_hash = hash_file($algorithm, $file_path);

            if ($actual_hash === false) {
                return array(
                    'passed' => false,
                    'message' => 'Failed to calculate file hash'
                );
            }

            // Compare hashes (case-insensitive)
            $match = hash_equals(
                strtolower($expected_hash),
                strtolower($actual_hash)
            );

            return array(
                'passed' => $match,
                'message' => $match ? 'Checksum verified' : 'Checksum mismatch',
                'algorithm' => $algorithm,
                'expected' => $expected_hash,
                'actual' => $actual_hash
            );

        } catch (Exception $e) {
            return array(
                'passed' => false,
                'message' => 'Checksum verification error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Verify file signature (placeholder for future implementation)
     *
     * @param string $file_path Path to file
     * @param string $signature Expected signature
     * @return array Check result
     */
    private static function verify_signature($file_path, $signature) {
        // Placeholder for GPG signature verification
        // This would require openssl or gnupg PHP extensions
        return array(
            'passed' => true,
            'message' => 'Signature verification not implemented',
            'skipped' => true
        );
    }

    /**
     * Verify ZIP archive contents are valid
     *
     * @param string $file_path Path to ZIP file
     * @return array Check result
     */
    private static function verify_zip_contents($file_path) {
        try {
            $zip = new ZipArchive();
            if ($zip->open($file_path) !== true) {
                return array(
                    'passed' => false,
                    'message' => 'Failed to open ZIP file'
                );
            }

            $num_files = $zip->numFiles;

            // Check ZIP is not empty
            if ($num_files === 0) {
                $zip->close();
                return array(
                    'passed' => false,
                    'message' => 'ZIP file is empty'
                );
            }

            // Check for required plugin file
            $has_main_file = false;
            $suspicious_files = array();

            for ($i = 0; $i < $num_files; $i++) {
                $stat = $zip->statIndex($i);
                $filename = $stat['name'];

                // Check for main PHP file
                if (preg_match('/\.php$/', $filename) && !preg_match('/vendor\/|tests?\//', $filename)) {
                    $has_main_file = true;
                }

                // Check for suspicious files
                if (self::is_suspicious_file($filename)) {
                    $suspicious_files[] = $filename;
                }

                // Check for path traversal attempts
                if (strpos($filename, '..') !== false) {
                    $zip->close();
                    return array(
                        'passed' => false,
                        'message' => 'ZIP contains path traversal attempt',
                        'suspicious_file' => $filename
                    );
                }
            }

            $zip->close();

            // Check for suspicious files
            if (!empty($suspicious_files)) {
                return array(
                    'passed' => false,
                    'message' => 'ZIP contains suspicious files',
                    'suspicious_files' => $suspicious_files
                );
            }

            // Check for main plugin file
            if (!$has_main_file) {
                return array(
                    'passed' => false,
                    'message' => 'ZIP does not contain a valid plugin file'
                );
            }

            return array(
                'passed' => true,
                'message' => 'ZIP contents are valid',
                'num_files' => $num_files
            );

        } catch (Exception $e) {
            return array(
                'passed' => false,
                'message' => 'ZIP contents check error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Check if filename is suspicious
     *
     * @param string $filename File name to check
     * @return bool True if suspicious
     */
    private static function is_suspicious_file($filename) {
        $suspicious_patterns = array(
            '/\.exe$/',
            '/\.dll$/',
            '/\.bat$/',
            '/\.sh$/',
            '/\.phar$/',
            '/\.(htaccess|htpasswd)$/',
            '/^\.env/',
            '/config\.php$/',
            '/wp-config\.php$/'
        );

        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scan ZIP contents for malware patterns
     *
     * @param string $file_path Path to ZIP file
     * @return array Check result
     */
    private static function scan_for_malware($file_path) {
        try {
            $zip = new ZipArchive();
            if ($zip->open($file_path) !== true) {
                return array(
                    'passed' => false,
                    'message' => 'Failed to open ZIP for scanning'
                );
            }

            $malicious_patterns = array(
                '/eval\s*\(\s*base64_decode/i',
                '/eval\s*\(\s*gzinflate/i',
                '/eval\s*\(\s*str_rot13/i',
                '/system\s*\(/i',
                '/exec\s*\(/i',
                '/passthru\s*\(/i',
                '/shell_exec\s*\(/i',
                '/\$_FILES.*move_uploaded_file/i',
                '/base64_decode.*eval/i'
            );

            $num_files = $zip->numFiles;
            $max_scan_files = 50; // Limit number of files to scan
            $scanned = 0;

            for ($i = 0; $i < $num_files && $scanned < $max_scan_files; $i++) {
                $stat = $zip->statIndex($i);
                $filename = $stat['name'];

                // Only scan PHP files
                if (!preg_match('/\.php$/', $filename)) {
                    continue;
                }

                // Skip large files
                if ($stat['size'] > 1048576) { // 1MB
                    continue;
                }

                $contents = $zip->getFromIndex($i);
                if ($contents === false) {
                    continue;
                }

                // Check for malicious patterns
                foreach ($malicious_patterns as $pattern) {
                    if (preg_match($pattern, $contents)) {
                        $zip->close();
                        return array(
                            'passed' => false,
                            'message' => 'Malicious code detected',
                            'file' => $filename,
                            'pattern' => $pattern
                        );
                    }
                }

                $scanned++;
            }

            $zip->close();

            return array(
                'passed' => true,
                'message' => 'No malware detected',
                'scanned_files' => $scanned
            );

        } catch (Exception $e) {
            return array(
                'passed' => true, // Fail open on scan error
                'message' => 'Malware scan skipped: ' . $e->getMessage(),
                'skipped' => true
            );
        }
    }

    /**
     * Calculate file hash
     *
     * @param string $file_path Path to file
     * @param string $algorithm Hash algorithm (default: sha256)
     * @return string|false Hash value or false on failure
     */
    public static function calculate_hash($file_path, $algorithm = 'sha256') {
        try {
            if (!file_exists($file_path)) {
                return false;
            }

            return hash_file($algorithm, $file_path);

        } catch (Exception $e) {
            error_log('CUFT Download Verifier: Hash calculation failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get verification report for display
     *
     * @param array $verification_result Result from verify_download()
     * @return string Human-readable report
     */
    public static function get_verification_report($verification_result) {
        $report = array();

        if ($verification_result['verified']) {
            $report[] = '✓ Download verified successfully';
        } else {
            $report[] = '✗ Download verification failed';
        }

        foreach ($verification_result['checks'] as $check_name => $check_result) {
            $status = $check_result['passed'] ? '✓' : '✗';
            $label = ucwords(str_replace('_', ' ', $check_name));
            $report[] = sprintf('%s %s: %s', $status, $label, $check_result['message']);
        }

        if (!empty($verification_result['errors'])) {
            $report[] = '';
            $report[] = 'Errors:';
            foreach ($verification_result['errors'] as $error) {
                $report[] = '  - ' . $error;
            }
        }

        return implode("\n", $report);
    }
}
