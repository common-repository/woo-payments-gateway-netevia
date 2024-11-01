<?php
/*
Plugin Name: Netevia Payments for WooCommerce
Plugin URI: https://developer.wordpress.org/plugins/netevia-payments-for-woocomerce/
Description: Netevia Payments for WooCommerce
Author: Netelement Software
Version: 1.0.7
Author URI: https://profiles.wordpress.org/netelementsoftware
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Exit if executed directl
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Functions used by plugins
 */
if ( ! class_exists( 'WC_NG_Dependencies' ) )
    require_once 'class-wc-ng-dependencies.php';

/**
 * WC Detection
 */
if ( ! function_exists( 'is_woocommerce_active' ) ) {
    function is_woocommerce_active() {
        return WC_NG_Dependencies::woocommerce_active_check();
    }
}

if ( is_woocommerce_active() ) {

    //current plugin version
    define( 'WOONETEVIA_VER', '1.0.7' );

    // The text domain for strings localization
    define( 'WOONETEVIATEXTDOMAIN', 'netevia-payments-for-woocommerce' );

    if ( !class_exists( 'WOONETEVIA' ) ) {

        class WOONETEVIA {

            var $plugin_dir;
            var $plugin_url;

            var $gateways_class = array();

            public function __clone() {
                _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), '2.1' );
            }


            public function __wakeup() {
                _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), '2.1' );
            }

            /**
             * Main constructor
             **/
            function __construct() {

                //setup proper directories
                $this->plugin_dir =  dirname( __FILE__ ) . '/';
                $this->plugin_url =  str_replace( array( 'http:', 'https:' ), '', plugins_url( '', __FILE__ ) ) . '/';

                register_activation_hook( $this->plugin_dir . 'woo-netevia.php', array( &$this, 'activation' ) );

                add_filter( 'woocommerce_payment_gateways',  array( $this, 'add_gateways' ) );
            }

            /**
             * Run Activated funtions
             */
            function activation() {
                add_option( 'woonetevia_ver', WOONETEVIA_VER );
            }

            /**
             * Add gateways to WC
             *
             * @param  array $methods
             * @return array of methods
             */
            public function add_gateways( $methods ) {
                include_once( $this->plugin_dir . 'class-netevia.php' );

                $methods[] = 'WCNeteviaGateway';

                return $methods;
            }

            //end class
        }

    }

    /**
     * function to initiate plugin
     */
    function init_woonetevia() {

        //checking for version required
        if ( ! version_compare( netevia_get_wc_version(), '2.6.0', '>=' ) ) {
            add_action( 'admin_notices', 'woonetevia_rec_ver_notice', 5 );
            function woonetevia_rec_ver_notice() {
                if ( current_user_can( 'install_plugins' ) )
                    echo '<div class="error fade"><p>Sorry, but for this version of <b>Woo Netevia</b> is required version of the <b>WooCommerce</b> not lower than <b>2.6.0</b>. <br />Please update <b>WooCommerce</b> to latest version or install previous versions of <b>Woo Netevia</b>.</span></p></div>';
            }

        } else {
            $GLOBALS['woonetevia'] = new WOONETEVIA();
        }
    }

    function netevia_get_wc_version() {
        if ( defined( 'WC_VERSION' ) && WC_VERSION )
            return WC_VERSION;
        if ( defined( 'WOOCOMMERCE_VERSION' ) && WOOCOMMERCE_VERSION )
            return WOOCOMMERCE_VERSION;
        return null;
    }

    add_action( 'plugins_loaded', 'init_woonetevia' );
}
