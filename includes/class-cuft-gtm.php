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
        
        ?>
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');</script>
        <!-- End Google Tag Manager -->
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
        
        ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $gtm_id ); ?>"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
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
