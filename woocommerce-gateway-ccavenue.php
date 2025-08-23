<?php
/**
 * Plugin Name: CCAvenue Payment Gateway for WooCommerce
 * Description: Accept payments via CCAvenue in WooCommerce.
 * Version: 1.0
 * Author: Dravasp Shroff
 * Author URI: https://dashboard.ccavenue.com/web/genregistration.do?command=navigateGenericRegistration&reseller=CCAV
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
// Include the CCAvenue Gateway class
add_action('plugins_loaded', 'init_ccavenue_gateway_class');
function init_ccavenue_gateway_class() {
    require_once plugin_dir_path(__FILE__) . 'class-wc-gateway-ccavenue.php';
    // Register the gateway
    add_filter('woocommerce_payment_gateways', 'add_ccavenue_gateway');
}
function add_ccavenue_gateway($methods) {
    $methods[] = 'WC_Gateway_CCAvenue';
    return $methods;
}
