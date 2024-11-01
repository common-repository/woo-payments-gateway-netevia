<?php
/**
 * WCNeteviaGateway
 */
if ( !class_exists( 'WCNeteviaGateway' ) ) {

    class WCNeteviaGateway extends WC_Payment_Gateway_CC {

        /**
         * Constructor
         */
        public function __construct() {
            $this->method_title = 'Netevia';
            $this->id           = 'netevia';
            $this->has_fields   = false;
            $this->icon         = plugins_url( '/images/logo.png', __FILE__ );

            // Load the settings.
            $this->init_settings();

            $this->mode = $this->settings[ 'sandbox' ] == 'yes' ? 'sandbox' : 'production';

            // Load the form fields.
            $this->NG_init_form_fields();

            $this->settings[ '__' ] = WC()->api_request_url( 'WCNeteviaGateway' );

            // Define user set variables
            $this->title            = trim( $this->settings['title'] );
            $this->description      = trim( $this->get_option( 'description' ) );
			$this->method_description      = trim( $this->get_option( 'description' ) );

            if ( 'sandbox' == $this->mode )    {
                $this->appid = trim( $this->settings['appid_sandbox'] );
            } else {
                $this->appid = trim( $this->settings['appid'] );
            }
            if ( 'sandbox' == $this->mode )    {
                $this->api_key = trim( $this->settings['api_key_sandbox'] );
            } else {
                $this->api_key = trim( $this->settings['api_key'] );
            }

            add_action( 'woocommerce_api_wcneteviagateway', array( $this, '_ipn' ) );

            if ( 'sandbox' == $this->mode )    {
                $this->api_endpoint = trim( $this->settings['api_endpoint_sandbox'] );
            } else {
                $this->api_endpoint = trim( $this->settings['api_endpoint'] );
            }

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            add_action( 'woocommerce_api_wcneteviagateway', array( $this, '_ipn' ) );

            add_action( 'woocommerce_receipt_netevia', array( $this, 'receipt_page' ) );
        }

        /**
         * init_form_fields function.
         *
         * @access public
         * @return void
         */
        function NG_init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => __( 'Enable/Disable', WOONETEVIATEXTDOMAIN ),
                    'label'       => __( 'Enable Netevia', WOONETEVIATEXTDOMAIN ),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'sandbox' => array(
                    'title'       => __( 'Use Sandbox', WOONETEVIATEXTDOMAIN ),
                    'label'       => __( 'Enable sandbox mode during testing and development - live payments will not be taken if enabled.', WOONETEVIATEXTDOMAIN ),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => __( 'Title', WOONETEVIATEXTDOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Payment method title that the customer will see on your website.', WOONETEVIATEXTDOMAIN ),
                    'default'     => __( 'Netevia', WOONETEVIATEXTDOMAIN ),
                    'desc_tip'    => true
                ),
                'description' => array(
                    'title'       => __( 'Description', WOONETEVIATEXTDOMAIN ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', WOONETEVIATEXTDOMAIN ),
                    'default'     => __( 'Pay securely by Credit or Debit card or internet banking through Netevia Secure Servers.', WOONETEVIATEXTDOMAIN ),
                    'desc_tip'    => true,
                ),
                'appid_sandbox' => array(
                    'title'       => __( 'App ID (Sandbox)', WOONETEVIATEXTDOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Obtained from your Netevia account. You can set this key by logging into Netevia.', WOONETEVIATEXTDOMAIN ),
                    'default'     => '',
                    'desc_tip'    => true
                ),
                'api_endpoint_sandbox' => array(
                    'title'       => __( 'API URL (Sandbox)', WOONETEVIATEXTDOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Obtained from your Netevia account. You can set this key by logging into Netevia.', WOONETEVIATEXTDOMAIN ),
                    'default'     => 'https://dashboard.netevia.com/api/transaction/pos/payment',
                    'desc_tip'    => true
                ),
                'api_key_sandbox' => array(
                    'title'       => __( 'API Key (Sandbox)', WOONETEVIATEXTDOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Obtained from your Netevia account. You can set this key by logging into Netevia.', WOONETEVIATEXTDOMAIN ),
                    'default'     => '',
                    'desc_tip'    => true
                ),
                'appid' => array(
                    'title'       => __( 'App ID', WOONETEVIATEXTDOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Obtained from your Netevia account. You can set this key by logging into Netevia.', WOONETEVIATEXTDOMAIN ),
                    'default'     => '',
                    'desc_tip'    => true
                ),
                'api_endpoint' => array(
                    'title'       => __( 'API URL', WOONETEVIATEXTDOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Obtained from your Netevia account. You can set this key by logging into Netevia.', WOONETEVIATEXTDOMAIN ),
                    'default'     => 'https://dashboard.netevia.com/api/transaction/pos/payment',
                    'desc_tip'    => true
                ),
                'api_key' => array(
                    'title'       => __( 'API Key', WOONETEVIATEXTDOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Obtained from your Netevia account. You can set this key by logging into Netevia.', WOONETEVIATEXTDOMAIN ),
                    'default'     => '',
                    'desc_tip'    => true
                ),
            );
        }

        /**
         * Check If The Gateway Is Available For Use
         *
         * @access public
         * @return bool
         */
        function is_available() {
            if ( $this->enabled == "yes" && !empty( $this->appid ) && !empty( $this->api_key ) )
                return true;

            return false;
        }


        /**
         * Payment form on checkout page
         */
        public function payment_fields() {
            $this->form();
        }


        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         */
        function admin_options() {
            ?>

            <script>
                jQuery(document).ready(function($) {
                    var wwoocommerce_netevia_api_key_parent = $("#woocommerce_netevia_api_key").closest('tr');
                    var woocommerce_netevia_api_key_sandbox_parent = $("#woocommerce_netevia_api_key_sandbox").closest('tr');
                    var woocommerce_netevia_api_endpoint_parent = $("#woocommerce_netevia_api_endpoint").closest('tr');
                    var woocommerce_netevia_api_endpoint_sandbox_parent = $("#woocommerce_netevia_api_endpoint_sandbox").closest('tr');
                    var woocommerce_netevia_appid_parent = $("#woocommerce_netevia_appid").closest('tr');
                    var woocommerce_netevia_appid_sandbox_parent = $("#woocommerce_netevia_appid_sandbox").closest('tr');

                    if ($("#woocommerce_netevia_sandbox").attr("checked") == 'checked') {
                        woocommerce_netevia_appid_sandbox_parent.show();
                        woocommerce_netevia_appid_parent.hide();
                        woocommerce_netevia_api_endpoint_sandbox_parent.show();
                        woocommerce_netevia_api_endpoint_parent.hide();
                        woocommerce_netevia_api_key_sandbox_parent.show();
                        wwoocommerce_netevia_api_key_parent.hide();
                    } else {
                        woocommerce_netevia_appid_sandbox_parent.hide();
                        woocommerce_netevia_appid_parent.show();
                        woocommerce_netevia_api_endpoint_sandbox_parent.hide();
                        woocommerce_netevia_api_endpoint_parent.show();
                        woocommerce_netevia_api_key_sandbox_parent.hide();
                        wwoocommerce_netevia_api_key_parent.show();
                    }

                    $('#woocommerce_netevia_sandbox').on('click', function () {
                        if ($("#woocommerce_netevia_sandbox").attr("checked") == 'checked') {
                            woocommerce_netevia_appid_sandbox_parent.show();
                            woocommerce_netevia_appid_parent.hide();
                            woocommerce_netevia_api_endpoint_sandbox_parent.show();
                            woocommerce_netevia_api_endpoint_parent.hide();
                            woocommerce_netevia_api_key_sandbox_parent.show();
                            wwoocommerce_netevia_api_key_parent.hide();
                        } else {
                            woocommerce_netevia_appid_sandbox_parent.hide();
                            woocommerce_netevia_appid_parent.show();
                            woocommerce_netevia_api_endpoint_sandbox_parent.hide();
                            woocommerce_netevia_api_endpoint_parent.show();
                            woocommerce_netevia_api_key_sandbox_parent.hide();
                            wwoocommerce_netevia_api_key_parent.show();
                        }
                    });
                });
            </script>

            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table><!--/.form-table-->
            <?php
        }


        /**
         * Reciept page.
         *
         * Display text and a button to direct the user to WeCard.
         *
         * @since 1.0.0
         */
        function receipt_page( $order_id ) {
            echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Netevia.', WOONETEVIATEXTDOMAIN ) . '</p>';
        }


        /**
         * Process the payment and return the result.
         *
         * @since 1.0.0
         */
        function process_payment( $order_id ) {
            $order = new WC_Order( $order_id );

            $order_data = $order->get_data();
            $order_items = $order->get_items();

            $customer = new WC_Customer( $order_data['customer_id'] );
            $customer_data = $customer->get_data();

            $productArray = array();

            foreach ( $order_items as $key => $item ) {
                $productArray[ $key ][ 'Amount' ] = $item->get_total();
                $productArray[ $key ][ 'Quantity' ] = $item->get_quantity();
                $productArray[ $key ][ 'Description' ] = '';
            }

            if( empty( $_POST[ 'netevia-card-expiry' ] ) && empty( $_POST[ 'netevia-card-number' ] ) && empty( $_POST[ 'netevia-card-cvc' ] ) ) {
                return;
            }

            $neteviaCardExpiry = esc_attr( sanitize_text_field( $_POST[ 'netevia-card-expiry' ] ) );
            $neteviaCardNumber = esc_attr( sanitize_text_field( $_POST[ 'netevia-card-number' ] ) );
            $neteviaCardCvc = esc_attr( sanitize_text_field( $_POST[ 'netevia-card-cvc' ] ) );

            $cardExpiryArray = explode( '/', $neteviaCardExpiry );

            // Construct variables for post
            $params = array(
                'Card' => array(
                    'Number' => $neteviaCardNumber,
                    'ExpirationMonth' => (int)trim( $cardExpiryArray[ 0 ] ),
                    'ExpirationYear' => (int)trim( $cardExpiryArray[ 1 ] ),
                    'Cardholder' => 'sdfs sfsdfsd',
                    'CVV' => $neteviaCardCvc,
                    'Issuer' => ''
                ),
                'Order' => array(
                    'OrderId' => "Order ".$order_id,
                    'Amount' => $order->total,
                    'Currency' => strtoupper( get_woocommerce_currency() ),
                    'Description' => ""
                ),
                'Customer' => array(
                    'FirstName' => $customer_data[ 'first_name' ] != "" ? $customer_data[ 'first_name' ] : $order_data[ 'billing' ][ 'first_name' ],
                    'LastName' => $customer_data[ 'last_name' ] != "" ? $customer_data[ 'last_name' ] : $order_data[ 'billing' ][ 'last_name' ],
                    'Email' => $customer_data[ 'email' ] != "" ? $customer_data[ 'email' ] : $order_data[ 'billing' ][ 'billing_email' ],
                    'IPAddress' => "",
                    'Phone' => $customer_data[ 'billing' ][ 'phone' ] != "" ? $customer_data[ 'billing' ][ 'phone' ] : $order_data[ 'billing' ][ 'phone' ]
                ),
                'ShoppingCard' => array(
                    'Items' => array(
                        $productArray
                    )
                ),
                'BillingAddress' => array(
                    'Country' => $order_data[ 'billing' ][ 'country' ],
                    'StreetAddress' => $order_data[ 'billing' ][ 'address_1' ] . $order_data[ 'billing' ][ 'address_2' ],
                    'ZipCode' => $order_data[ 'billing' ][ 'postcode' ],
                    'State' => $order_data[ 'billing' ][ 'state' ],
                    'City' => $order_data[ 'billing' ][ 'city' ],
                ),
                'ShippingAddress' => array(
                    'Country' => $order_data[ 'shipping' ][ 'country' ],
                    'StreetAddress' => $order_data[ 'shipping' ][ 'address_1' ] . $order_data[ 'shipping' ][ 'address_2' ],
                    'ZipCode' => $order_data[ 'shipping' ][ 'postcode' ],
                    'State' => $order_data[ 'shipping' ][ 'state' ],
                    'City' => $order_data[ 'shipping' ][ 'city' ],
                ),
                'autocapture' => true
            );
            $params2 = json_encode($params);

            $auth = $this->ngAuthorizationHeader( $this->appid, $this->api_key, $this->api_endpoint, 'POST', $params2 );

            $args = array(
                'timeout'     => 45,
                'headers' => array(
                    'Authorization' => $auth,
                    'Content-Type' => 'application/json',
                    'Content-Encoding' => 'utf-8'
                ),
                'sslverify' => false,
                'sslcertificates' => false,
                'method' => 'POST',
                'body' => $params2,
            );

            $result = wp_remote_post( $this->api_endpoint, $args );

            $resultArray = json_decode( $result['body'] );

            if ( is_object($resultArray) && $resultArray->Message == 'Success' &&  $resultArray->Result == 'Pending') {
                $order->payment_complete( $resultArray->id );

                WC()->cart->empty_cart();

                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url( $order )
                );
            } else {
                wc_add_notice( 'Error: '. $resultArray->Message);
                return;
            }

        }

        /**
         * Get authorization token
         * @param $appID
         * @param $apiKey
         * @param $requestURL
         * @param $method
         * @param $content
         * @return string
         */
        function ngAuthorizationHeader( $appID, $apiKey, $requestURL, $method, $content ) {
            if ( empty( $content ) ) {
                $content = null;
            } else {
                $contentMD5 = md5( $content, true );
                $content = base64_encode( $contentMD5 );
            }

            $nonce = $this->getNGNonce();
            $timestamp = time();
            $hmacSHA256Hash = $this->ngComputeHMACSHA256Hash( $appID, $apiKey, $requestURL, $method, $timestamp, $nonce, $content );
            $authHeader = 'amx ' . $appID . ':' . $hmacSHA256Hash . ':' . $nonce . ':' . $timestamp;

            return $authHeader;
        }

        /**
         * @param $appID
         * @param $apiKey
         * @param $requestURL
         * @param $method
         * @param $timestamp
         * @param $nonce
         * @param $content
         * @return string
         */
        function ngComputeHMACSHA256Hash( $appID, $apiKey, $requestURL, $method, $timestamp, $nonce, $content ) {
            $signatureRawData = $appID . $method . $requestURL . $timestamp . $nonce . $content;
            $signature = utf8_encode( $signatureRawData );
            $hmac = hash_hmac( 'sha256', $signature, $apiKey, true );
            $hmacSHA256Hash = base64_encode( $hmac );

            return $hmacSHA256Hash;
        }

        /**
         * @return mixed|string
         */
        function getNGNonce() {
            $nonce = uniqid( "", true );
            $nonce = str_replace( '.', '', $nonce );

            return $nonce;
        }

    }
}