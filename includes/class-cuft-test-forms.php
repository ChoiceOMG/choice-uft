<?php
/**
 * Test Forms Handler for Frontend Testing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Test_Forms {

    /**
     * Constructor
     */
    public function __construct() {
        // Register shortcode for test forms
        add_shortcode( 'cuft_test_forms', array( $this, 'render_test_forms_shortcode' ) );

        // Handle URL parameter test mode
        add_action( 'wp_footer', array( $this, 'inject_test_mode' ), 999 );

        // Enqueue scripts for test page
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_test_scripts' ) );

        // Add admin bar link for easy access
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_link' ), 100 );

        // Create test page on activation if it doesn't exist
        add_action( 'init', array( $this, 'maybe_create_test_page' ) );

        // Handle AJAX form submission with email
        add_action( 'wp_ajax_cuft_frontend_test_submit', array( $this, 'handle_frontend_test_submit' ) );
    }

    /**
     * Check if user can access test features
     */
    private function can_access_tests() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Render test forms shortcode
     */
    public function render_test_forms_shortcode( $atts ) {
        // Only show to admins
        if ( ! $this->can_access_tests() ) {
            return '<p>You must be logged in as an administrator to access test forms.</p>';
        }

        $frameworks = CUFT_Form_Detector::get_framework_status();
        $detected_frameworks = array_filter( $frameworks, function( $fw ) {
            return $fw['detected'];
        });

        if ( empty( $detected_frameworks ) ) {
            return '<p>No form frameworks detected. Please install and activate a supported form plugin.</p>';
        }

        $site_domain = parse_url( home_url(), PHP_URL_HOST );
        $admin_email = get_option( 'admin_email' );
        $gtm_id = get_option( 'cuft_gtm_id', '' );

        ob_start();
        ?>
        <div id="cuft-test-forms-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px;">
                <h1 style="margin: 0 0 10px 0; color: white;">📊 Choice UFT - Test Forms</h1>
                <p style="margin: 0; opacity: 0.9;">Test your form tracking integration with Tag Assistant and debugging tools</p>
                <?php if ( $gtm_id ): ?>
                    <p style="margin: 10px 0 0 0; font-size: 14px; opacity: 0.8;">GTM Container: <strong><?php echo esc_html( $gtm_id ); ?></strong></p>
                <?php else: ?>
                    <p style="margin: 10px 0 0 0; font-size: 14px; background: rgba(255,255,255,0.2); padding: 10px; border-radius: 5px;">
                        ⚠️ GTM not configured. <a href="<?php echo admin_url( 'options-general.php?page=choice-universal-form-tracker' ); ?>" style="color: white; text-decoration: underline;">Configure GTM</a>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Navigation Bar -->
            <div style="background: #f0f3f7; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="<?php echo admin_url('options-general.php?page=choice-universal-form-tracker'); ?>"
                           class="button">
                            ⚙️ Back to Plugin Settings
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=google-tag-assistant'); ?>"
                           class="button button-secondary"
                           target="_blank">
                            🔍 Tag Assistant
                        </a>
                    </div>
                    <div style="font-size: 12px; color: #666; text-align: right;">
                        <strong>Test forms trigger real tracking events</strong><br>
                        Check Tag Assistant for validation • Events include cuft_tracked & cuft_source
                    </div>
                </div>
            </div>

            <!-- Test Forms Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
                <?php foreach ( $detected_frameworks as $framework ): ?>
                    <?php echo $this->render_framework_test_form( $framework, $admin_email ); ?>
                <?php endforeach; ?>
            </div>


            <!-- Framework-Specific Scripts Loading -->
            <script>
                // Initialize framework-specific test forms
                (function() {
                    'use strict';

                    function initFrameworkTestForms() {
                        console.log('[CUFT] Initializing framework-specific test forms...');

                        // Initialize each detected framework
                        const frameworks = <?php echo json_encode( array_keys( $detected_frameworks ) ); ?>;

                        frameworks.forEach(function(frameworkKey) {
                            const formElements = document.querySelectorAll('.cuft-test-form[data-framework="' + frameworkKey + '"]');

                            formElements.forEach(function(formElement) {
                                // Initialize framework-specific behavior
                                if (window.CUFTTestForms && window.CUFTTestForms[frameworkKey]) {
                                    window.CUFTTestForms[frameworkKey].init(formElement);
                                    console.log('[CUFT] Initialized ' + frameworkKey + ' test form');
                                } else {
                                    console.warn('[CUFT] Framework module not found: ' + frameworkKey);
                                    // Fallback to generic handler if framework module not loaded
                                    initGenericHandler(formElement, frameworkKey);
                                }
                            });
                        });
                    }

                    function initGenericHandler(formElement, framework) {
                        const submitButton = formElement.querySelector('.cuft-submit-btn');
                        if (submitButton) {
                            submitButton.addEventListener('click', function(e) {
                                e.preventDefault();
                                alert('Framework module for ' + framework + ' not loaded. Please check console for errors.');
                            });
                        }
                    }

                    // Initialize when all scripts are loaded
                    function attemptInit() {
                        if (window.CUFTTestForms && window.CUFTTestForms.common) {
                            initFrameworkTestForms();
                        } else {
                            console.log('[CUFT] Waiting for framework scripts to load...');
                            setTimeout(attemptInit, 200);
                        }
                    }

                    // Start initialization
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', attemptInit);
                    } else {
                        attemptInit();
                    }
                })();
            </script>

            <!-- Instructions -->
            <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin-top: 30px;">
                <h3 style="margin-top: 0; color: #0c5460;">📖 How to Test</h3>
                <ol style="margin: 0; padding-left: 20px;">
                    <li>Open Google Tag Assistant or GTM Preview mode</li>
                    <li>Click "Submit Test Form" for any framework above</li>
                    <li>Check the browser console for real-time events</li>
                    <li>Verify the <code>form_submit</code> event in Tag Assistant (always fires)</li>
                    <li>Verify the <code>generate_lead</code> event in Tag Assistant (fires with email + phone + click_id)</li>
                    <li>Confirm all parameters are correctly passed (click_id, gclid, UTM params, etc.)</li>
                    <li>Check your WordPress admin email for the test submission notification</li>
                </ol>

                <h4 style="margin-top: 15px; color: #0c5460;">🎯 Generate Lead Requirements by Framework</h4>
                <p style="margin: 5px 0;">The <code>generate_lead</code> event has different requirements per framework:</p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 10px 0;">
                    <div style="background: #f0f8ff; border: 1px solid #cce7ff; border-radius: 4px; padding: 8px; font-size: 12px;">
                        <strong>Elementor, Avada, Ninja:</strong><br>
                        Email + Phone + Click ID
                    </div>
                    <div style="background: #fff0f5; border: 1px solid #ffcce7; border-radius: 4px; padding: 8px; font-size: 12px;">
                        <strong>Contact Form 7, Gravity:</strong><br>
                        Email + UTM Campaign only
                    </div>
                </div>

                <p style="margin: 5px 0; color: #28a745;"><strong>Test forms include appropriate fields for each framework's requirements!</strong></p>

                <h4 style="margin-top: 15px; color: #0c5460;">URL Parameter Testing</h4>
                <p style="margin: 5px 0;">You can also trigger automatic tests using URL parameters:</p>
                <code style="background: white; padding: 10px; display: block; border-radius: 4px; font-size: 12px;">
                    <?php echo home_url( '?cuft_test=1&framework=elementor&auto_submit=1' ); ?>
                </code>
            </div>
        </div>

        <!-- Load Framework-Specific Test Scripts -->
        <script src="<?php echo CUFT_URL; ?>assets/test-forms/cuft-test-common.js?ver=<?php echo CUFT_VERSION; ?>"></script>
        <?php foreach ( $detected_frameworks as $framework ): ?>
            <script src="<?php echo CUFT_URL; ?>assets/test-forms/cuft-test-<?php
                $script_name = $framework['key'];
                if ( $script_name === 'contact_form_7' ) {
                    $script_name = 'cf7';
                } elseif ( $script_name === 'ninja_forms' ) {
                    $script_name = 'ninja';
                } elseif ( $script_name === 'gravity_forms' ) {
                    $script_name = 'gravity';
                }
                echo esc_attr( $script_name );
            ?>.js?ver=<?php echo CUFT_VERSION; ?>"></script>
        <?php endforeach; ?>
        <script>
            // Ensure config is available
            window.cuftTestConfig = {
                ajax_url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
                adminEmail: '<?php echo esc_js( $admin_email ); ?>',
                siteUrl: '<?php echo esc_js( home_url() ); ?>',
                gtmId: '<?php echo esc_js( $gtm_id ); ?>',
                testMode: false,
                framework: '',
                autoSubmit: false,
                verbose: false
            };

            // Initialize after a short delay
            setTimeout(function() {
                if (window.CUFTTestForms && window.CUFTTestForms.common) {
                    console.log('[CUFT] Test forms system loaded successfully');
                } else {
                    console.error('[CUFT] Test forms script not loaded');
                }
            }, 500);
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render framework-specific test form
     */
    private function render_framework_test_form( $framework, $admin_email ) {
        $form_id_map = array(
            'avada' => 'fusion_form_1',
            'elementor' => 'elementor-form-widget-7a2c4f9',
            'contact_form_7' => 'wpcf7-f123-p456-o1',
            'ninja_forms' => 'nf-form-3',
            'gravity_forms' => 'gform_1'
        );
        $form_id = isset( $form_id_map[ $framework['key'] ] ) ? $form_id_map[ $framework['key'] ] : 'test_form_1';

        ob_start();
        ?>
        <div class="cuft-test-form-card" style="background: white; border: 2px solid #e9ecef; border-radius: 8px; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; color: #495057;"><?php echo esc_html( $framework['name'] ); ?></h3>
                <span style="background: #28a745; color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px;">ACTIVE</span>
            </div>

            <?php echo $this->render_framework_form_html( $framework['key'], $form_id, $admin_email ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render framework-specific form HTML
     */
    private function render_framework_form_html( $framework_key, $form_id, $admin_email ) {
        switch ( $framework_key ) {
            case 'elementor':
                return $this->render_elementor_form( $form_id, $admin_email );
            case 'contact_form_7':
                return $this->render_cf7_form( $form_id, $admin_email );
            case 'gravity_forms':
                return $this->render_gravity_form( $form_id, $admin_email );
            case 'avada':
                return $this->render_avada_form( $form_id, $admin_email );
            case 'ninja_forms':
                return $this->render_ninja_form( $form_id, $admin_email );
            default:
                return $this->render_generic_form( $framework_key, $form_id, $admin_email );
        }
    }

    /**
     * Render Elementor form HTML
     */
    private function render_elementor_form( $form_id, $admin_email ) {
        ob_start();
        ?>
        <form class="cuft-test-form elementor-form"
              data-framework="elementor"
              data-form-id="<?php echo esc_attr( $form_id ); ?>"
              data-cuft-test-form="true"
              onsubmit="return false;">
            <div class="elementor-form-fields-wrapper">
                <div class="elementor-field-group elementor-column elementor-col-100">
                    <label for="form-field-email" class="elementor-field-label">Email Address</label>
                    <input size="1"
                           type="email"
                           name="form_fields[email]"
                           id="form-field-email"
                           class="elementor-field elementor-size-sm"
                           data-field-type="email"
                           data-field="email"
                           inputmode="email"
                           value="<?php echo esc_attr( $admin_email ); ?>"
                           readonly>
                </div>
                <div class="elementor-field-group elementor-column elementor-col-100">
                    <label for="form-field-phone" class="elementor-field-label">Phone Number</label>
                    <input size="1"
                           type="tel"
                           name="form_fields[phone]"
                           id="form-field-phone"
                           class="elementor-field elementor-size-sm"
                           data-field-type="tel"
                           data-field="phone"
                           inputmode="tel"
                           value="1-555-555-5555"
                           readonly>
                </div>
                <div class="elementor-field-group elementor-column elementor-col-100">
                    <button type="submit"
                            class="elementor-button elementor-size-sm cuft-submit-btn"
                            style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                        🚀 Submit Test Form
                    </button>
                </div>
            </div>
            <div class="test-result" style="display: none; margin-top: 10px;"></div>
        </form>

        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; color: #6c757d; margin-top: 10px;">
            <div><strong>Form ID:</strong> <?php echo esc_html( $form_id ); ?></div>
            <div><strong>Click ID:</strong> test_click_elementor_<?php echo time(); ?></div>
            <div><strong>Campaign:</strong> test_campaign_elementor</div>
            <div><strong>Generate Lead:</strong> Email + Phone + Click ID (all required)</div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Contact Form 7 HTML
     */
    private function render_cf7_form( $form_id, $admin_email ) {
        // Extract numeric ID from form identifier (e.g., wpcf7-f123-p456-o1 → 123)
        $numeric_id = preg_replace( '/\D/', '', $form_id );
        if ( empty( $numeric_id ) ) {
            $numeric_id = '123'; // Default fallback
        }

        ob_start();
        ?>
        <div class="wpcf7" id="<?php echo esc_attr( $form_id ); ?>" data-wpcf7-id="<?php echo esc_attr( $numeric_id ); ?>" data-cuft-test-form="true">
            <form class="wpcf7-form init cuft-test-form"
                  data-framework="contact_form_7"
                  data-form-id="<?php echo esc_attr( $form_id ); ?>"
                  data-wpcf7-id="<?php echo esc_attr( $numeric_id ); ?>"
                  data-cuft-test-form="true"
                  novalidate="novalidate"
                  data-status="init">
                <p>
                    <label>Email Address<br>
                        <span class="wpcf7-form-control-wrap" data-name="your-email">
                            <input size="40"
                                   class="wpcf7-form-control wpcf7-text wpcf7-email wpcf7-validates-as-required wpcf7-validates-as-email"
                                   type="email"
                                   name="your-email"
                                   value="<?php echo esc_attr( $admin_email ); ?>"
                                   readonly>
                        </span>
                    </label>
                </p>
                <p>
                    <label>Phone Number<br>
                        <span class="wpcf7-form-control-wrap" data-name="your-phone">
                            <input size="40"
                                   class="wpcf7-form-control wpcf7-text wpcf7-tel"
                                   type="tel"
                                   name="your-phone"
                                   value="1-555-555-5555"
                                   readonly>
                        </span>
                    </label>
                </p>
                <p>
                    <input class="wpcf7-form-control wpcf7-submit has-spinner cuft-submit-btn"
                           type="submit"
                           value="🚀 Submit Test Form"
                           style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                </p>
            </form>
            <div class="wpcf7-response-output" aria-hidden="true" style="display: none;"></div>
            <div class="test-result" style="display: none; margin-top: 10px;"></div>
        </div>

        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; color: #6c757d; margin-top: 10px;">
            <div><strong>Form ID:</strong> <?php echo esc_html( $form_id ); ?></div>
            <div><strong>Click ID:</strong> test_click_cf7_<?php echo time(); ?></div>
            <div><strong>Campaign:</strong> test_campaign_contact_form_7</div>
            <div><strong>Generate Lead:</strong> Email + UTM Campaign (phone & click_id NOT required)</div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Gravity Forms HTML
     */
    private function render_gravity_form( $form_id, $admin_email ) {
        $numeric_id = preg_replace( '/\D/', '', $form_id ) ?: '1';

        ob_start();
        ?>
        <div class="gform_wrapper">
            <form id="<?php echo esc_attr( $form_id ); ?>"
                  class="cuft-test-form"
                  data-framework="gravity_forms"
                  data-form-id="<?php echo esc_attr( $form_id ); ?>"
                  data-formid="<?php echo esc_attr( $numeric_id ); ?>"
                  data-cuft-test-form="true">
                <div class="gform_body">
                    <ul class="gform_fields">
                        <li class="gfield gfield_email gfield_required">
                            <label class="gfield_label" for="input_<?php echo esc_attr( $numeric_id ); ?>_1">Email Address</label>
                            <div class="ginput_container ginput_container_email">
                                <input name="input_1"
                                       id="input_<?php echo esc_attr( $numeric_id ); ?>_1"
                                       type="email"
                                       value="<?php echo esc_attr( $admin_email ); ?>"
                                       class="large"
                                       readonly>
                            </div>
                        </li>
                        <li class="gfield gfield_phone">
                            <label class="gfield_label" for="input_<?php echo esc_attr( $numeric_id ); ?>_2">Phone Number</label>
                            <div class="ginput_container ginput_container_phone">
                                <input name="input_2"
                                       id="input_<?php echo esc_attr( $numeric_id ); ?>_2"
                                       type="tel"
                                       value="1-555-555-5555"
                                       class="large"
                                       readonly>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="gform_footer">
                    <input type="submit"
                           class="gform_button button cuft-submit-btn"
                           value="🚀 Submit Test Form"
                           style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                </div>
            </form>
            <div class="gform_confirmation_message" style="display: none;"></div>
            <div class="test-result" style="display: none; margin-top: 10px;"></div>
        </div>

        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; color: #6c757d; margin-top: 10px;">
            <div><strong>Form ID:</strong> <?php echo esc_html( $form_id ); ?></div>
            <div><strong>Click ID:</strong> test_click_gravity_<?php echo time(); ?></div>
            <div><strong>Campaign:</strong> test_campaign_gravity_forms</div>
            <div><strong>Generate Lead:</strong> Email + UTM Campaign (phone & click_id NOT required)</div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Avada/Fusion form HTML
     */
    private function render_avada_form( $form_id, $admin_email ) {
        ob_start();
        ?>
        <div class="fusion-form">
            <form class="fusion-form-form cuft-test-form"
                  data-framework="avada"
                  data-form-id="<?php echo esc_attr( $form_id ); ?>"
                  data-cuft-test-form="true"
                  method="post"
                  enctype="multipart/form-data">
                <div class="fusion-form-field fusion-form-field-email">
                    <label for="fusion-form-field-email">Email Address</label>
                    <input type="email"
                           name="email"
                           id="fusion-form-field-email"
                           class="fusion-form-input"
                           value="<?php echo esc_attr( $admin_email ); ?>"
                           readonly>
                </div>
                <div class="fusion-form-field fusion-form-field-phone">
                    <label for="fusion-form-field-phone">Phone Number</label>
                    <input type="tel"
                           name="phone"
                           id="fusion-form-field-phone"
                           class="fusion-form-input"
                           value="1-555-555-5555"
                           readonly>
                </div>
                <div class="fusion-form-field fusion-form-submit-field">
                    <button type="submit"
                            class="fusion-button fusion-button-default cuft-submit-btn"
                            style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                        🚀 Submit Test Form
                    </button>
                </div>
            </form>
            <div class="fusion-form-response-success" style="display: none;">
                <div class="fusion-alert success">Form submitted successfully!</div>
            </div>
            <div class="test-result" style="display: none; margin-top: 10px;"></div>
        </div>

        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; color: #6c757d; margin-top: 10px;">
            <div><strong>Form ID:</strong> <?php echo esc_html( $form_id ); ?></div>
            <div><strong>Click ID:</strong> test_click_avada_<?php echo time(); ?></div>
            <div><strong>Campaign:</strong> test_campaign_avada</div>
            <div><strong>Generate Lead:</strong> Email + Phone + Click ID (all required)</div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Ninja Forms HTML
     */
    private function render_ninja_form( $form_id, $admin_email ) {
        $numeric_id = preg_replace( '/\D/', '', $form_id ) ?: '3';

        ob_start();
        ?>
        <div class="nf-form-cont">
            <div class="nf-form-wrap">
                <form class="nf-form cuft-test-form"
                      data-framework="ninja_forms"
                      data-form-id="<?php echo esc_attr( $form_id ); ?>"
                      data-cuft-test-form="true"
                      method="post">
                    <div class="nf-field-container email-container">
                        <div class="nf-field" data-field-type="email">
                            <label for="nf-field-<?php echo esc_attr( $numeric_id ); ?>-email" class="nf-label">Email Address</label>
                            <input type="email"
                                   name="email"
                                   id="nf-field-<?php echo esc_attr( $numeric_id ); ?>-email"
                                   class="nf-element"
                                   value="<?php echo esc_attr( $admin_email ); ?>"
                                   readonly>
                        </div>
                    </div>
                    <div class="nf-field-container phone-container">
                        <div class="nf-field" data-field-type="phone">
                            <label for="nf-field-<?php echo esc_attr( $numeric_id ); ?>-phone" class="nf-label">Phone Number</label>
                            <input type="tel"
                                   name="phone"
                                   id="nf-field-<?php echo esc_attr( $numeric_id ); ?>-phone"
                                   class="nf-element"
                                   value="1-555-555-5555"
                                   readonly>
                        </div>
                    </div>
                    <div class="nf-field-container submit-container">
                        <input type="button"
                               class="nf-element cuft-submit-btn"
                               value="🚀 Submit Test Form"
                               style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                    </div>
                </form>
                <div class="nf-response-msg" style="display: none;"></div>
            </div>
            <div class="test-result" style="display: none; margin-top: 10px;"></div>
        </div>

        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; color: #6c757d; margin-top: 10px;">
            <div><strong>Form ID:</strong> <?php echo esc_html( $form_id ); ?></div>
            <div><strong>Click ID:</strong> test_click_ninja_<?php echo time(); ?></div>
            <div><strong>Campaign:</strong> test_campaign_ninja_forms</div>
            <div><strong>Generate Lead:</strong> Email + Phone + Click ID (all required)</div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render generic form HTML (fallback)
     */
    private function render_generic_form( $framework_key, $form_id, $admin_email ) {
        ob_start();
        ?>
        <form class="cuft-test-form"
              data-framework="<?php echo esc_attr( $framework_key ); ?>"
              data-form-id="<?php echo esc_attr( $form_id ); ?>"
              data-cuft-test-form="true"
              onsubmit="return false;"
              style="display: flex; flex-direction: column; gap: 10px;">

            <div>
                <label style="display: block; margin-bottom: 5px; color: #6c757d; font-size: 14px;">Email Address</label>
                <input type="email"
                       name="email"
                       value="<?php echo esc_attr( $admin_email ); ?>"
                       readonly
                       style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; background: #f8f9fa;">
            </div>

            <div>
                <label style="display: block; margin-bottom: 5px; color: #6c757d; font-size: 14px;">Phone Number</label>
                <input type="tel"
                       name="phone"
                       value="1-555-555-5555"
                       readonly
                       style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; background: #f8f9fa;">
            </div>

            <button type="button"
                    class="button button-primary cuft-submit-btn"
                    data-framework="<?php echo esc_attr( $framework_key ); ?>"
                    style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                🚀 Submit Test Form
            </button>

            <div class="test-result" style="display: none; margin-top: 10px;"></div>
        </form>

        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; color: #6c757d; margin-top: 10px;">
            <div><strong>Form ID:</strong> <?php echo esc_html( $form_id ); ?></div>
            <div><strong>Click ID:</strong> test_click_<?php echo esc_html( $framework_key ); ?>_<?php echo time(); ?></div>
            <div><strong>Campaign:</strong> test_campaign_<?php echo esc_html( $framework_key ); ?></div>
            <div><strong>Generate Lead:</strong> Framework-specific requirements</div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue test scripts
     */
    public function enqueue_test_scripts() {
        // Check if we're on a page with our shortcode or test mode
        global $post;

        // More permissive check - load on any page that might have our content
        $has_shortcode = false;
        if ( $post && is_object( $post ) && isset( $post->post_content ) ) {
            $has_shortcode = has_shortcode( $post->post_content, 'cuft_test_forms' );
        }

        $test_mode = isset( $_GET['cuft_test'] ) && $_GET['cuft_test'] === '1';
        $is_test_page = false;

        // Check if this is our test page
        $test_page_id = get_option( 'cuft_test_page_id' );
        if ( $test_page_id && is_page( $test_page_id ) ) {
            $is_test_page = true;
        }

        // Load script if any condition is met
        if ( ! $has_shortcode && ! $test_mode && ! $is_test_page ) {
            return;
        }

        if ( ! $this->can_access_tests() ) {
            return;
        }


    }

    /**
     * Inject test mode for URL parameter testing
     */
    public function inject_test_mode() {
        if ( ! isset( $_GET['cuft_test'] ) || $_GET['cuft_test'] !== '1' ) {
            return;
        }

        if ( ! $this->can_access_tests() ) {
            return;
        }

        $framework = isset( $_GET['framework'] ) ? sanitize_text_field( $_GET['framework'] ) : '';
        $auto_submit = isset( $_GET['auto_submit'] ) && $_GET['auto_submit'] === '1';

        if ( empty( $framework ) ) {
            return;
        }

        // Check if framework is detected
        if ( ! CUFT_Form_Detector::is_framework_detected( $framework ) ) {
            return;
        }

        ?>
        <!-- CUFT Test Mode Active -->
        <div id="cuft-test-mode-indicator" style="position: fixed; top: 50px; right: 20px; background: #28a745; color: white; padding: 15px; border-radius: 8px; z-index: 999999; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
            <div style="font-weight: bold; margin-bottom: 5px;">🧪 CUFT Test Mode Active</div>
            <div style="font-size: 12px;">Framework: <?php echo esc_html( $framework ); ?></div>
            <?php if ( $auto_submit ): ?>
                <div style="font-size: 12px;">Auto-submit enabled</div>
            <?php endif; ?>
            <button onclick="document.getElementById('cuft-test-mode-indicator').style.display='none'" style="position: absolute; top: 5px; right: 5px; background: none; border: none; color: white; cursor: pointer;">✕</button>
        </div>
        <?php
    }

    /**
     * Add admin bar link for quick access
     */
    public function add_admin_bar_link( $wp_admin_bar ) {
        if ( ! $this->can_access_tests() ) {
            return;
        }

        $test_page_id = get_option( 'cuft_test_page_id' );
        if ( ! $test_page_id ) {
            return;
        }

        $test_page_url = get_permalink( $test_page_id );
        if ( ! $test_page_url ) {
            return;
        }

        $wp_admin_bar->add_node( array(
            'id' => 'cuft-test-forms',
            'title' => '🧪 CUFT Test Forms',
            'href' => $test_page_url,
            'meta' => array(
                'target' => '_blank',
                'title' => 'Open CUFT Test Forms'
            )
        ));
    }

    /**
     * Maybe create test page
     */
    public function maybe_create_test_page() {
        // Only create on admin requests to avoid frontend issues
        if ( ! is_admin() ) {
            return;
        }

        $test_page_id = get_option( 'cuft_test_page_id' );

        // Check if page exists
        if ( $test_page_id && get_post( $test_page_id ) ) {
            return;
        }

        // Check if user can create pages
        if ( ! current_user_can( 'publish_pages' ) ) {
            return;
        }

        // Create the test page
        $page_data = array(
            'post_title' => 'CUFT Test Forms',
            'post_content' => '[cuft_test_forms]',
            'post_status' => 'private',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'meta_input' => array(
                '_cuft_test_page' => '1'
            )
        );

        $page_id = wp_insert_post( $page_data );

        if ( ! is_wp_error( $page_id ) ) {
            update_option( 'cuft_test_page_id', $page_id );
        }
    }

    /**
     * Handle frontend test form submission with email
     */
    public function handle_frontend_test_submit() {
        // Verify user can access test features
        if ( ! $this->can_access_tests() ) {
            wp_send_json_error( array( 'message' => 'Access denied' ) );
        }

        // Validate nonce if provided (optional for frontend)
        if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( $_POST['nonce'], 'cuft_frontend_test' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        // Get and validate form data
        $framework = isset( $_POST['framework'] ) ? sanitize_text_field( $_POST['framework'] ) : '';
        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
        $form_id = isset( $_POST['form_id'] ) ? sanitize_text_field( $_POST['form_id'] ) : '';

        if ( empty( $framework ) ) {
            wp_send_json_error( array( 'message' => 'Framework is required' ) );
        }

        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'Valid email is required' ) );
        }

        // Get framework display name
        $framework_names = array(
            'avada' => 'Avada/Fusion Forms',
            'elementor' => 'Elementor Forms',
            'contact_form_7' => 'Contact Form 7',
            'ninja_forms' => 'Ninja Forms',
            'gravity_forms' => 'Gravity Forms'
        );
        $framework_name = isset( $framework_names[ $framework ] ) ? $framework_names[ $framework ] : $framework;

        // Prepare test data for email notification
        $test_data = array(
            'event' => 'form_submit',
            'user_email' => $email,
            'user_phone' => $phone,
            'form_type' => $framework, // Use snake_case field name
            'form_id' => $form_id,
            'test_submission' => true,
            'timestamp' => current_time( 'mysql' ),
            'click_id' => 'click_id_' . $framework . '_test',
            'utm_campaign' => 'test_campaign_' . $framework . '_test',
            'utm_source' => 'cuft_test',
            'utm_medium' => 'test_form'
        );

        // Add any additional UTM parameters from POST data
        $utm_params = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
        foreach ( $utm_params as $param ) {
            if ( isset( $_POST[ $param ] ) && ! empty( $_POST[ $param ] ) ) {
                $test_data[ $param ] = sanitize_text_field( $_POST[ $param ] );
            }
        }

        // Generate tracking ID
        $tracking_id = 'frontend_test_' . wp_generate_password( 8, false );

        // Send email to WordPress admin
        $admin_email = get_option( 'admin_email' );
        $email_sent = $this->send_test_form_email( $admin_email, $framework_name, $tracking_id, $test_data );

        // Log the submission
        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'info', 'Frontend test form submission', $test_data );
        }

        // Store test submission for verification
        set_transient( 'cuft_test_' . $tracking_id, $test_data, 300 ); // 5 minutes

        $response = array(
            'success' => true,
            'message' => 'Test form submitted successfully',
            'email_sent' => $email_sent,
            'tracking_id' => $tracking_id,
            'framework' => $framework_name
        );

        wp_send_json_success( $response );
    }

    /**
     * Send test form email notification
     */
    private function send_test_form_email( $to, $framework_name, $tracking_id, $test_data ) {
        $site_name = get_bloginfo( 'name' );
        $site_url = home_url();

        $subject = sprintf( '[%s] Test Form Submission - %s', $site_name, $framework_name );

        $message = "You have received a test form submission from the Choice Universal Form Tracker plugin.\n\n";
        $message .= "==================================================\n";
        $message .= "FRAMEWORK: {$framework_name}\n";
        $message .= "TRACKING ID: {$tracking_id}\n";
        $message .= "==================================================\n\n";

        $message .= "FORM DATA:\n";
        $message .= "--------------------------------------------------\n";
        $message .= "Email: {$test_data['user_email']}\n";
        $message .= "Phone: {$test_data['user_phone']}\n";
        $message .= "Click ID: {$test_data['click_id']}\n";
        $message .= "Form ID: {$test_data['form_id']}\n";
        $message .= "Timestamp: {$test_data['timestamp']}\n";
        $message .= "\n";

        // Add UTM data if present
        if ( ! empty( $test_data['utm_source'] ) || ! empty( $test_data['utm_medium'] ) || ! empty( $test_data['utm_campaign'] ) ) {
            $message .= "UTM TRACKING DATA:\n";
            $message .= "--------------------------------------------------\n";
            if ( ! empty( $test_data['utm_source'] ) ) {
                $message .= "Source: {$test_data['utm_source']}\n";
            }
            if ( ! empty( $test_data['utm_medium'] ) ) {
                $message .= "Medium: {$test_data['utm_medium']}\n";
            }
            if ( ! empty( $test_data['utm_campaign'] ) ) {
                $message .= "Campaign: {$test_data['utm_campaign']}\n";
            }
            if ( ! empty( $test_data['utm_term'] ) ) {
                $message .= "Term: {$test_data['utm_term']}\n";
            }
            if ( ! empty( $test_data['utm_content'] ) ) {
                $message .= "Content: {$test_data['utm_content']}\n";
            }
            $message .= "\n";
        }

        // Add GTM status
        $gtm_id = get_option( 'cuft_gtm_id', '' );
        $gtm_status = ! empty( $gtm_id ) ? 'Active (ID: ' . $gtm_id . ')' : 'Not configured';

        $message .= "TRACKING STATUS:\n";
        $message .= "--------------------------------------------------\n";
        $message .= "GTM Status: {$gtm_status}\n";
        $message .= "Debug Mode: " . ( get_option( 'cuft_debug_enabled', false ) ? 'Enabled' : 'Disabled' ) . "\n";
        $message .= "\n";

        $message .= "==================================================\n";
        $message .= "This is a test submission from the Choice Universal\n";
        $message .= "Form Tracker plugin to verify form tracking is\n";
        $message .= "working correctly.\n";
        $message .= "\n";
        $message .= "Site: {$site_url}\n";
        $message .= "Admin: {$site_url}/wp-admin/options-general.php?page=choice-universal-form-tracker\n";

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <' . $to . '>',
            'Reply-To: ' . $to
        );

        // Send the email
        $sent = wp_mail( $to, $subject, $message, $headers );

        // Log email attempt
        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 
                $sent ? 'info' : 'error', 
                'Test form email ' . ( $sent ? 'sent' : 'failed' ), 
                array(
                    'to' => $to,
                    'framework' => $framework_name,
                    'tracking_id' => $tracking_id
                )
            );
        }

        return $sent;
    }
}

// Initialize
new CUFT_Test_Forms();