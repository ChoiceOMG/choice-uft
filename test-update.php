<?php
/**
 * Test script for verifying GitHub updater functionality
 *
 * Usage: Copy this file to WordPress root and access via browser or WP-CLI:
 * wp eval-file test-update.php
 */

// Load WordPress
if (!defined('ABSPATH')) {
    require_once(dirname(__FILE__) . '/wp-load.php');
}

echo "Choice Universal Form Tracker - Update Test\n";
echo "============================================\n\n";

// Check if plugin is active
if (!is_plugin_active('choice-universal-form-tracker/choice-universal-form-tracker.php')) {
    echo "❌ Plugin is not active. Please activate it first.\n";
    exit;
}

echo "✅ Plugin is active\n";

// Get current version
$current_version = defined('CUFT_VERSION') ? CUFT_VERSION : 'Unknown';
echo "📌 Current version: $current_version\n\n";

// Initialize updater manually for testing
if (class_exists('CUFT_GitHub_Updater')) {
    echo "✅ GitHub Updater class found\n";

    $updater = new CUFT_GitHub_Updater(
        WP_PLUGIN_DIR . '/choice-universal-form-tracker/choice-universal-form-tracker.php',
        $current_version,
        'ChoiceOMG',
        'choice-uft'
    );

    // Force check for updates
    echo "🔍 Checking for updates...\n";
    delete_transient('cuft_github_version');
    delete_transient('cuft_github_changelog');

    $remote_version = $updater->force_check();
    echo "📦 Latest version available: $remote_version\n";

    if (version_compare($current_version, $remote_version, '<')) {
        echo "🆕 UPDATE AVAILABLE: $current_version → $remote_version\n\n";

        // Get download URL
        $download_url_method = new ReflectionMethod('CUFT_GitHub_Updater', 'get_download_url');
        $download_url_method->setAccessible(true);
        $download_url = $download_url_method->invoke($updater, $remote_version);

        echo "📥 Download URL: $download_url\n";

        // Test if it's using release asset
        if (strpos($download_url, '/releases/download/') !== false) {
            echo "✅ Using release asset (correct)\n";
        } else {
            echo "⚠️  Using archive URL (fallback)\n";
        }

        // Test download
        echo "\n🧪 Testing download...\n";
        $response = wp_remote_head($download_url, array('timeout' => 10));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200 || $code == 302) {
                $size = wp_remote_retrieve_header($response, 'content-length');
                if ($size) {
                    $size_mb = round($size / 1024 / 1024, 2);
                    echo "✅ Download URL is valid (Size: {$size_mb} MB)\n";
                } else {
                    echo "✅ Download URL is valid\n";
                }
            } else {
                echo "❌ Download failed with HTTP $code\n";
            }
        } else {
            echo "❌ Download test failed: " . $response->get_error_message() . "\n";
        }

        echo "\n📋 Next Steps:\n";
        echo "1. Go to WordPress Admin → Plugins\n";
        echo "2. You should see an update available for Choice Universal Form Tracker\n";
        echo "3. Click 'Update Now' to test the update process\n";
        echo "4. Verify the plugin updates to version $remote_version\n";

    } else {
        echo "✅ Plugin is up to date\n";
    }

} else {
    echo "❌ GitHub Updater class not found\n";
}

echo "\n🔧 Troubleshooting:\n";
echo "- Clear transients: delete_transient('cuft_github_version');\n";
echo "- Force WordPress update check: wp_update_plugins();\n";
echo "- Check error log for any issues\n";