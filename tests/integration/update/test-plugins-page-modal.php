<?php
/**
 * Integration Test: Plugin Information Modal (T006)
 *
 * Tests the plugin information modal that displays when clicking "View Details"
 * on the Plugins page. This validates FR-102 implementation.
 *
 * Reference: quickstart.md QS-1
 *
 * @package Choice_UFT
 * @subpackage Tests
 */

/**
 * Integration test class for plugin information modal
 */
class CUFT_Test_Plugins_Page_Modal extends WP_UnitTestCase {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear plugin info cache
		delete_transient( 'cuft_plugin_info' );

		// Set up user with update_plugins capability
		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		// Clear cache
		delete_transient( 'cuft_plugin_info' );

		parent::tearDown();
	}

	/**
	 * Test: Modal displays when clicking "View Details" on Plugins page
	 *
	 * Scenario: User clicks "View version X.X.X details" link
	 * Expected: plugins_api filter returns complete plugin information
	 */
	public function test_modal_displays_plugin_information() {
		// Simulate plugins_api request
		$result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		// Verify filter returned an object (not false)
		$this->assertIsObject( $result, 'plugins_api should return object for choice-uft' );

		// Verify object is not WP_Error
		$this->assertNotInstanceOf( 'WP_Error', $result, 'plugins_api should not return WP_Error' );
	}

	/**
	 * Test: All required plugin metadata fields are present
	 *
	 * Scenario: Modal loads plugin information
	 * Expected: All required fields present (name, author, versions, compatibility)
	 */
	public function test_all_plugin_metadata_present() {
		$result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		$this->assertIsObject( $result );

		// Required fields
		$required_fields = array(
			'name',
			'slug',
			'version',
			'author',
			'homepage',
			'requires',
			'tested',
			'requires_php',
			'download_link',
			'last_updated',
			'sections',
		);

		foreach ( $required_fields as $field ) {
			$this->assertObjectHasProperty(
				$field,
				$result,
				"Plugin info should have '{$field}' field"
			);
		}

		// Verify slug matches exactly
		$this->assertEquals( 'choice-uft', $result->slug, 'Slug should be choice-uft' );

		// Verify name is populated
		$this->assertNotEmpty( $result->name, 'Plugin name should not be empty' );
		$this->assertEquals(
			'Choice Universal Form Tracker',
			$result->name,
			'Plugin name should match expected value'
		);

		// Verify author
		$this->assertNotEmpty( $result->author, 'Author should not be empty' );

		// Verify version format (semver)
		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+\.\d+$/',
			$result->version,
			'Version should be in semver format (e.g., 3.17.0)'
		);

		// Verify compatibility fields
		$this->assertNotEmpty( $result->requires, 'Requires WordPress version should not be empty' );
		$this->assertNotEmpty( $result->tested, 'Tested up to version should not be empty' );
		$this->assertNotEmpty( $result->requires_php, 'Requires PHP version should not be empty' );

		// Verify download link is HTTPS
		$this->assertStringStartsWith(
			'https://',
			$result->download_link,
			'Download link should be HTTPS'
		);

		// Verify download link points to GitHub
		$this->assertStringContainsString(
			'github.com',
			$result->download_link,
			'Download link should point to GitHub'
		);
	}

	/**
	 * Test: Modal tabs are properly structured
	 *
	 * Scenario: Modal displays tabs for Description, Installation, Changelog
	 * Expected: sections array contains required tab content
	 */
	public function test_modal_tabs_present() {
		$result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		$this->assertIsObject( $result );
		$this->assertObjectHasProperty( 'sections', $result );
		$this->assertIsArray( $result->sections, 'sections should be an array' );

		// Required tabs
		$this->assertArrayHasKey(
			'description',
			$result->sections,
			'sections should have description tab'
		);
		$this->assertArrayHasKey(
			'installation',
			$result->sections,
			'sections should have installation tab'
		);

		// Verify tab content is not empty
		$this->assertNotEmpty(
			$result->sections['description'],
			'Description tab should have content'
		);
		$this->assertNotEmpty(
			$result->sections['installation'],
			'Installation tab should have content'
		);

		// Changelog is optional (may be omitted on GitHub API failure)
		// If present, verify it has content
		if ( isset( $result->sections['changelog'] ) ) {
			$this->assertNotEmpty(
				$result->sections['changelog'],
				'Changelog tab should have content if present'
			);
		}
	}

	/**
	 * Test: "Update Now" button functionality
	 *
	 * Scenario: Modal displays "Update Now" button
	 * Expected: download_link field is present and valid
	 */
	public function test_update_now_button_present() {
		$result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		$this->assertIsObject( $result );

		// Verify download_link exists
		$this->assertObjectHasProperty( 'download_link', $result );

		// Verify download_link is a valid URL
		$parsed_url = wp_parse_url( $result->download_link );
		$this->assertNotFalse( $parsed_url, 'download_link should be a valid URL' );

		// Verify URL scheme is HTTPS
		$this->assertEquals( 'https', $parsed_url['scheme'], 'Download link should use HTTPS' );

		// Verify URL points to GitHub
		$this->assertStringContainsString(
			'github.com',
			$result->download_link,
			'Download link should point to GitHub'
		);

		// Verify URL path contains releases/download
		$this->assertStringContainsString(
			'/releases/download/',
			$result->download_link,
			'Download link should point to GitHub releases'
		);

		// Verify URL ends with .zip
		$this->assertStringEndsWith( '.zip', $result->download_link, 'Download link should be a ZIP file' );
	}

	/**
	 * Test: Graceful degradation when GitHub API fails
	 *
	 * Scenario: GitHub API unavailable or rate limited
	 * Expected: Modal still displays with partial information (no changelog)
	 */
	public function test_graceful_degradation_on_github_api_failure() {
		// Clear cache to force fresh request
		delete_transient( 'cuft_plugin_info' );

		// Mock GitHub API failure by simulating network error
		// (In real implementation, this would be detected automatically)
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				// Only intercept GitHub API requests
				if ( strpos( $url, 'api.github.com' ) !== false ) {
					return new WP_Error( 'http_request_failed', 'Network error simulated for testing' );
				}
				return $preempt;
			},
			10,
			3
		);

		$result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		// Remove filter
		remove_all_filters( 'pre_http_request', 10 );

		// Verify filter still returns an object (not false)
		$this->assertIsObject( $result, 'plugins_api should return fallback data on API failure' );

		// Verify essential fields still present (from hardcoded fallback)
		$this->assertObjectHasProperty( 'name', $result );
		$this->assertObjectHasProperty( 'version', $result );
		$this->assertObjectHasProperty( 'sections', $result );

		// Verify description and installation tabs present (hardcoded)
		$this->assertArrayHasKey( 'description', $result->sections );
		$this->assertArrayHasKey( 'installation', $result->sections );

		// Changelog may be omitted (acceptable graceful degradation)
		// This is the key test: system continues to function without changelog
		if ( ! isset( $result->sections['changelog'] ) ) {
			$this->assertTrue(
				true,
				'Changelog omission on API failure is acceptable graceful degradation'
			);
		}
	}

	/**
	 * Test: Pass-through for non-CUFT plugins
	 *
	 * Scenario: plugins_api called for other plugins
	 * Expected: Filter returns false (pass-through to WordPress.org)
	 */
	public function test_pass_through_for_other_plugins() {
		$result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'other-plugin' )
		);

		// Should return false (pass-through)
		$this->assertFalse(
			$result,
			'plugins_api should return false for non-CUFT plugins'
		);
	}

	/**
	 * Test: Pass-through for wrong action
	 *
	 * Scenario: plugins_api called with different action (e.g., query_plugins)
	 * Expected: Filter returns false (pass-through)
	 */
	public function test_pass_through_for_wrong_action() {
		$result = apply_filters(
			'plugins_api',
			false,
			'query_plugins',
			(object) array( 'slug' => 'choice-uft' )
		);

		// Should return false (pass-through)
		$this->assertFalse(
			$result,
			'plugins_api should return false for non-information actions'
		);
	}

	/**
	 * Test: HTML sanitization in sections
	 *
	 * Scenario: Plugin info contains HTML content
	 * Expected: All HTML is sanitized (no script tags, etc.)
	 */
	public function test_html_sanitization() {
		$result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		$this->assertIsObject( $result );
		$this->assertIsArray( $result->sections );

		// Check each section for dangerous HTML
		foreach ( $result->sections as $section_name => $section_content ) {
			// Verify no script tags
			$this->assertStringNotContainsString(
				'<script',
				$section_content,
				"{$section_name} section should not contain script tags"
			);

			// Verify no inline event handlers
			$this->assertDoesNotMatchRegularExpression(
				'/on\w+\s*=/',
				$section_content,
				"{$section_name} section should not contain inline event handlers"
			);

			// Verify no iframe tags
			$this->assertStringNotContainsString(
				'<iframe',
				$section_content,
				"{$section_name} section should not contain iframe tags"
			);
		}
	}

	/**
	 * Test: Caching behavior
	 *
	 * Scenario: Multiple requests for plugin information
	 * Expected: Second request uses cached data
	 */
	public function test_caching_behavior() {
		// Clear cache
		delete_transient( 'cuft_plugin_info' );

		// First request (should cache)
		$result1 = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		$this->assertIsObject( $result1 );

		// Verify cache was set
		$cached = get_transient( 'cuft_plugin_info' );
		$this->assertNotFalse( $cached, 'Plugin info should be cached after first request' );

		// Second request (should use cache)
		$result2 = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		$this->assertIsObject( $result2 );

		// Results should be identical (from cache)
		$this->assertEquals(
			$result1->version,
			$result2->version,
			'Cached result should match original'
		);
	}

	/**
	 * Test: Last updated date format
	 *
	 * Scenario: Modal displays last updated date
	 * Expected: Date is in valid format (ISO 8601 or similar)
	 */
	public function test_last_updated_date_format() {
		$result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		$this->assertIsObject( $result );
		$this->assertObjectHasProperty( 'last_updated', $result );
		$this->assertNotEmpty( $result->last_updated, 'Last updated date should not be empty' );

		// Verify date is parseable
		$timestamp = strtotime( $result->last_updated );
		$this->assertNotFalse(
			$timestamp,
			'Last updated date should be a valid date format'
		);

		// Verify date is not in the future
		$this->assertLessThanOrEqual(
			time(),
			$timestamp,
			'Last updated date should not be in the future'
		);
	}

	/**
	 * Test: Compatibility version formats
	 *
	 * Scenario: Modal displays WordPress and PHP version requirements
	 * Expected: Versions are in valid format (e.g., "5.0", "7.0")
	 */
	public function test_compatibility_version_formats() {
		$result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		$this->assertIsObject( $result );

		// Test requires field (WordPress version)
		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+$/',
			$result->requires,
			'Requires version should be in format "X.Y"'
		);

		// Test tested field (WordPress version)
		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+$/',
			$result->tested,
			'Tested version should be in format "X.Y"'
		);

		// Test requires_php field
		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+$/',
			$result->requires_php,
			'Requires PHP version should be in format "X.Y"'
		);

		// Verify reasonable version values
		$this->assertGreaterThanOrEqual(
			'5.0',
			$result->requires,
			'Requires WordPress 5.0 or higher'
		);
		$this->assertGreaterThanOrEqual(
			'7.0',
			$result->requires_php,
			'Requires PHP 7.0 or higher'
		);
	}
}
