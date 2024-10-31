<?php
/**
 * Plugin Name:             Pay with ATH Movil (WooCommerce payment gateway)
 * Description:             A Woocommerce payment gateway for ATH Movil.
 * Version:                 1.2.2
 * Requires at least:       4.4
 * Requires PHP:            7.0
 * Author:                  Softech Corporation
 * Author URI:              https://softechpr.com
 * License:                 GPLv3
 * License URI:             http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce fallback notice.
 *
 * @since 4.1.2
 * @return string
 */
function woocommerce_athmovil_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'ATH Movil requires WooCommerce to be installed and active. You can download %s here.', 'pay-with-athmovil' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * Add a notice to the admin telling the user to checkout the Awesome checkout component that integrates well with the ath movil gateway
 */
function awesome_checkout_wc_notice() {
    $uid = get_current_user_id();
    if ( get_user_meta( $uid, 'athm_awesome_notice_dismissed' ) ) 
        return;

    ?>
    <div class="notice notice-info woocommerce-message">
        <a class="woocommerce-message-close notice-dismiss" href="?athm-awesome-dismissed">Dismiss</a>
        <p>
            <strong>Want a better checkout experience?</strong> ATH Movil integrates perfectly with <strong>Awesome Checkout</strong> component. You can download <a href="https://wordpress.org/plugins/awesome-wc/" target="_blank">Awesome for WooCommerce</a> here.
        </p>
    </div>
    <?php
}

function awesome_checkout_wc_notice_dismissed() {
    $uid = get_current_user_id();
    if( isset( $_GET['athm-awesome-dismissed'] ) )
        add_user_meta( $uid, 'athm_awesome_notice_dismissed', 'true', true );
}

add_action( 'plugins_loaded', 'woocommerce_gateway_athmovil_init' );
add_action( 'deactivated_plugin', 'woocommerce_gateway_athmovil_deactivate', 10, 2 );

function woocommerce_gateway_athmovil_deactivate( $plugin, $network_wide ) {
    var_dump( $plugin );
    if( $plugin != 'pay-with-ath-movil-woocommerce-gateway/woocommerce-gateway-athmovil.php' ) return;
    // delete all dismissed actions for all users when deactivating the plugin
    delete_metadata( 'user', 0, 'athm_awesome_notice_dismissed', '', true );
}

function woocommerce_gateway_athmovil_init() {

    if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_athmovil_missing_wc_notice' );
		return;
    }

    add_action( 'admin_notices', 'awesome_checkout_wc_notice' );
    add_action( 'admin_init', 'awesome_checkout_wc_notice_dismissed' );
    
    if ( ! class_exists( 'WC_Gateway_ATHMovil' ) ) :
		/**
		 * Required minimums and constants
		 */
		define( 'WC_ATHMOVIL_VERSION', '1.2.0' );
		define( 'WC_ATHMOVIL_MIN_PHP_VER', '5.6.0' );
		define( 'WC_ATHMOVIL_MIN_WC_VER', '2.6.0' );
        define( 'WC_ATHMOVIL_API_VER', '4.0.0' );
		define( 'WC_ATHMOVIL_MAIN_FILE', __FILE__ );
		define( 'WC_ATHMOVIL_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		define( 'WC_ATHMOVIL_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

		class WC_Gateway_ATHMovil extends WC_Payment_Gateway {

            /**
			 * @var Singleton The reference the *Singleton* instance of this class
			 */
            private static $instance;
            private $textDomain;
            private $refundEndpoint = 'https://www.athmovil.com/api/v4/refundTransaction';

            /**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return Singleton The *Singleton* instance.
			 */
			public static function get_instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}
				return self::$instance;
			}

            public function __construct() {
                $this->id                       = 'athmovil';
                $this->icon                     = WC_ATHMOVIL_PLUGIN_URL . '/assets/images/icon.png';
                $this->has_fields               = false;
                $this->method_title             = 'ATH Movil';
                $this->method_description       = 'A payment gateway for ATH Movil';
                $this->textDomain               = 'pay-with-athmovil';

                // gateways can support subscriptions, refunds, saved payment methods,
	            // but in this plugin will only support simple payments
                $this->supports = [
                    'products',
                    'refunds'
                ];

                // Method with all the options fields
                $this->init_form_fields();

                // Load the settings.
                $this->init_settings();
                $this->title = $this->get_option( 'title' );
                $this->description = $this->get_option( 'description' );
                $this->enabled = $this->get_option( 'enabled' );
                $this->testmode = 'yes' === $this->get_option( 'testmode' );
                $this->publishable_key = $this->testmode ? '' : $this->get_option( 'publishable_key' );
                $this->private_key = $this->testmode ? '' : $this->get_option( 'private_key' );
                $this->theme = $this->get_option( 'theme' );
                $this->show_popup_disclaimer = $this->get_option( 'show_popup_disclaimer' );
                $this->popup_disclaimer_text = $this->get_option( 'popup_disclaimer_text' );

                // Initialize text domain
                add_action( 'init', [ $this, 'loadPluginTextdomain' ] );

                // This action hook saves the settings
	            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

                add_filter( 'woocommerce_payment_gateways', [ $this, 'wc_athmovil_add_to_gateways' ] );
                add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

                // Add the settings link to the plugins page
                add_filter('plugin_action_links_'.plugin_basename(__FILE__), [ $this, 'plugin_page_settings_link' ] );

                // Intercept process_checkout call to exit after validation.
			    // add_action( 'woocommerce_after_checkout_validation', [ $this, 'start_checkout_payment' ], 10, 2 );

                // Ajax hooks
                // add_action( 'wp_ajax_athmovil_start_checkout', [ $this, 'start_checkout' ] );
                // add_action( 'wp_ajax_nopriv_athmovil_start_checkout', [ $this, 'start_checkout' ] );
                add_action( 'wp_ajax_athmovil_update_totals', [ $this, 'update_totals' ] );
                add_action( 'wp_ajax_nopriv_athmovil_update_totals', [ $this, 'update_totals' ] );

                add_action( 'wp_ajax_athmovil_validate_checkout', [ $this, 'validate_checkout' ] );
                add_action( 'wp_ajax_nopriv_athmovil_validate_checkout', [ $this, 'validate_checkout' ] );
            }
            
            /**
             * Initialize Gateway Settings Form Fields
             */
            public function init_form_fields() {
                
                $this->form_fields = apply_filters( 'wc_iframe_form_fields', [
                    
                    'enabled' => [
                        'title'   => __( 'Enable/Disable', $this->textDomain ),
                        'type'    => 'checkbox',
                        'label'   => __( 'Enable ATH Movil Payments', $this->textDomain ),
                        'default' => 'yes'
                    ],

                    'title' => [
                        'title'       => __( 'Title', $this->textDomain ),
                        'type'        => 'text',
                        'description' => __( 'This controls the title for the payment method the customer sees during checkout.', $this->textDomain ),
                        'default'     => __( 'ATH Movil', $this->textDomain ),
                        'desc_tip'    => true,
                    ],

                    'description' => [
                        'title'       => __( 'Description', $this->textDomain ),
                        'type'        => 'textarea',
                        'description' => __( 'This controls the description which the user sees during checkout.', $this->textDomain ),
                        'default'     => __( 'Pay with your ATH card.', $this->textDomain ),
                    ],

                    'testmode' => [
                        'title'       => __( 'Test mode', $this->textDomain ),
                        'label'       => __( 'Enable Test Mode', $this->textDomain ),
                        'type'        => 'checkbox',
                        'description' => __( 'Place the payment gateway in test mode.', $this->textDomain ),
                        'default'     => 'yes',
                        'desc_tip'    => true,
                    ],

                    'publishable_key' => [
                        'title'       => __( 'Public token', $this->textDomain ),
                        'type'        => 'text',
                        'default'     => ''
                    ],

                    'private_key' => [
                        'title'       => __( 'Private token', $this->textDomain ),
                        'type'        => 'text',
                        'default'     => '',
                        'description' => __( 'To find your public and private tokens open the ATH Movil app and go to <strong>Settings -> API Keys</strong>.', $this->textDomain ),
                    ],

                    'theme'      => [
                        'title'       => __( 'Button theme', $this->textDomain ),
                        'type'        => 'select',
                        'options'     => [
                            'btn'       => 'Default',
                            'btn-light' => 'Light',
                            'btn-dark'  => 'Dark'
                        ],
                        'default'     => 'btn',
                        'description' => __( 'Button display theme.', $this->textDomain ) . ' <ul><li>Default: <br/><img src="'.WC_ATHMOVIL_PLUGIN_URL . '/assets/images/theme_default.png" /></li><li>Light: <br/><img src="'.WC_ATHMOVIL_PLUGIN_URL . '/assets/images/theme_light.png" /></li><li>Dark: <br/><img src="'.WC_ATHMOVIL_PLUGIN_URL . '/assets/images/theme_dark.png" /></li></ul>',
                    ],

                    'show_popup_disclaimer' => [
                        'title'       => __( 'Popup disclaimer', $this->textDomain ),
                        'label'       => __( 'Show popup disclaimer', $this->textDomain ),
                        'type'        => 'checkbox',
                        'description' => __( 'Show popup disclaimer on checkout page.', $this->textDomain ),
                        'default'     => 'yes',
                        // 'desc_tip'    => true,
                    ],

                    'popup_disclaimer_text' => [
                        'title'       => __( 'Popup disclaimer text', $this->textDomain ),
                        'label'       => __( 'Disclaimer text', $this->textDomain ),
                        'type'        => 'textarea',
                        'description' => __( 'Text to be displayed as a disclaimer.', $this->textDomain ),
                        'default'     => "IMPORTANT: ATH Movil uses a popup windows. Make sure you don't use a popup blocker or you won't be able to make payments.",
                        // 'desc_tip'    => true,
                    ],
                 ] );
            }

            public function process_admin_options() {
                $fieldPrefix = 'woocommerce_' . $this->id . '_';

                if ( !isset( $_POST[ $fieldPrefix. 'title'] ) || empty( $_POST[ $fieldPrefix. 'title'] ) ) {
                    WC_Admin_Settings::add_error( 'Error: ' . __( 'You must provide a gateway title', $this->textDomain ) );
                    return false;
                }

                if ( isset( $_POST[ $fieldPrefix . 'testmode'] ) && $_POST[ $fieldPrefix . 'testmode'] == false && empty( $_POST[ $fieldPrefix . 'publishable_key'] ) ) {
                    WC_Admin_Settings::add_error( 'Error: ' . __( 'You must provide a Public token', $this->textDomain ) );
                    return false;
                }

                if ( isset( $_POST[ $fieldPrefix . 'testmode'] ) && $_POST[ $fieldPrefix . 'testmode'] == false && empty( $_POST[ $fieldPrefix . 'private_key'] ) ) {
                    WC_Admin_Settings::add_error( 'Error: ' . __( 'You must provide a Private token', $this->textDomain ) );
                    return false;
                }

                parent::process_admin_options();
            }

            public function payment_scripts() {

                if ( !is_cart() && !is_checkout() && !isset( $_GET['pay_for_order'] ) ) {
                    return;
                }

                if ( 'no' === $this->enabled ) {
                    return;
                }

                // do not work with card details without SSL unless your website is in a test mode
                // if ( !$this->testmode && !is_ssl() ) {
                //     return;
                // }

                WC()->cart->calculate_totals();
                $cart_content = [];
                foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                    $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

                    if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                        $citems = [];

                        $citems = $cart_item;
                        $product = wc_get_product( $citems['product_id'] );

                        $cart_content[] = [
                            'name'          => $product->get_title(),
                            'quantity'      => $citems['quantity'],
                            'price'         => $product->get_price(),
                            'tax'           => $citems['line_tax'],
                        ];
                    }
                }

                wp_register_script('wc-ath-movil', WC_ATHMOVIL_PLUGIN_URL . '/assets/scripts/athmovil-button.min.js', false, false, true); 

                wp_localize_script('wc-ath-movil', 'ATHM_Checkout', [
                    'env'                   => $this->testmode ? 'sandbox' : 'production',
                    'publicToken'           => $this->testmode ? 'sandboxtoken01875617264' : $this->publishable_key,
                    'ready'                 => false,
                    'timeout'               => 600,
                    'theme'                 => $this->theme,
                    'lang'                  => substr( get_locale(), 0, 2 ) != 'en' && substr( get_locale(), 0, 2 ) != 'es' ? 'en' : substr( get_locale(), 0, 2 ), 
                    'total'                 => WC()->cart->get_totals()['subtotal'] + WC()->cart->get_totals()['shipping_total'] + WC()->cart->get_totals()['fee_total'] + WC()->cart->get_totals()['total_tax'] - WC()->cart->get_totals()['discount_total'],
                    'tax'                   => wc_format_decimal( WC()->cart->get_totals()['total_tax'] ),
                    'subtotal'              => WC()->cart->get_totals()['subtotal'] + WC()->cart->get_totals()['shipping_total'] + WC()->cart->get_totals()['fee_total'] - WC()->cart->get_totals()['discount_total'],
                    'items'                 => $cart_content,
                    'scripBaseUrl'          => WC_ATHMOVIL_PLUGIN_URL . '/assets/scripts/athmovil.base.js',
                    'scriptUrl'             => WC_ATHMOVIL_PLUGIN_URL . '/assets/scripts/athmovilV4.js',
                    'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
                    'metadata1'             => 'Shipping total: ' . wc_format_decimal( WC()->cart->get_totals()['shipping_total'] )
                ]);
         
                wp_enqueue_script( 'wc-ath-movil' );
                wp_enqueue_style( 'athmovil-style', WC_ATHMOVIL_PLUGIN_URL . '/assets/styles/athmovil.min.css' );
            }

            public function process_payment( $order_id ) {
    
                $order = wc_get_order( $order_id );
                        
                // Set order as payment complete
                if( isset( $_POST['athm_referenceNumber'] ) && !empty( $_POST['athm_referenceNumber'] ) )  {
                    $order->payment_complete( sanitize_text_field( $_POST['athm_referenceNumber'] ) );
                }
                else {
                    // Mark as on-hold (we're awaiting the payment)
                    $order->update_status( 'pending', __( 'Awaiting', $this->textDomain ) . ' ' . $this->title . ' ' . __( 'payment', $this->textDomain ) );
                }
                     
                // Reduce stock levels
                $order->reduce_order_stock();

                // Remove cart
                WC()->cart->empty_cart();
                        
                // Return thankyou redirect
                return [
                    'result'    => 'success',
                    'redirect'  => $this->get_return_url( $order )
                ];
            }

            public function process_refund( $order_id, $amount = null, $reason = '' ) {
                
                $order = wc_get_order( $order_id );
                if( empty( $order ) ) 
                    return new WP_Error( 'R001','Order not found.' );
                if( empty( $amount ) )
                    return new WP_Error( 'R002','Refund amount cannot be 0.' );
                if( empty( $order->get_transaction_id() ) )
                    return new WP_Error( 'R003','Order cannot be refunded through ' . $this->title . '.' );


                $data = [ 
                    'publicToken'       => $this->publishable_key,
                    'privateToken'      => $this->private_key,
                    'referenceNumber'   => $order->get_transaction_id(),
                    'amount'            => $amount
                ];

                $args = [
                    'method'        => 'POST',
                    'body'          => json_encode( $data ),
                    'timeout'       => 30,
                    'redirection'   => 10,
                    'httpversion'   => '1.0',
                    'blocking'      => true,
                    'headers'       => [
                        'Content-Type'  => 'application/json',
                    ],
                    'sslverify'     => false
                ];

                try {
                    $response = wp_remote_post( $this->refundEndpoint, $args );

                    if ( is_wp_error( $response ) ) 
                        return $response;

                    if ( $response['response']['code'] != 200 )
                        return new WP_Error( 'R004','Something went wrong creating refund.' );
                
                    $response = json_decode( $response['body'], true );
                    if( isset( $response['errorCode'] ) && !empty( $response['errorCode'] ) ) {
                        return new WP_Error( 'R' . $response['errorCode'], $response['description'] );
                    }

                    if( $response['refund']['status'] != 'COMPLETED' )
                        return false;

                    $order->add_order_note( 'Refunded $' . $response['refund']['refundedAmount'] . ' through ' . $this->title . '. Reference #' . $response['refund']['referenceNumber'] . '.' . ( empty( $reason ) ? '' : ' Reason: ' . $reason . '.') );

                    // Change order status if it is a full refund
                    $orderTotal = WC()->cart->get_totals()['subtotal'] + WC()->cart->get_totals()['shipping_total'] + WC()->cart->get_totals()['fee_total'] + WC()->cart->get_totals()['total_tax'] - WC()->cart->get_totals()['discount_total'];
                    if( floatval( $response['refund']['refundedAmount'] ) == floatval( $orderTotal ) )
                        $order->update_status('refunded');

                    return true;
                }
                catch(Exception $ex) {
                    return new WP_Error( 'R005','Something went wrong, please try again later.' );
                }
            }

            function wc_athmovil_add_to_gateways( $gateways ) {
                $gateways[] = 'WC_Gateway_ATHMovil';
                return $gateways;
            }

            function plugin_page_settings_link( $links ) {
                $links[] = '<a href="admin.php?page=wc-settings&tab=checkout&section=athmovil">' . __('Settings') . '</a>';
	            return $links;
            }

            public function start_checkout_payment( $data, $errors = null ) {
                if( isset( $_POST['payment_method'] ) && $_POST['payment_method'] !== $this->id )
                    return;
                
                if ( is_null( $errors ) ) {
                    // Compatibility with WC <3.0: get notices and clear them so they don't re-appear.
                    $error_messages = wc_get_notices( 'error' );
                    wc_clear_notices();
                } else {
                    $error_messages = $errors->get_error_messages();
                }
        
                if ( empty( $error_messages ) ) {
                    // if a payment has been made continue to with the checkout process
                    if( isset( $_POST['athm_referenceNumber'] ) && !empty( $_POST['athm_referenceNumber'] ) )
                        return;
                    else  { // else return to collect the payment
                        wc_clear_notices();
                        wc_add_notice( "athm_valid", 'error');
                    }
                } 
                else {
                    echo json_encode( [ 'messages' => $error_messages ] );
                }
                
                // wp_die(); 
            }

            public function update_totals() {
                echo json_encode([
                    'total'         => WC()->cart->get_totals()['subtotal'] + WC()->cart->get_totals()['shipping_total'] + WC()->cart->get_totals()['fee_total'] + WC()->cart->get_totals()['total_tax'] - WC()->cart->get_totals()['discount_total'],
                    'tax'           => wc_format_decimal( WC()->cart->get_totals()['total_tax'] ),
                    'subtotal'      => WC()->cart->get_totals()['subtotal'] + WC()->cart->get_totals()['shipping_total'] + WC()->cart->get_totals()['fee_total'] - WC()->cart->get_totals()['discount_total'],
                    'shipping'      => wc_format_decimal( WC()->cart->get_totals()['shipping_total'] )
                ]);

                wp_die();
            }

            public function validate_checkout() {
                $checkout = new WC_ATHMovil_Checkout();
                $checkout->validate();

                // echo json_encode( $data );
                wp_die();
            }

            public function popup_disclaimer() {
                if ( 'no' === $this->enabled ) 
                    return;

                if( $this->show_popup_disclaimer === 'yes' ) 
                    echo "<p class='athm_disclaimer' style='margin-top:20px;margin-bottom:10px;'>" . $this->popup_disclaimer_text . "</p>";
            }

            public function loadPluginTextdomain() {
                $domain = $this->textDomain;
                $mo_file = WC_ATHMOVIL_PLUGIN_PATH . '/'  . 'languages/' . $domain . '-' . get_locale() . '.mo';
        
                load_textdomain( $domain, $mo_file ); 
                load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 
            }
		}

        WC_Gateway_ATHMovil::get_instance();

        // add popup disclaimer
        add_action( 'woocommerce_review_order_after_submit', function() { WC_Gateway_ATHMovil::get_instance()->popup_disclaimer(); } );
    endif;
    
    if ( ! class_exists( 'WC_ATHMovil_Checkout' ) ) :
        class WC_ATHMovil_Checkout extends WC_Checkout {
            public function validate() {

                // for compatibility with Awesome WC
                $isAwesome = $_POST['isAwesome'];
                if( $isAwesome === 'true' && function_exists('st_wc_prepare_posted_data') ) {
                    st_wc_prepare_posted_data();
                }

                try {

                    // for compatibility with the Checkout Field Editor for WooCommerce plugin [experimental]
                    $nonce_value = wc_get_var( $_REQUEST['woocommerce-process-checkout-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // @codingStandardsIgnoreLine.

                    if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, 'woocommerce-process_checkout' ) ) {
                        WC()->session->set( 'refresh_totals', true );
                        throw new Exception( __( 'We were unable to process your order, please try again.', 'woocommerce' ) );
                    }

                    wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
                    wc_set_time_limit( 0 );

                    do_action( 'woocommerce_before_checkout_process' );

                    if ( WC()->cart->is_empty() ) {
                        /* translators: %s: shop cart url */
                        throw new Exception( sprintf( __( 'Sorry, your session has expired. <a href="%s" class="wc-backward">Return to shop</a>', 'woocommerce' ), esc_url( wc_get_page_permalink( 'shop' ) ) ) );
                    }

                    do_action( 'woocommerce_checkout_process' ); 
                    // end [experimental]

                    $data       = parent::get_posted_data();
                    $errors     = new WP_Error();

                    // Update session for customer and totals.
                    parent::update_session( $data );

                    // Validate posted data and cart items before proceeding.
                    parent::validate_checkout( $data, $errors );

                    if( $errors->errors && count( $errors->errors ) > 0 ) {
                        foreach ( $errors->errors as $code => $messages ) {
                            $data = $errors->get_error_data( $code );
                            foreach ( $messages as $message ) {
                                wc_add_notice( $message, 'error', $data );
                            }
                        }

                        parent::send_ajax_failure_response();
                    }

                    wp_send_json([
                        'result'    => 'success',
                        'refresh'   => isset( WC()->session->refresh_totals ),
                        'reload'    => isset( WC()->session->reload_checkout )
                    ]);

                } catch ( Exception $e ) {
                    wc_add_notice( $e->getMessage(), 'error' );
                    parent::send_ajax_failure_response();
                }
            }
        }
    endif;
}
