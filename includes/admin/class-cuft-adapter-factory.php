<?php
/**
 * Framework Adapter Factory
 *
 * Provides lazy-loading factory pattern for framework adapters.
 * Ensures only required adapters are instantiated.
 *
 * @package    Choice_UTM_Form_Tracker
 * @subpackage Choice_UTM_Form_Tracker/includes/admin
 * @since      3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CUFT Adapter Factory Class
 *
 * Creates and manages framework adapter instances.
 */
class CUFT_Adapter_Factory {

    /**
     * Adapter registry
     *
     * Maps framework IDs to adapter class names
     *
     * @var array
     */
    private static $registry = array(
        'elementor' => 'CUFT_Elementor_Adapter',
        'cf7'       => 'CUFT_CF7_Adapter',
        'gravity'   => 'CUFT_Gravity_Adapter',
        'ninja'     => 'CUFT_Ninja_Adapter',
        'avada'     => 'CUFT_Avada_Adapter',
    );

    /**
     * Loaded adapter instances (cache)
     *
     * @var array
     */
    private static $instances = array();

    /**
     * Get adapter for specified framework
     *
     * Implements lazy loading - only instantiates adapters when needed.
     *
     * @param string $framework Framework identifier
     * @return Abstract_CUFT_Adapter|WP_Error Adapter instance or error
     */
    public static function get_adapter($framework) {
        // Validate framework exists in registry
        if (!isset(self::$registry[$framework])) {
            return new WP_Error(
                'invalid_framework',
                sprintf(__('Invalid framework: %s', 'choice-uft'), $framework)
            );
        }

        // Return cached instance if available
        if (isset(self::$instances[$framework])) {
            return self::$instances[$framework];
        }

        // Load adapter class if not already loaded
        $class_name = self::$registry[$framework];
        $file_path = self::get_adapter_file_path($framework);

        if (!file_exists($file_path)) {
            return new WP_Error(
                'adapter_file_not_found',
                sprintf(__('Adapter file not found for framework: %s', 'choice-uft'), $framework)
            );
        }

        require_once $file_path;

        // Verify class exists after loading
        if (!class_exists($class_name)) {
            return new WP_Error(
                'adapter_class_not_found',
                sprintf(__('Adapter class not found: %s', 'choice-uft'), $class_name)
            );
        }

        // Instantiate adapter
        $adapter = new $class_name();

        // Verify adapter extends base class
        if (!($adapter instanceof Abstract_CUFT_Adapter)) {
            return new WP_Error(
                'invalid_adapter',
                sprintf(__('Adapter must extend Abstract_CUFT_Adapter: %s', 'choice-uft'), $class_name)
            );
        }

        // Cache instance
        self::$instances[$framework] = $adapter;

        return $adapter;
    }

    /**
     * Get all available adapters
     *
     * Returns only adapters for frameworks that are currently available.
     *
     * @return array Array of adapter instances keyed by framework ID
     */
    public static function get_available_adapters() {
        $available = array();

        foreach (array_keys(self::$registry) as $framework) {
            $adapter = self::get_adapter($framework);

            if (!is_wp_error($adapter) && $adapter->is_available()) {
                $available[$framework] = $adapter;
            }
        }

        return $available;
    }

    /**
     * Get framework information for all registered frameworks
     *
     * @return array Framework information including availability
     */
    public static function get_frameworks_info() {
        $frameworks = array();

        foreach (array_keys(self::$registry) as $framework) {
            $adapter = self::get_adapter($framework);

            if (is_wp_error($adapter)) {
                continue;
            }

            $frameworks[$framework] = array(
                'id' => $framework,
                'name' => $adapter->get_framework_name(),
                'version' => $adapter->get_version(),
                'available' => $adapter->is_available(),
                'supports_generation' => $adapter->is_available(), // Only available frameworks support generation
            );
        }

        return $frameworks;
    }

    /**
     * Check if a framework is available
     *
     * @param string $framework Framework identifier
     * @return bool True if available
     */
    public static function is_framework_available($framework) {
        $adapter = self::get_adapter($framework);

        if (is_wp_error($adapter)) {
            return false;
        }

        return $adapter->is_available();
    }

    /**
     * Get file path for adapter
     *
     * @param string $framework Framework identifier
     * @return string File path
     */
    private static function get_adapter_file_path($framework) {
        $class_name = self::$registry[$framework];

        // Convert class name to file name (CUFT_Elementor_Adapter -> class-cuft-elementor-adapter.php)
        $file_name = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';

        return CUFT_PATH . 'includes/admin/framework-adapters/' . $file_name;
    }

    /**
     * Register a custom adapter
     *
     * Allows third-party developers to register additional adapters.
     *
     * @param string $framework  Framework identifier
     * @param string $class_name Adapter class name
     * @return bool True on success
     */
    public static function register_adapter($framework, $class_name) {
        if (isset(self::$registry[$framework])) {
            return false; // Already registered
        }

        self::$registry[$framework] = $class_name;

        return true;
    }

    /**
     * Clear adapter cache
     *
     * Useful for testing or when frameworks are activated/deactivated.
     */
    public static function clear_cache() {
        self::$instances = array();
    }

    /**
     * Get registered framework IDs
     *
     * @return array Array of framework identifiers
     */
    public static function get_registered_frameworks() {
        return array_keys(self::$registry);
    }

    /**
     * Validate framework identifier
     *
     * @param string $framework Framework identifier
     * @return bool True if valid
     */
    public static function is_valid_framework($framework) {
        return isset(self::$registry[$framework]);
    }
}
