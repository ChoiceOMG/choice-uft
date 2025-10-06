<?php
/**
 * Choice Universal Form Tracker - Input Validator
 *
 * Sanitizes and validates all user inputs for security.
 * Prevents XSS, SQL injection, and other input-based attacks.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Input Validator Class
 *
 * Provides input sanitization and validation
 */
class CUFT_Input_Validator {
    /**
     * Validation rules for different input types
     *
     * @var array
     */
    private static $validation_rules = array(
        'version' => '/^v?(\d+)\.(\d+)\.(\d+)(-[a-zA-Z0-9\.\-]+)?$/',
        'url' => FILTER_VALIDATE_URL,
        'email' => FILTER_VALIDATE_EMAIL,
        'boolean' => '/^(true|false|1|0|yes|no)$/i',
        'integer' => '/^\d+$/',
        'slug' => '/^[a-z0-9\-\_]+$/i',
        'hash' => '/^[a-f0-9]+$/i'
    );

    /**
     * Sanitize and validate input value
     *
     * @param mixed $value Input value
     * @param string $type Expected type
     * @param array $options Additional validation options
     * @return array Validation result with sanitized value
     */
    public static function validate($value, $type, $options = array()) {
        $result = array(
            'valid' => false,
            'sanitized' => null,
            'errors' => array()
        );

        try {
            // Handle null values
            if ($value === null) {
                if (!empty($options['required'])) {
                    $result['errors'][] = 'Value is required';
                    return $result;
                }

                $result['valid'] = true;
                $result['sanitized'] = null;
                return $result;
            }

            // Sanitize based on type
            switch ($type) {
                case 'text':
                    $result = self::validate_text($value, $options);
                    break;

                case 'email':
                    $result = self::validate_email($value, $options);
                    break;

                case 'url':
                    $result = self::validate_url($value, $options);
                    break;

                case 'version':
                    $result = self::validate_version($value, $options);
                    break;

                case 'boolean':
                    $result = self::validate_boolean($value, $options);
                    break;

                case 'integer':
                    $result = self::validate_integer($value, $options);
                    break;

                case 'slug':
                    $result = self::validate_slug($value, $options);
                    break;

                case 'hash':
                    $result = self::validate_hash($value, $options);
                    break;

                case 'json':
                    $result = self::validate_json($value, $options);
                    break;

                case 'array':
                    $result = self::validate_array($value, $options);
                    break;

                default:
                    $result['errors'][] = 'Unknown validation type: ' . $type;
            }

            return $result;

        } catch (Exception $e) {
            $result['errors'][] = 'Validation error: ' . $e->getMessage();
            return $result;
        }
    }

    /**
     * Validate text input
     *
     * @param string $value Input value
     * @param array $options Validation options
     * @return array Validation result
     */
    private static function validate_text($value, $options) {
        $result = array(
            'valid' => false,
            'sanitized' => null,
            'errors' => array()
        );

        // Sanitize
        $sanitized = sanitize_text_field($value);

        // Check length
        if (!empty($options['min_length']) && strlen($sanitized) < $options['min_length']) {
            $result['errors'][] = 'Text is too short (minimum: ' . $options['min_length'] . ')';
            return $result;
        }

        if (!empty($options['max_length']) && strlen($sanitized) > $options['max_length']) {
            $result['errors'][] = 'Text is too long (maximum: ' . $options['max_length'] . ')';
            return $result;
        }

        // Check pattern
        if (!empty($options['pattern'])) {
            if (!preg_match($options['pattern'], $sanitized)) {
                $result['errors'][] = 'Text does not match required format';
                return $result;
            }
        }

        $result['valid'] = true;
        $result['sanitized'] = $sanitized;
        return $result;
    }

    /**
     * Validate email address
     *
     * @param string $value Input value
     * @param array $options Validation options
     * @return array Validation result
     */
    private static function validate_email($value, $options) {
        $result = array(
            'valid' => false,
            'sanitized' => null,
            'errors' => array()
        );

        $sanitized = sanitize_email($value);

        if (!is_email($sanitized)) {
            $result['errors'][] = 'Invalid email address';
            return $result;
        }

        $result['valid'] = true;
        $result['sanitized'] = $sanitized;
        return $result;
    }

    /**
     * Validate URL
     *
     * @param string $value Input value
     * @param array $options Validation options
     * @return array Validation result
     */
    private static function validate_url($value, $options) {
        $result = array(
            'valid' => false,
            'sanitized' => null,
            'errors' => array()
        );

        $sanitized = esc_url_raw($value);

        // Validate URL format
        if (!filter_var($sanitized, FILTER_VALIDATE_URL)) {
            $result['errors'][] = 'Invalid URL format';
            return $result;
        }

        // Check HTTPS requirement
        if (!empty($options['require_https'])) {
            if (parse_url($sanitized, PHP_URL_SCHEME) !== 'https') {
                $result['errors'][] = 'URL must use HTTPS';
                return $result;
            }
        }

        // Check allowed domains
        if (!empty($options['allowed_domains'])) {
            $host = parse_url($sanitized, PHP_URL_HOST);
            if (!in_array($host, $options['allowed_domains'])) {
                $result['errors'][] = 'URL domain not allowed';
                return $result;
            }
        }

        $result['valid'] = true;
        $result['sanitized'] = $sanitized;
        return $result;
    }

    /**
     * Validate version string
     *
     * @param string $value Input value
     * @param array $options Validation options
     * @return array Validation result
     */
    private static function validate_version($value, $options) {
        $result = array(
            'valid' => false,
            'sanitized' => null,
            'errors' => array()
        );

        $sanitized = sanitize_text_field($value);

        if (!preg_match(self::$validation_rules['version'], $sanitized)) {
            $result['errors'][] = 'Invalid version format (expected: X.Y.Z)';
            return $result;
        }

        $result['valid'] = true;
        $result['sanitized'] = $sanitized;
        return $result;
    }

    /**
     * Validate boolean value
     *
     * @param mixed $value Input value
     * @param array $options Validation options
     * @return array Validation result
     */
    private static function validate_boolean($value, $options) {
        $result = array(
            'valid' => true,
            'sanitized' => null,
            'errors' => array()
        );

        // Convert to boolean
        if (is_bool($value)) {
            $result['sanitized'] = $value;
        } elseif (is_numeric($value)) {
            $result['sanitized'] = (bool) $value;
        } elseif (is_string($value)) {
            $lower = strtolower(trim($value));
            $result['sanitized'] = in_array($lower, array('true', '1', 'yes', 'on'));
        } else {
            $result['valid'] = false;
            $result['errors'][] = 'Invalid boolean value';
        }

        return $result;
    }

    /**
     * Validate integer value
     *
     * @param mixed $value Input value
     * @param array $options Validation options
     * @return array Validation result
     */
    private static function validate_integer($value, $options) {
        $result = array(
            'valid' => false,
            'sanitized' => null,
            'errors' => array()
        );

        if (!is_numeric($value)) {
            $result['errors'][] = 'Value must be numeric';
            return $result;
        }

        $sanitized = (int) $value;

        // Check range
        if (isset($options['min']) && $sanitized < $options['min']) {
            $result['errors'][] = 'Value is too small (minimum: ' . $options['min'] . ')';
            return $result;
        }

        if (isset($options['max']) && $sanitized > $options['max']) {
            $result['errors'][] = 'Value is too large (maximum: ' . $options['max'] . ')';
            return $result;
        }

        $result['valid'] = true;
        $result['sanitized'] = $sanitized;
        return $result;
    }

    /**
     * Validate slug (alphanumeric with dashes/underscores)
     *
     * @param string $value Input value
     * @param array $options Validation options
     * @return array Validation result
     */
    private static function validate_slug($value, $options) {
        $result = array(
            'valid' => false,
            'sanitized' => null,
            'errors' => array()
        );

        $sanitized = sanitize_key($value);

        if (!preg_match(self::$validation_rules['slug'], $sanitized)) {
            $result['errors'][] = 'Invalid slug format (only alphanumeric, dash, underscore)';
            return $result;
        }

        $result['valid'] = true;
        $result['sanitized'] = $sanitized;
        return $result;
    }

    /**
     * Validate hash string (hexadecimal)
     *
     * @param string $value Input value
     * @param array $options Validation options
     * @return array Validation result
     */
    private static function validate_hash($value, $options) {
        $result = array(
            'valid' => false,
            'sanitized' => null,
            'errors' => array()
        );

        $sanitized = strtolower(trim($value));

        if (!preg_match(self::$validation_rules['hash'], $sanitized)) {
            $result['errors'][] = 'Invalid hash format (must be hexadecimal)';
            return $result;
        }

        // Check length for specific algorithms
        if (!empty($options['algorithm'])) {
            $expected_lengths = array(
                'md5' => 32,
                'sha1' => 40,
                'sha256' => 64,
                'sha512' => 128
            );

            if (isset($expected_lengths[$options['algorithm']])) {
                if (strlen($sanitized) !== $expected_lengths[$options['algorithm']]) {
                    $result['errors'][] = 'Hash length mismatch for ' . $options['algorithm'];
                    return $result;
                }
            }
        }

        $result['valid'] = true;
        $result['sanitized'] = $sanitized;
        return $result;
    }

    /**
     * Validate JSON string
     *
     * @param string $value Input value
     * @param array $options Validation options
     * @return array Validation result
     */
    private static function validate_json($value, $options) {
        $result = array(
            'valid' => false,
            'sanitized' => null,
            'errors' => array()
        );

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $result['errors'][] = 'Invalid JSON: ' . json_last_error_msg();
            return $result;
        }

        $result['valid'] = true;
        $result['sanitized'] = $decoded;
        return $result;
    }

    /**
     * Validate array input
     *
     * @param mixed $value Input value
     * @param array $options Validation options
     * @return array Validation result
     */
    private static function validate_array($value, $options) {
        $result = array(
            'valid' => false,
            'sanitized' => null,
            'errors' => array()
        );

        if (!is_array($value)) {
            $result['errors'][] = 'Value must be an array';
            return $result;
        }

        // Sanitize array values
        $sanitized = array_map('sanitize_text_field', $value);

        // Check allowed values
        if (!empty($options['allowed_values'])) {
            foreach ($sanitized as $item) {
                if (!in_array($item, $options['allowed_values'])) {
                    $result['errors'][] = 'Array contains invalid value: ' . $item;
                    return $result;
                }
            }
        }

        $result['valid'] = true;
        $result['sanitized'] = $sanitized;
        return $result;
    }

    /**
     * Validate multiple inputs at once
     *
     * @param array $inputs Array of input values with types
     * @return array Validation results
     */
    public static function validate_batch($inputs) {
        $results = array(
            'valid' => true,
            'data' => array(),
            'errors' => array()
        );

        foreach ($inputs as $key => $input) {
            $value = $input['value'] ?? null;
            $type = $input['type'] ?? 'text';
            $options = $input['options'] ?? array();

            $validation = self::validate($value, $type, $options);

            $results['data'][$key] = $validation['sanitized'];

            if (!$validation['valid']) {
                $results['valid'] = false;
                $results['errors'][$key] = $validation['errors'];
            }
        }

        return $results;
    }

    /**
     * Sanitize request data
     *
     * @param array $data Request data
     * @param array $schema Validation schema
     * @return array Sanitized data
     */
    public static function sanitize_request($data, $schema) {
        $sanitized = array();

        foreach ($schema as $field => $rules) {
            $value = $data[$field] ?? null;
            $type = $rules['type'] ?? 'text';
            $options = $rules['options'] ?? array();

            $validation = self::validate($value, $type, $options);

            if ($validation['valid']) {
                $sanitized[$field] = $validation['sanitized'];
            } elseif (!empty($rules['default'])) {
                $sanitized[$field] = $rules['default'];
            }
        }

        return $sanitized;
    }

    /**
     * Check for SQL injection attempts
     *
     * @param string $value Input value
     * @return bool True if suspicious
     */
    public static function is_sql_injection_attempt($value) {
        $patterns = array(
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\'|\")\s*(OR|AND)\s*\1\s*=\s*\1/i',
            '/;.*\bDROP\b/i'
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for XSS attempts
     *
     * @param string $value Input value
     * @return bool True if suspicious
     */
    public static function is_xss_attempt($value) {
        $patterns = array(
            '/<script\b[^>]*>/i',
            '/<iframe\b[^>]*>/i',
            '/javascript:/i',
            '/on\w+\s*=/i', // onclick, onload, etc.
            '/<embed\b[^>]*>/i',
            '/<object\b[^>]*>/i'
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log validation failure for security monitoring
     *
     * @param string $field Field name
     * @param string $error Error message
     * @param mixed $value Failed value
     * @return void
     */
    public static function log_validation_failure($field, $error, $value) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_entry = sprintf(
            '[CUFT Validation] Field: %s, Error: %s, Value: %s',
            $field,
            $error,
            is_string($value) ? substr($value, 0, 100) : gettype($value)
        );

        error_log($log_entry);
    }
}
