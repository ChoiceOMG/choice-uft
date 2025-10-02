<?php
/**
 * CUFT Form Builder Validator
 *
 * Validates tracking events against constitutional compliance requirements.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Builder Validator Class
 */
class CUFT_Form_Builder_Validator {

    /**
     * Validate tracking event
     *
     * @param array $event Event data
     * @return array Validation results
     */
    public static function validate_event($event) {
        $results = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array(),
            'checks' => array(),
        );

        // Check 1: cuft_tracked field
        $results['checks']['has_cuft_tracked'] = self::check_cuft_tracked($event);
        if (!$results['checks']['has_cuft_tracked']) {
            $results['valid'] = false;
            $results['errors'][] = 'Missing required field: cuft_tracked';
        }

        // Check 2: cuft_source field
        $results['checks']['has_cuft_source'] = self::check_cuft_source($event);
        if (!$results['checks']['has_cuft_source']) {
            $results['valid'] = false;
            $results['errors'][] = 'Missing required field: cuft_source';
        }

        // Check 3: Snake case naming
        $snake_case_result = self::check_snake_case($event);
        $results['checks']['uses_snake_case'] = $snake_case_result['valid'];
        if (!$snake_case_result['valid']) {
            $results['valid'] = false;
            $results['errors'][] = 'Field naming violation: ' . implode(', ', $snake_case_result['violations']);
        }

        // Check 4: Required event fields
        $required_fields_result = self::check_required_fields($event);
        $results['checks']['required_fields_present'] = $required_fields_result['valid'];
        if (!$required_fields_result['valid']) {
            $results['warnings'][] = 'Missing recommended fields: ' . implode(', ', $required_fields_result['missing']);
        }

        // Check 5: Click ID tracking
        $click_ids_result = self::check_click_ids($event);
        $results['checks']['click_ids_tracked'] = $click_ids_result['tracked'];
        if (!empty($click_ids_result['tracked'])) {
            $results['checks']['tracked_click_ids'] = $click_ids_result['tracked'];
        }

        // Check 6: Generate lead requirements (if event is generate_lead)
        if (isset($event['event']) && $event['event'] === 'generate_lead') {
            $lead_result = self::check_generate_lead_requirements($event);
            $results['checks']['generate_lead_valid'] = $lead_result['valid'];
            if (!$lead_result['valid']) {
                $results['warnings'][] = 'generate_lead event missing: ' . implode(', ', $lead_result['missing']);
            }
        }

        return $results;
    }

    /**
     * Check for cuft_tracked field
     *
     * @param array $event Event data
     * @return bool
     */
    private static function check_cuft_tracked($event) {
        return isset($event['cuft_tracked']) && $event['cuft_tracked'] === true;
    }

    /**
     * Check for cuft_source field
     *
     * @param array $event Event data
     * @return bool
     */
    private static function check_cuft_source($event) {
        return isset($event['cuft_source']) && !empty($event['cuft_source']);
    }

    /**
     * Check for snake_case naming convention
     *
     * @param array $event Event data
     * @return array
     */
    private static function check_snake_case($event) {
        $violations = array();

        // Camel case patterns to detect
        $camelCase_patterns = array(
            'formType', 'formId', 'formName',
            'userEmail', 'userPhone', 'userName',
            'clickId', 'submittedAt', 'createdAt',
        );

        foreach ($camelCase_patterns as $pattern) {
            if (isset($event[$pattern])) {
                $violations[] = $pattern;
            }
        }

        return array(
            'valid' => empty($violations),
            'violations' => $violations,
        );
    }

    /**
     * Check for required event fields
     *
     * @param array $event Event data
     * @return array
     */
    private static function check_required_fields($event) {
        $required = array('event');
        $recommended = array('form_type', 'form_id');

        $missing = array();

        foreach ($required as $field) {
            if (!isset($event[$field])) {
                $missing[] = $field;
            }
        }

        foreach ($recommended as $field) {
            if (!isset($event[$field])) {
                $missing[] = $field . ' (recommended)';
            }
        }

        return array(
            'valid' => empty($missing),
            'missing' => $missing,
        );
    }

    /**
     * Check for tracked click IDs
     *
     * @param array $event Event data
     * @return array
     */
    private static function check_click_ids($event) {
        $click_id_fields = array(
            'click_id', 'gclid', 'gbraid', 'wbraid',
            'fbclid', 'msclkid', 'ttclid', 'li_fat_id',
            'twclid', 'snap_click_id', 'pclid',
        );

        $tracked = array();

        foreach ($click_id_fields as $field) {
            if (isset($event[$field]) && !empty($event[$field])) {
                $tracked[] = $field;
            }
        }

        return array(
            'tracked' => $tracked,
            'count' => count($tracked),
        );
    }

    /**
     * Check generate_lead event requirements
     *
     * @param array $event Event data
     * @return array
     */
    private static function check_generate_lead_requirements($event) {
        $required = array('user_email', 'user_phone');
        $click_ids = self::check_click_ids($event);

        $missing = array();

        foreach ($required as $field) {
            if (!isset($event[$field]) || empty($event[$field])) {
                $missing[] = $field;
            }
        }

        if (empty($click_ids['tracked'])) {
            $missing[] = 'any click_id';
        }

        return array(
            'valid' => empty($missing),
            'missing' => $missing,
        );
    }

    /**
     * Validate form data structure
     *
     * @param array $form_data Form data
     * @return array Validation results
     */
    public static function validate_form_data($form_data) {
        $results = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array(),
        );

        // Check if form_data is an array
        if (!is_array($form_data)) {
            $results['valid'] = false;
            $results['errors'][] = 'Form data must be an array';
            return $results;
        }

        // Validate field names
        foreach ($form_data as $key => $value) {
            // Check for camelCase field names
            if (preg_match('/[A-Z]/', $key)) {
                $results['warnings'][] = "Field '{$key}' uses camelCase naming (should use snake_case)";
            }

            // Check for empty required fields
            if (empty($value) && in_array($key, array('email', 'name'))) {
                $results['warnings'][] = "Required field '{$key}' is empty";
            }
        }

        return $results;
    }

    /**
     * Generate compliance report
     *
     * @param array $events Array of events to validate
     * @return array Compliance report
     */
    public static function generate_compliance_report($events) {
        $report = array(
            'total_events' => count($events),
            'valid_events' => 0,
            'invalid_events' => 0,
            'events' => array(),
            'summary' => array(
                'has_cuft_tracked' => 0,
                'has_cuft_source' => 0,
                'uses_snake_case' => 0,
                'has_click_ids' => 0,
            ),
        );

        foreach ($events as $event) {
            $validation = self::validate_event($event);

            if ($validation['valid']) {
                $report['valid_events']++;
            } else {
                $report['invalid_events']++;
            }

            // Update summary counts
            if ($validation['checks']['has_cuft_tracked']) {
                $report['summary']['has_cuft_tracked']++;
            }
            if ($validation['checks']['has_cuft_source']) {
                $report['summary']['has_cuft_source']++;
            }
            if ($validation['checks']['uses_snake_case']) {
                $report['summary']['uses_snake_case']++;
            }
            if (!empty($validation['checks']['click_ids_tracked'])) {
                $report['summary']['has_click_ids']++;
            }

            $report['events'][] = array(
                'event' => $event,
                'validation' => $validation,
            );
        }

        // Calculate percentages
        if ($report['total_events'] > 0) {
            $report['compliance_percentage'] = ($report['valid_events'] / $report['total_events']) * 100;
        } else {
            $report['compliance_percentage'] = 0;
        }

        return $report;
    }

    /**
     * Get validation rules documentation
     *
     * @return array
     */
    public static function get_validation_rules() {
        return array(
            'required_fields' => array(
                'cuft_tracked' => 'Must be true to indicate CUFT processed the event',
                'cuft_source' => 'Must indicate which framework generated the event',
            ),
            'naming_convention' => array(
                'rule' => 'All field names must use snake_case',
                'examples' => array(
                    'correct' => array('form_type', 'user_email', 'click_id'),
                    'incorrect' => array('formType', 'userEmail', 'clickId'),
                ),
            ),
            'recommended_fields' => array(
                'event' => 'Event name (form_submit or generate_lead)',
                'form_type' => 'Framework identifier',
                'form_id' => 'Form identifier',
                'user_email' => 'User email (if provided)',
                'user_phone' => 'User phone (if provided)',
            ),
            'click_id_tracking' => array(
                'supported' => array(
                    'click_id', 'gclid', 'gbraid', 'wbraid',
                    'fbclid', 'msclkid', 'ttclid', 'li_fat_id',
                    'twclid', 'snap_click_id', 'pclid',
                ),
            ),
            'generate_lead_requirements' => array(
                'user_email' => 'Required',
                'user_phone' => 'Required',
                'click_id' => 'At least one click ID required',
            ),
        );
    }
}
