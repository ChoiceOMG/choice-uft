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
     * Resolve the gtm.js host and tag attributes from the sGTM settings.
     *
     * The host is either Google's www.googletagmanager.com or the validated
     * bare hostname from CUFT_SGTM; the URL itself is always built by
     * CUFT_SGTM from that host and the validated container ID.
     *
     * @return array { host: string, attributes: array }
     */
    private function get_loader_config() {
        $config = array(
            'host'       => CUFT_SGTM::DEFAULT_HOST,
            'attributes' => array(),
        );

        if ( CUFT_SGTM::custom_host_active() ) {
            $config['host']       = CUFT_SGTM::get_host();
            $config['attributes'] = array(
                'data-cuft-gtm-source' => 'custom',
                'data-cuft-gtm-server' => 'https://' . $config['host'],
            );
        } elseif ( get_option( 'cuft_sgtm_enabled', false ) && '' !== CUFT_SGTM::get_host() ) {
            $config['attributes'] = array(
                'data-cuft-gtm-source'      => 'fallback',
                'data-cuft-gtm-server'      => 'https://' . CUFT_SGTM::DEFAULT_HOST,
                'data-cuft-fallback-reason' => 'health_check_failed',
            );
        }

        return $config;
    }

    /**
     * Enqueue the standard GTM loader as an inline script in <head>.
     *
     * The loader is Google's snippet: it initialises window.dataLayer, pushes
     * gtm.start, then inserts gtm.js (async). The script URL is built in PHP
     * from a validated host and container ID and passed through esc_url()
     * and CUFT_Utils::js_encode(). It is attached to a source-less handle
     * printed in the head, so the dataLayer init still runs before gtm.js loads.
     */
    public function enqueue_gtm() {
        $gtm_id = $this->get_gtm_id();
        if ( ! $gtm_id ) {
            return;
        }

        $config = $this->get_loader_config();
        $src    = esc_url( CUFT_SGTM::script_url( $config['host'], $gtm_id ), array( 'https' ) );
        if ( '' === $src ) {
            return;
        }
        $this->script_attributes = $config['attributes'];

        $loader = "(function(w,d,s,l){w[l]=w[l]||[];w[l].push({'gtm.start':\n"
            . "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
            . "j=d.createElement(s);j.async=true;j.src=" . CUFT_Utils::js_encode( $src ) . ";\n"
            . "f.parentNode.insertBefore(j,f);\n"
            . "})(window,document,'script','dataLayer');";

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
            echo '<!-- CUFT Debug: sGTM enabled=' . esc_html( get_option( 'cuft_sgtm_enabled', false ) ? 'true' : 'false' ) .
                 ", host='" . esc_html( CUFT_SGTM::get_host() ) .
                 "', active_server='" . esc_html( get_option( 'cuft_sgtm_active_server', 'fallback' ) ) . "' -->\n";
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

        $custom         = CUFT_SGTM::custom_host_active();
        $host           = $custom ? CUFT_SGTM::get_host() : CUFT_SGTM::DEFAULT_HOST;
        $comment_prefix = $custom ? 'Server-Side GTM' : 'Google Tag Manager';
        $src            = CUFT_SGTM::noscript_url( $host, $gtm_id );
        if ( '' === $src ) {
            return;
        }

        ?>
        <!-- <?php echo esc_html( $comment_prefix ); ?> (noscript) -->
        <noscript><iframe src="<?php echo esc_url( $src, array( 'https' ) ); ?>"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End <?php echo esc_html( $comment_prefix ); ?> (noscript) -->
        <?php
    }

    /**
     * Get validated GTM ID
     */
    private function get_gtm_id() {
        return CUFT_SGTM::sanitize_gtm_id( (string) get_option( 'cuft_gtm_id', '' ) );
    }
}
