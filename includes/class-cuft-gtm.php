<?php
/**
 * Google Tag Manager integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_GTM {
    
    /**
     * Script handle that carries the GTM loader as an inline script.
     */
    const HANDLE = 'cuft-gtm';

    /**
     * Data attributes for the loader's <script> tag, set while enqueuing.
     *
     * @var array
     */
    private $script_attributes = array();

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_gtm' ), 1 );
        add_filter( 'wp_inline_script_attributes', array( $this, 'filter_inline_script_attributes' ), 10, 2 );
        add_action( 'wp_head', array( $this, 'inject_head_code' ), 1 );
        add_action( 'wp_body_open', array( $this, 'inject_body_code' ), 1 );
    }

    /**
     * Resolve the GTM base URL and tag attributes from the sGTM settings.
     *
     * @return array { base_url: string, attributes: array }
     */
    private function get_loader_config() {
        $sgtm_enabled  = get_option( 'cuft_sgtm_enabled', false );
        $sgtm_url      = get_option( 'cuft_sgtm_url', '' );
        $active_server = get_option( 'cuft_sgtm_active_server', 'fallback' );

        $config = array(
            'base_url'   => 'https://www.googletagmanager.com',
            'attributes' => array(),
        );

        if ( $sgtm_enabled && $sgtm_url && $active_server === 'custom' ) {
            $config['base_url']   = rtrim( $sgtm_url, '/' );
            $config['attributes'] = array(
                'data-cuft-gtm-source' => 'custom',
                'data-cuft-gtm-server' => $config['base_url'],
            );
        } elseif ( $sgtm_enabled && $sgtm_url ) {
            $config['attributes'] = array(
                'data-cuft-gtm-source'       => 'fallback',
                'data-cuft-gtm-server'       => 'https://www.googletagmanager.com',
                'data-cuft-fallback-reason'  => 'health_check_failed',
            );
        }

        return $config;
    }

    /**
     * Enqueue the standard GTM loader as an inline script in <head>.
     *
     * The loader is Google's snippet unchanged: it initialises window.dataLayer,
     * pushes gtm.start, then inserts gtm.js (async) from Google or the
     * configured server-side GTM host. It is attached to a source-less handle
     * printed in the head, so the dataLayer init still runs before gtm.js loads.
     */
    public function enqueue_gtm() {
        $gtm_id = $this->get_gtm_id();
        if ( ! $gtm_id ) {
            return;
        }

        $config = $this->get_loader_config();
        $this->script_attributes = $config['attributes'];

        $loader = "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n"
            . "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
            . "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n"
            . wp_json_encode( $config['base_url'] . '/gtm.js?id=' ) . "+i+dl;f.parentNode.insertBefore(j,f);\n"
            . "})(window,document,'script','dataLayer'," . wp_json_encode( $gtm_id ) . ');';

        // In the head (in_footer false) so the dataLayer exists before tags or form scripts push to it.
        wp_register_script( self::HANDLE, false, array(), CUFT_VERSION, false );
        wp_enqueue_script( self::HANDLE );
        wp_add_inline_script( self::HANDLE, $loader );
    }

    /**
     * Put the sGTM data attributes on the loader's inline <script> tag.
     *
     * @param array  $attributes Tag attributes.
     * @param string $data       Inline script body.
     * @return array
     */
    public function filter_inline_script_attributes( $attributes, $data = '' ) {
        if ( empty( $this->script_attributes ) || ! isset( $attributes['id'] ) || self::HANDLE . '-js-after' !== $attributes['id'] ) {
            return $attributes;
        }
        return array_merge( $attributes, $this->script_attributes );
    }

    /**
     * Admin-only debug comment in <head>. The loader itself is enqueued by
     * enqueue_gtm().
     */
    public function inject_head_code() {
        if ( ! $this->get_gtm_id() ) {
            return;
        }

        if ( current_user_can( 'manage_options' ) && get_option( 'cuft_debug_enabled', false ) ) {
            $sgtm_enabled  = get_option( 'cuft_sgtm_enabled', false );
            $sgtm_url      = get_option( 'cuft_sgtm_url', '' );
            $active_server = get_option( 'cuft_sgtm_active_server', 'fallback' );
            echo '<!-- CUFT Debug: sGTM enabled=' . esc_html( $sgtm_enabled ? 'true' : 'false' ) .
                 ", URL='" . esc_html( $sgtm_url ) .
                 "', active_server='" . esc_html( $active_server ) . "' -->\n";
        }
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
        <!-- <?php echo esc_html( $comment_prefix ); ?> (noscript) -->
        <noscript><iframe src="<?php echo esc_attr( $gtm_base_url ); ?>/ns.html?id=<?php echo esc_attr( $gtm_id ); ?>"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End <?php echo esc_html( $comment_prefix ); ?> (noscript) -->
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
