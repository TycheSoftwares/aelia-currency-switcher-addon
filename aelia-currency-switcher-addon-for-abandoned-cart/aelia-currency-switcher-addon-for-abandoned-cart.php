<?php 
/*
Plugin Name: Aelia Currency Switcher addon for Abandoned Cart
Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/woocommerce-abandoned-cart-pro
Description: This plugin allows you to capture the customers selected currency for the abandoned cart.
Version: 1.0
Author: Tyche Softwares
Author URI: http://www.tychesoftwares.com/
*/

register_uninstall_hook( __FILE__, 'acfac_delete_data' );

function acfac_delete_data() {
    
    global $wpdb;

    $table_name = $wpdb->prefix . "abandoned_cart_aelia_currency";
    
    $acfac_delete_table= "DROP TABLE " . $table_name ;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $wpdb->get_results( $acfac_delete_table );
}


if ( ! class_exists( 'Abandoned_Cart_For_Aelia_Currency' ) ) {
    
    class Abandoned_Cart_For_Aelia_Currency {
        public function __construct( ) {
            register_activation_hook( __FILE__   , array( &$this, 'acfac_create_table' ) );
            add_action ( 'acfac_add_data'        , array( &$this, 'acfac_add_abandoned_currency' ),10, 1 );
            add_filter ( 'acfac_change_currency' , array( &$this, 'acfac_change_abandoned_currency' ),10, 3 );
            add_action ( 'woocommerce_init'      , array( &$this, 'acfac_set_currency_from_recovered_cart'), 0);
            add_action ( 'admin_init'            , array( &$this, 'acfac_check_compatibility' ) );
        }

        /**
         * Check if WooCommerce is active. For branch again
         */   
        public static function acfac_check_wcap_installed() {
        
            if ( class_exists( 'woocommerce_abandon_cart' ) ) {
                return true;
            } else {
                return false;
            }
        }

        /**
         * Ensure that the currency addon is deactivated when Abandoned cart plguin
         * is deactivated.
         */
        public static function acfac_check_compatibility() {
                
            if ( ! self::acfac_check_wcap_installed() ) {
                    
                if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
                    deactivate_plugins( plugin_basename( __FILE__ ) );
                        
                    add_action( 'admin_notices', array( 'Abandoned_Cart_For_Aelia_Currency', 'acfac_disabled_notice' ) );
                    if ( isset( $_GET['activate'] ) ) {
                        unset( $_GET['activate'] );
                    }
                        
                }
                    
            }
        }

        /**
         * Display a notice in the admin Plugins page if the Currecny addon is
         * activated while Abandoned cart plguin is deactivated.
         */
        public static function acfac_disabled_notice() {
                
            $class = 'notice notice-error';
            $message = __( 'Aelia Currency Switcher addon for Abandoned Cart requires Abandoned Cart Pro for WooCommerce installed and activate.', 'woocommerce-ac' );
                
            printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
                
        }

        /*
        This function will load the Client selected curency while client comes from the abandoned cart reminder emails.
        */
        function acfac_set_currency_from_recovered_cart() {
            // User explicitly selected a currency, we should not do anything
            if(!empty($_POST['aelia_cs_currency'])) {
                return;
            }

            // Only change the currency on the frontend
            if( !is_admin() || defined('DOING_AJAX') ) {
                // If the user comes from a “recover cart” link, take the currency from
                // the stored “abandoned cart” data
                $track_link = '';
                if ( isset( $_GET['wacp_action'] ) ){ 
                    $track_link = $_GET['wacp_action'];
                }
            
                if ( $track_link == 'track_links' ) {
                     
                    global $wpdb;
                    
                    $validate_server_string  = rawurldecode ( $_GET ['validate'] );
                    $validate_server_string = str_replace ( " " , "+", $validate_server_string);
                    $validate_encoded_string = $validate_server_string;
                    
                    $link_decode_test = base64_decode( $validate_encoded_string );
                    
                    if ( preg_match( '/&url=/', $link_decode_test ) ) { // it will check if any old email have open the link
                        $link_decode = $link_decode_test;
                    } else {
                        $link_decode = woocommerce_abandon_cart::wcap_decrypt_validate( $validate_encoded_string );
                    }
                    
                    if ( !preg_match( '/&url=/', $link_decode ) ) { // This will decrypt more security
                        $cryptKey    = get_option( 'ac_security_key' );
                        
                        $link_decode = Wcap_Aes_Ctr::decrypt( $validate_encoded_string, $cryptKey, 256 );
                    }
                    
                    $email_sent_id   = 0;
                    
                    $sent_email_id_pos          = strpos( $link_decode, '&' );
                    $email_sent_id              = substr( $link_decode , 0, $sent_email_id_pos );
                    $_POST['aelia_cs_currency'] = Abandoned_Cart_For_Aelia_Currency::acfac_get_currency_of_abandoned_cart( $email_sent_id );
                }
            }
        }

        /*
        This function will give the currency of the selected abandoned cart
        */

        function acfac_get_currency_of_abandoned_cart( $abandoned_sent_id ){

            global $wpdb;
            $wcap_email_sent_table_name       = $wpdb->prefix . "ac_sent_history"; 
            $acfac_get_abandoned_order_id     = "SELECT abandoned_order_id FROM $wcap_email_sent_table_name WHERE id = $abandoned_sent_id ";
            $acfac_get_abandoned_order_id_res = $wpdb->get_results( $acfac_get_abandoned_order_id );

            if ( !empty( $acfac_get_abandoned_order_id_res ) ){
                $acfac_abandoned_id = $acfac_get_abandoned_order_id_res[0]->abandoned_order_id;

                $acfc_table_name                 = $wpdb->prefix . "abandoned_cart_aelia_currency";
                $acfac_get_currency_for_cart     = "SELECT acfac_currency FROM $acfc_table_name WHERE abandoned_cart_id = $acfac_abandoned_id ORDER BY `id` desc limit 1";
                $acfac_get_currency_for_cart_res = $wpdb->get_results( $acfac_get_currency_for_cart );

                $selected_currency = $acfac_get_currency_for_cart_res[0]->acfac_currency;
            }
            return $selected_currency;
        }

        /*
        This function will create the table for storing the Aelia currency.
        */

        function acfac_create_table (){
            global $wpdb;
            
            $wcap_collate = '';
            if ( $wpdb->has_cap( 'collation' ) ) {
                $wcap_collate = $wpdb->get_charset_collate();
            }
            $table_name = $wpdb->prefix . "abandoned_cart_aelia_currency";

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `abandoned_cart_id` int(11) COLLATE utf8_unicode_ci NOT NULL,
                    `acfac_currency` text COLLATE utf8_unicode_ci NOT NULL,
                    `date_time` TIMESTAMP on update CURRENT_TIMESTAMP COLLATE utf8_unicode_ci NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                    ) $wcap_collate AUTO_INCREMENT=1 ";           
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            $wpdb->query( $sql ); 
        }

        /*
        This function will insert the selected Currency of the Aelia plugin
        */

        function acfac_add_abandoned_currency ( $acfac_abandoned_id ){
            global $wpdb;

            $acfac_currencey = get_woocommerce_currency(); 
            $acfc_table_name = $wpdb->prefix . "abandoned_cart_aelia_currency";

            $acfac_get_currency_for_cart     = "SELECT  acfac_currency FROM $acfc_table_name WHERE abandoned_cart_id = $acfac_abandoned_id";
            $acfac_get_currency_for_cart_res = $wpdb->get_results( $acfac_get_currency_for_cart );

            if ( !empty( $acfac_get_currency_for_cart_res ) ){
                $wpdb->update( $acfc_table_name,
                        array( 'acfac_currency'    => $acfac_currencey ),
                        array( 'abandoned_cart_id' => $acfac_abandoned_id )
                );
            }else{

                $wpdb->insert( $acfc_table_name, array(
                    'abandoned_cart_id' => $acfac_abandoned_id,
                    'acfac_currency'    => $acfac_currencey
                ));
            }
        }

        /*
        This function will change the currency symbol on the order details, email & abandoned orders tab.
        */
        function acfac_change_abandoned_currency ( $acfac_default_currency, $acfac_abandoned_id,  $abandoned_total ){
            
            global $wpdb;
            $acfc_table_name                 = $wpdb->prefix . "abandoned_cart_aelia_currency";

            $acfac_get_currency_for_cart     = "SELECT acfac_currency FROM $acfc_table_name WHERE abandoned_cart_id = $acfac_abandoned_id ORDER BY `id` desc limit 1";
            $acfac_get_currency_for_cart_res = $wpdb->get_results( $acfac_get_currency_for_cart );

            if ( !empty( $acfac_get_currency_for_cart_res ) ){

                $acfac_change_currency = array(
                    'ex_tax_label'       => false,
                    'currency'           => $acfac_get_currency_for_cart_res[0]->acfac_currency,
                    'decimal_separator'  => wc_get_price_decimal_separator(),
                    'thousand_separator' => wc_get_price_thousand_separator(),
                    'decimals'           => wc_get_price_decimals(),
                    'price_format'       => get_woocommerce_price_format()
                ) ;
                $acfac_default_currency = wc_price ( $abandoned_total, $acfac_change_currency );
            }
            return $acfac_default_currency;
        }
    }
}

$acfac = new Abandoned_Cart_For_Aelia_Currency(  );
?>