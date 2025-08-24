<?php
/**
 * Plugin Name: WooCommerce CCAvenue Payments
 * Plugin URI: https://ccavenue.com/woocommerce-ccavenue-payments
 * Description: Advanced CCAvenue payment gateway integration with voice notifications, dynamic QR codes, and enhanced security features.
 * Version: 1.0.0
 * Author: WE SKY PRINT LLP
 * Author URI: https://weskyprint.com
 * Text Domain: woocommerce-ccavenue-payments
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 7.0
 *
 * @package WooCommerce_CCAvenue_Payments
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'WC_CCAVENUE_PAYMENTS_VERSION', '1.0.0' );
define( 'WC_CCAVENUE_PAYMENTS_PLUGIN_FILE', __FILE__ );
define( 'WC_CCAVENUE_PAYMENTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_CCAVENUE_PAYMENTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_CCAVENUE_PAYMENTS_ASSETS_URL', WC_CCAVENUE_PAYMENTS_PLUGIN_URL . 'assets/' );

// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'admin_notices', 'wc_ccavenue_payments_woocommerce_missing_notice' );
    return;
}

/**
 * WooCommerce missing notice
 */
function wc_ccavenue_payments_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e( 'WooCommerce CCAvenue Payments requires WooCommerce to be installed and active.', 'woocommerce-ccavenue-payments' ); ?></p>
    </div>
    <?php
}

// Include all required files
require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-payments.php';
require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-gateway-ccavenue.php';
require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-encryption.php';
require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-webhook-handler.php';
require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-voice-notifications.php';
require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-qr-generator.php';
require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-payments-install.php';
require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-payments-admin.php';

/**
 * Initialize the plugin
 */
function wc_ccavenue_payments_init() {
    return WC_CCAvenue_Payments::instance();
}

// Initialize the plugin
add_action( 'plugins_loaded', 'wc_ccavenue_payments_init' );

// Register activation and deactivation hooks
register_activation_hook( __FILE__, 'wc_ccavenue_payments_activate' );
register_deactivation_hook( __FILE__, 'wc_ccavenue_payments_deactivate' );

/**
 * Plugin activation
 */
function wc_ccavenue_payments_activate() {
    // Create necessary database tables
    require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-payments-install.php';
    WC_CCAvenue_Payments_Install::install();
    
    // Schedule cron jobs
    if ( ! wp_next_scheduled( 'wc_ccavenue_payments_daily_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'wc_ccavenue_payments_daily_cleanup' );
    }
}

/**
 * Plugin deactivation
 */
function wc_ccavenue_payments_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook( 'wc_ccavenue_payments_daily_cleanup' );
}
