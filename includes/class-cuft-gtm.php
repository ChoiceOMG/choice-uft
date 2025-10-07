<?php
/**
 * Google Tag Manager integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_GTM {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_head', array( $this, 'inject_head_code' ), 1 );
        add_action( 'wp_body_open', array( $this, 'inject_body_code' ), 1 );
    }
    
    /**
     * Inject GTM code in head
     */
    public function inject_head_code() {
        $gtm_id = $this->get_gtm_id();
        if ( ! $gtm_id ) {
            return;
        }

        // Get sGTM settings
        $sgtm_enabled = get_option( 'cuft_sgtm_enabled', false );
        $sgtm_url = get_option( 'cuft_sgtm_url', '' );
        $active_server = get_option( 'cuft_sgtm_active_server', 'fallback' );

        // Debug output for administrators
        if ( current_user_can( 'manage_options' ) && get_option( 'cuft_debug_enabled', false ) ) {
            echo "<!-- CUFT Debug: sGTM enabled=" . var_export( $sgtm_enabled, true ) .
                 ", URL='" . esc_html( $sgtm_url ) .
                 "', active_server='" . esc_html( $active_server ) . "' -->\n";
        }

        // Determine which URL to use
        $gtm_base_url = 'https://www.googletagmanager.com';
        $comment_prefix = 'Google Tag Manager';
        $data_attributes = '';

        if ( $sgtm_enabled && $sgtm_url && $active_server === 'custom' ) {
            $gtm_base_url = rtrim( $sgtm_url, '/' );
            $comment_prefix = 'Server-Side GTM';
            $data_attributes = ' data-cuft-gtm-source="custom" data-cuft-gtm-server="' . esc_attr( $gtm_base_url ) . '"';
        } else if ( $sgtm_enabled && $sgtm_url ) {
            $data_attributes = ' data-cuft-gtm-source="fallback" data-cuft-gtm-server="https://www.googletagmanager.com" data-cuft-fallback-reason="health_check_failed"';
        }

        ?>
        <!-- <?php echo $comment_prefix; ?> -->
        <script<?php echo $data_attributes; ?>>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        '<?php echo esc_js( $gtm_base_url ); ?>/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');
        </script>
        <!-- End <?php echo $comment_prefix; ?> -->
        <?php
    }
    
    /**
     * Inject GTM noscript in body
     */
    public function inject_body_code() {
        $gtm_id = $this->get_gtm_id();
        if ( ! $gtm_id ) {
            return;
        }

        // Get sGTM settings
        $sgtm_enabled = get_option( 'cuft_sgtm_enabled', false );
        $sgtm_url = get_option( 'cuft_sgtm_url', '' );
        $active_server = get_option( 'cuft_sgtm_active_server', 'fallback' );

        // Determine which URL to use
        $gtm_base_url = 'https://www.googletagmanager.com';
        $comment_prefix = 'Google Tag Manager';

        if ( $sgtm_enabled && $sgtm_url && $active_server === 'custom' ) {
            $gtm_base_url = rtrim( $sgtm_url, '/' );
            $comment_prefix = 'Server-Side GTM';
        }

        ?>
        <!-- <?php echo $comment_prefix; ?> (noscript) -->
        <noscript><iframe src="<?php echo esc_attr( $gtm_base_url ); ?>/ns.html?id=<?php echo esc_attr( $gtm_id ); ?>"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End <?php echo $comment_prefix; ?> (noscript) -->
        <?php
    }
    
    /**
     * Get validated GTM ID
     */
    private function get_gtm_id() {
        $gtm_id = get_option( 'cuft_gtm_id' );
        
        if ( empty( $gtm_id ) || ! $this->is_valid_gtm_id( $gtm_id ) ) {
            return false;
        }
        
        return $gtm_id;
    }
    
    /**
     * Validate GTM ID format
     */
    private function is_valid_gtm_id( $gtm_id ) {
        return preg_match( '/^GTM-[A-Z0-9]{4,}$/i', $gtm_id );
    }
}
