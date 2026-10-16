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
     * Whether the Google tag gateway setting is on. The noscript stays on
     * Google whenever it is, because Google does not serve ns.html through
     * the gateway.
     *
     * @return bool
     */
    private function gateway_enabled() {
        return wp_validate_boolean( get_option( 'cuft_gtg_enabled', false ) ) && class_exists( 'CUFT_GTG_Health' );
    }

    /**
     * Resolve the gtm.js source and tag attributes from the gateway and sGTM
     * settings.
     *
     * With a healthy Google tag gateway the source is the validated
     * same-origin path from CUFT_GTG_Health, and fallback_src is Google's
     * gtm.js for the browser fallback. Otherwise the host is either Google's
     * www.googletagmanager.com or the validated bare hostname from
     * CUFT_SGTM, and the URL is built by CUFT_SGTM from that host and the
     * validated container ID.
     *
     * @param string $gtm_id Validated container ID.
     * @return array { src: string, fallback_src: string, attributes: array }
     */
    private function get_loader_config( $gtm_id ) {
        $gtg_reason = null;
        if ( $this->gateway_enabled() ) {
            $gtg_path = CUFT_GTG_Health::normalize_path( get_option( 'cuft_gtg_script_path', '' ) );
            // The container ID the probe checked; '' when the probe would refuse it.
            $gtg_id = CUFT_GTG_Health::gtm_id();
            if ( '' !== $gtg_path && $gtg_id === $gtm_id && 'gateway' === get_option( 'cuft_gtg_active', 'fallback' ) ) {
                return array(
                    'src'          => $gtg_path . '?id=' . $gtm_id,
                    'fallback_src' => CUFT_SGTM::script_url( CUFT_SGTM::DEFAULT_HOST, $gtm_id ),
                    'attributes'   => array(
                        'data-cuft-gtm-source' => 'gateway',
                        'data-cuft-gtm-server' => $gtg_path,
                    ),
                );
            }
            if ( '' !== $gtg_path ) {
                // Not healthy (yet): the loader the site used before stays, Google or its
                // tagging server, and the tag says why.
                $gtg_reason = (string) get_option( 'cuft_gtg_fallback_reason', 'not yet checked' );
            }
        }

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

        if ( null !== $gtg_reason ) {
            $config['attributes']['data-cuft-gtg-state']  = 'fallback';
            $config['attributes']['data-cuft-gtg-reason'] = $gtg_reason;
        }

        return array(
            'src'          => CUFT_SGTM::script_url( $config['host'], $gtm_id ),
            'fallback_src' => '',
            'attributes'   => $config['attributes'],
        );
    }

    /**
     * Enqueue the standard GTM loader as an inline script in <head>.
     *
     * The loader is Google's snippet: it initialises window.dataLayer, pushes
     * gtm.start, then inserts gtm.js (async). The script URL is built in PHP
     * from a validated host or gateway path and container ID and passed
     * through esc_url() and CUFT_Utils::js_encode(). It is attached to a
     * source-less handle printed in the head, so the dataLayer init still
     * runs before gtm.js loads.
     *
     * Through the Google tag gateway the loader also carries a browser
     * fallback: an error, or a script that did not define the container,
     * loads gtm.js from Google instead.
     */
    public function enqueue_gtm() {
        $gtm_id = $this->get_gtm_id();
        if ( ! $gtm_id ) {
            return;
        }

        $config = $this->get_loader_config( $gtm_id );
        $src    = esc_url( $config['src'], array( 'https' ) );
        if ( '' === $src ) {
            return;
        }
        $this->script_attributes = $config['attributes'];

        $fallback     = '';
        $fallback_src = '' === $config['fallback_src'] ? '' : esc_url( $config['fallback_src'], array( 'https' ) );
        if ( '' !== $fallback_src ) {
            $fallback = 'var fb=function(){if(j.cuftFb)return;j.cuftFb=1;var k=d.createElement(s);k.async=true;k.src='
                . CUFT_Utils::js_encode( $fallback_src ) . ";f.parentNode.insertBefore(k,f);};j.onerror=fb;\n"
                . 'j.onload=function(){if(!(w.google_tag_manager&&w.google_tag_manager[' . CUFT_Utils::js_encode( $gtm_id ) . "]))fb();};\n";
        }

        $loader = "(function(w,d,s,l){w[l]=w[l]||[];w[l].push({'gtm.start':\n"
            . "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
            . "j=d.createElement(s);j.async=true;j.src=" . CUFT_Utils::js_encode( $src ) . ";\n"
            . $fallback
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

        // Google does not serve ns.html through the tag gateway: keep the noscript on Google.
        $custom         = ! $this->gateway_enabled() && CUFT_SGTM::custom_host_active();
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
