<?php 
/*
Plugin Name: Aelia Currency Switcher addon for Abandoned Cart Plugin
Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/woocommerce-abandoned-cart-pro
Description: This plugin allows you to capture the customers selected currency for the abandoned cart.
Version: 1.0
Author: Tyche Softwares
Author URI: http://www.tychesoftwares.com/
*/

global $AcfacpdateChecker;
$AcfacpdateChecker = '1.0';

// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
define( 'EDD_SL_STORE_URL_ACFAC', 'http://www.tychesoftwares.com/' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system

// the name of your product. This is the title of your product in EDD and should match the download title in EDD exactly
define( 'EDD_SL_ITEM_NAME_ACFAC', 'Aelia Currency Switcher addon for Abandoned Cart Plugin' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system

if( ! class_exists( 'EDD_ACFAC_WOO_Plugin_Updater' ) ) {
    // load our custom updater if it doesn't already exist
    include( dirname( __FILE__ ) . '/plugin-updates/EDD_ACFAC_WOO_Plugin_Updater.php' );
}

// retrieve our license key from the DB
$license_key = trim( get_option( 'edd_sample_license_key_acfac' ) );
// setup the updater
$edd_updater = new EDD_ACFAC_WOO_Plugin_Updater( EDD_SL_STORE_URL_ACFAC, __FILE__, array(
    'version'   => '1.0',                     // current version number
    'license'   => $license_key,                // license key (used get_option above to retrieve from DB)
    'item_name' => EDD_SL_ITEM_NAME_ACFAC,     // name of this plugin
    'author'    => 'Ashok Rane'                 // author of this plugin
    )
);

if ( ! class_exists( 'Abandoned_Cart_For_Aelia_Currency' ) ) {
    
    class Abandoned_Cart_For_Aelia_Currency {
        public function __construct( ) {
            register_activation_hook( __FILE__     , array( &$this, 'acfac_create_table' ) );
            add_action ( 'acfac_add_data'          , array( &$this, 'acfac_add_abandoned_currency' ),10, 1 );
            add_filter ( 'acfac_change_currency'   , array( &$this, 'acfac_change_abandoned_currency' ),10, 4 );
            add_filter ( 'acfac_get_cart_currency' , array( &$this, 'acfac_get_abandoned_currency' ),10, 2 );
            add_action ( 'woocommerce_init'        , array( &$this, 'acfac_set_currency_from_recovered_cart'), 0);
            add_action ( 'admin_init'              , array( &$this, 'acfac_check_compatibility' ) );
            
            /**
             * Add new tab for the license key.
             */
            if ( ! has_action ('wcap_add_tabs' ) ){
                add_action ( 'wcap_add_tabs'       , array( &$this, 'acfac_add_tab' ) );
            }
            add_action ( 'admin_init'              , array( &$this, 'acfac_initialize_settings_options' ), 11 );

            add_action ( 'wcap_crm_data'           , array( &$this, 'acfac_display_data' ), 15 );

            /**
             * License key functions.
             */  
            add_action( 'admin_init'               , array( &$this, 'acfac_edd_register_option' ) );
            add_action( 'admin_init'               , array( &$this, 'acfac_edd_deactivate_license' ) );
            add_action( 'admin_init'               , array( &$this, 'acfc_edd_activate_license' ) );
        }

        /**
         * It will new tab in abandoned cart page.
         */
        function acfac_add_tab () {
            $wcap_action           = "";
            if ( isset( $_GET['action'] ) ) {
                $wcap_action = $_GET['action'];
            }
            $acfac_active = "";
            if (  'wcap_crm' == $wcap_action ) {
                $acfac_active = "nav-tab-active";
            }
            ?>
            <a href="admin.php?page=woocommerce_ac_page&action=wcap_crm" class="nav-tab <?php if( isset( $acfac_active ) ) echo $acfac_active; ?>"> <?php _e( 'Addon Settings', 'woocommerce-ac' );?> </a>
            <?php
            
        }

        /**
         * It will add the setting required for the addon.
         */
        function acfac_initialize_settings_options () {
            
            add_settings_section(
                'acfac_general_settings_section',         
                __( 'Aelia Currency Switcher addon for Abandoned Cart Settings', 'woocommerce-ac' ),
                array($this, 'acfac_general_settings_section_callback' ), 
                'acfac_section'     
            );
            
            //Setting section and field for license options
            add_settings_section(
                'acfac_general_license_key_section',
                __( 'Plugin License Options', 'woocommerce-ac' ),
                array( $this, 'wcap_acfac_general_license_key_section_callback' ),
                'acfac_section'
            );
            
            add_settings_field(
                'edd_sample_license_key_acfac',
                __( 'License Key', 'woocommerce-ac' ),
                array( $this, 'wcap_edd_sample_license_key_acfac_callback' ),
                'acfac_section',
                'acfac_general_license_key_section',
                array( __( 'Enter your license key.', 'woocommerce-ac' ) )
            );
             
            add_settings_field(
            'activate_license_key_acfac_woo',
            __( 'Activate License', 'woocommerce-ac' ),
            array( $this, 'wcap_activate_license_key_acfac_woo_callback' ),
            'acfac_section',
            'acfac_general_license_key_section',
            array( __( 'Enter your license key.', 'woocommerce-ac' ) )
            );
                        
            // Finally, we register the fields with WordPress
            
            register_setting(
                'wcap_acfac_setting',
                'edd_sample_license_key_acfac'
            );
        }

        
        function acfac_general_settings_section_callback() {
             
        }

        /**
         * It will display the all settings field.
         */
        function acfac_display_data () {
            ?>
            
            <?php
            /**
             * When we use the bulk action it will allot the action and mode.
             */
            $wcap_action = "";
            /**
             * When we click on the hover link it will take the action.
             */
            if ( '' == $wcap_action && isset( $_GET['action'] ) ) { 
                $wcap_action = $_GET['action'];
            }
            /**
             *  It will add the settings in the New tab.
             */
            if ( 'wcap_crm' == $wcap_action ) {
                ?>
                <form method="post" action="options.php" id="wcap_acfac_form">
                    <?php settings_fields     ( 'wcap_acfac_setting' ); ?>
                    <?php do_settings_sections( 'acfac_section' ); ?>
                    <?php submit_button( 'Save Settings', 'primary', 'wcap-save-acfac-settings' ); ?>
                </form>
                <?php
            }
        }

        /**
         * WP Settings API callback for License plugin option
         */
        function wcap_acfac_general_license_key_section_callback() {
        
        }
        
        /**
         * WP Settings API callback for License key
         */
        function wcap_edd_sample_license_key_acfac_callback( $args ) {
            $edd_sample_license_key_ac_woo_field = get_option( 'edd_sample_license_key_acfac' );
            printf(
            '<input type="text" id="edd_sample_license_key_acfac" name="edd_sample_license_key_acfac" class="regular-text" value="%s" />',
            isset( $edd_sample_license_key_ac_woo_field ) ? esc_attr( $edd_sample_license_key_ac_woo_field ) : ''
                );
                // Here, we'll take the first argument of the array and add it to a label next to the checkbox
                $html = '<label for="edd_sample_license_key_acfac"> '  . $args[0] . '</label>';
                echo $html;
        }
        /**
         * WP Settings API callback for to Activate License key
         */
        function wcap_activate_license_key_acfac_woo_callback() {
        
            $license = get_option( 'edd_sample_license_key_acfac' );
            $status  = get_option( 'edd_sample_license_status_acfac' );
            ?>
            <form method="post" action="options.php">
            <?php if ( false !== $license ) { ?>
                <?php if( $status !== false && $status == 'valid' ) { ?>
                    <span style="color:green;"><?php _e( 'active' ); ?></span>
                    <?php wp_nonce_field( 'edd_sample_nonce' , 'edd_sample_nonce' ); ?>
                    <input type="submit" class="button-secondary" name="edd_acfac_license_deactivate" value="<?php _e( 'Deactivate License' ); ?>"/>
                  <?php } else { ?>
                        <?php 
                        wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); 
                        ?>
                        <input type="submit" class="button-secondary" name="edd_acfac_license_activate" value="Activate License"/>
                    <?php } ?>
            <?php } ?>
            </form>
            <?php 
        }

        /**
         * Illustrates how to deactivate a license key.
         * This will descrease the site count
         */
        function acfac_edd_deactivate_license() {
            // listen for our activate button to be clicked
            if ( isset( $_POST['edd_acfac_license_deactivate'] ) ) {
                // run a quick security check
                if ( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
                    return; // get out if we didn't click the Activate button
                // retrieve the license from the database
                $license = trim( get_option( 'edd_sample_license_key_acfac' ) );
                // data to send in our API request
                $api_params = array(
                    'edd_action'=> 'deactivate_license',
                    'license'   => $license,
                    'item_name' => urlencode( EDD_SL_ITEM_NAME_ACFAC ) // the name of our product in EDD
                );
                // Call the custom API.
                $response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_ACFAC), array( 'timeout' => 15, 'sslverify' => false ) );
                // make sure the response came back okay
                if ( is_wp_error( $response ) )
                    return false;
                // decode the license data
                $license_data = json_decode( wp_remote_retrieve_body( $response ) );
                // $license_data->license will be either "deactivated" or "failed"
                if ( $license_data->license == 'deactivated' )
                    delete_option( 'edd_sample_license_status_acfac' );
            }
        }
        
        /**
         * this illustrates how to check if
         * a license key is still valid
         * the updater does this for you,
         * so this is only needed if you
         * want to do something custom
         */
        function edd_sample_check_license() {
            global $wp_version;
            $license = trim( get_option( 'edd_sample_license_key_acfac' ) );
            $api_params = array(
                'edd_action' => 'check_license',
                'license'    => $license,
                'item_name'  => urlencode( EDD_SL_ITEM_NAME_ACFAC )
            );
            // Call the custom API.
            $response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_ACFAC), array( 'timeout' => 15, 'sslverify' => false ) );
            if ( is_wp_error( $response ) )
                return false;
            $license_data = json_decode( wp_remote_retrieve_body( $response ) );
            if ( $license_data->license == 'valid' ) {
                echo 'valid';
                exit;
                // this license is still valid
            } else {
                echo 'invalid';
                exit;
                // this license is no longer valid
            }
        }
        
        /**
         * Register the license key option
         */
        function acfac_edd_register_option() {
            // creates our settings in the options table
            register_setting( 'edd_sample_license', 'edd_sample_license_key_acfac', array( &$this, 'wcap_acfac_edd_sanitize_license' ) );
        }
         
        function wcap_acfac_edd_sanitize_license( $new ) {
            $old = get_option( 'edd_sample_license_key_acfac' );
            if ( $old && $old != $new ) {
                delete_option( 'edd_sample_license_key_acfac' ); // new license has been entered, so must reactivate
            }
            return $new;
        }

        /**
         * When we click on the activate button it will activate the license
         */
        function acfc_edd_activate_license() {
            // listen for our activate button to be clicked
            if ( isset( $_POST['edd_acfac_license_activate'] ) ) {
                // run a quick security check
                if ( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
                    return; // get out if we didn't click the Activate button
                // retrieve the license from the database
                $license = trim( get_option( 'edd_sample_license_key_acfac' ) );
                // data to send in our API request
                $api_params = array(
                    'edd_action'=> 'activate_license',
                    'license'   => $license,
                    'item_name' => urlencode( EDD_SL_ITEM_NAME_ACFAC ) // the name of our product in EDD
                );
                // Call the custom API.
                $response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_ACFAC), array( 'timeout' => 15, 'sslverify' => false ) );
                // make sure the response came back okay
                if ( is_wp_error( $response ) )
                    return false;
                // decode the license data
                $license_data = json_decode( wp_remote_retrieve_body( $response ) );
                // $license_data->license will be either "active" or "inactive"
                update_option( 'edd_sample_license_status_acfac', $license_data->license );
            }
        }

        /**
         * Check if WooCommerce is active.
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

        /**
         * This function will load the Client selected curency while client comes from the abandoned cart reminder emails.
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
                        $link_decode = Wcap_Populate_Cart_Of_User::wcap_decrypt_validate( $validate_encoded_string );
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

        /** 
         * This function will give the currency of the selected abandoned cart
         */

        function acfac_get_currency_of_abandoned_cart( $abandoned_sent_id ) {

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

        /**
         * It will return the abandoned cart currency.
         */

        function acfac_get_abandoned_currency ( $acfac_default_currency, $acfac_abandoned_id ) {

            global $wpdb;
            $acfc_table_name                 = $wpdb->prefix . "abandoned_cart_aelia_currency";
            $acfac_get_currency_for_cart     = "SELECT acfac_currency FROM $acfc_table_name WHERE abandoned_cart_id = $acfac_abandoned_id ORDER BY `id` desc limit 1";
            $acfac_get_currency_for_cart_res = $wpdb->get_results( $acfac_get_currency_for_cart );
            if ( count( $acfac_get_currency_for_cart_res ) > 0 ){
                $acfac_default_currency = $acfac_get_currency_for_cart_res[0]->acfac_currency;
                return $acfac_default_currency;
            }
                
            return $acfac_default_currency;
        }

        /** 
         * This function will create the table for storing the Aelia currency.
         */

        function acfac_create_table () {
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

        /** 
         * This function will insert the selected Currency of the Aelia plugin
         */

        function acfac_add_abandoned_currency ( $acfac_abandoned_id ) {
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

        /** 
         * This function will change the currency symbol on the order details, email & abandoned orders tab.
         */
        function acfac_change_abandoned_currency ( $acfac_default_currency, $acfac_abandoned_id,  $abandoned_total, $is_ajax ) {
            
            global $wpdb;
            $acfc_table_name                 = $wpdb->prefix . "abandoned_cart_aelia_currency";

            $acfac_get_currency_for_cart     = "SELECT acfac_currency FROM $acfc_table_name WHERE abandoned_cart_id = $acfac_abandoned_id ORDER BY `id` desc limit 1";
            $acfac_get_currency_for_cart_res = $wpdb->get_results( $acfac_get_currency_for_cart );

            $acfac_aelia_settings = get_option('wc_aelia_currency_switcher');

            if ( count( $acfac_get_currency_for_cart_res ) > 0 ){
                $aelia_cur = $acfac_get_currency_for_cart_res[0]->acfac_currency;
            }else{
                $aelia_cur = get_option('woocommerce_currency');
            }

            $acfac_currency_position = $acfac_aelia_settings ['exchange_rates'][$aelia_cur]['symbol_position'];
            $acfac_format = '%1$s%2$s';
            switch ( $acfac_currency_position ) {
                case 'left' :
                    $acfac_format = '%1$s%2$s';
                break;
                case 'right' :
                    $acfac_format = '%2$s%1$s';
                break;
                case 'left_space' :
                    $acfac_format = '%1$s&nbsp;%2$s';
                break;
                case 'right_space' :
                    $acfac_format = '%2$s&nbsp;%1$s';
                break;
            }
            if ( count( $acfac_get_currency_for_cart_res ) > 0 ) {

                $aelia_cur = $acfac_get_currency_for_cart_res[0]->acfac_currency;

                $acfac_change_currency = array(
                    'ex_tax_label'       => false,
                    'currency'           => $aelia_cur,
                    'decimal_separator'  => $acfac_aelia_settings ['exchange_rates'][$aelia_cur]['decimal_separator'],
                    'thousand_separator' => $acfac_aelia_settings ['exchange_rates'][$aelia_cur]['thousand_separator'],
                    'decimals'           => $acfac_aelia_settings ['exchange_rates'][$aelia_cur]['decimals'],
                    'price_format'       => $acfac_format
                ) ;
                $acfac_default_currency = wc_price ( $abandoned_total, $acfac_change_currency );

            } else {
                $acfac_change_currency = array(
                    'ex_tax_label'       => false,
                    'currency'           => get_option('woocommerce_currency'),
                    'decimal_separator'  => $acfac_aelia_settings ['exchange_rates'][$aelia_cur]['decimal_separator'],
                    'thousand_separator' => $acfac_aelia_settings ['exchange_rates'][$aelia_cur]['thousand_separator'],
                    'decimals'           => $acfac_aelia_settings ['exchange_rates'][$aelia_cur]['decimals'],
                    'price_format'       => $acfac_format
                ) ;
                $acfac_default_currency = wc_price ( $abandoned_total, $acfac_change_currency );
            }
            return $acfac_default_currency;
        }
    }
}

$acfac = new Abandoned_Cart_For_Aelia_Currency(  );
?>