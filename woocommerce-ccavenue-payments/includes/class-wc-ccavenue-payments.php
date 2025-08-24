<?php
/**
 * Main plugin class
 *
 * @package WooCommerce_CCAvenue_Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_CCAvenue_Payments {
    
    /**
     * Plugin instance
     *
     * @var WC_CCAvenue_Payments
     */
    private static $instance;
    
    /**
     * Payment gateway instance
     *
     * @var WC_Gateway_CCAvenue
     */
    public $gateway;
    
    /**
     * Encryption instance
     *
     * @var WC_CCAvenue_Encryption
     */
    public $encryption;
    
    /**
     * Webhook handler instance
     *
     * @var WC_CCAvenue_Webhook_Handler
     */
    public $webhook_handler;
    
    /**
     * Voice notifications instance
     *
     * @var WC_CCAvenue_Voice_Notifications
     */
    public $voice_notifications;
    
    /**
     * QR generator instance
     *
     * @var WC_CCAvenue_QR_Generator
     */
    public $qr_generator;
    
    /**
     * Get instance
     *
     * @return WC_CCAvenue_Payments
     */
    public static function instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->includes();
        $this->init_components();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( WC_CCAVENUE_PAYMENTS_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'wc_ccavenue_payments_daily_cleanup', array( $this, 'daily_cleanup' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-gateway-ccavenue.php';
        require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-encryption.php';
        require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-webhook-handler.php';
        require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-voice-notifications.php';
        require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-qr-generator.php';
        require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-payments-install.php';
        require_once WC_CCAVENUE_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-ccavenue-payments-admin.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        $this->gateway = new WC_Gateway_CCAvenue();
        $this->encryption = new WC_CCAvenue_Encryption();
        $this->webhook_handler = new WC_CCAvenue_Webhook_Handler();
        $this->voice_notifications = new WC_CCAvenue_Voice_Notifications();
        $this->qr_generator = new WC_CCAvenue_QR_Generator();
        
        // Initialize admin
        if ( is_admin() ) {
            new WC_CCAvenue_Payments_Admin();
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain( 'woocommerce-ccavenue-payments', false, dirname( plugin_basename( WC_CCAVENUE_PAYMENTS_PLUGIN_FILE ) ) . '/languages' );
    }
    
    /**
     * Add gateway to WooCommerce
     *
     * @param array $methods Payment methods.
     * @return array
     */
    public function add_gateway( $methods ) {
        $methods[] = 'WC_Gateway_CCAvenue';
        return $methods;
    }
    
    /**
     * Add plugin action links
     *
     * @param array $links Plugin action links.
     * @return array
     */
    public function plugin_action_links( $links ) {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ccavenue' ) . '">' . __( 'Settings', 'woocommerce-ccavenue-payments' ) . '</a>',
            '<a href="https://yourdomain.com/docs" target="_blank">' . __( 'Documentation', 'woocommerce-ccavenue-payments' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }
    
    /**
     * Admin scripts
     */
    public function admin_scripts() {
        if ( isset( $_GET['section'] ) && 'ccavenue' === $_GET['section'] ) {
            wp_enqueue_style( 'wc-ccavenue-admin', WC_CCAVENUE_PAYMENTS_ASSETS_URL . 'css/admin.css', array(), WC_CCAVENUE_PAYMENTS_VERSION );
            wp_enqueue_script( 'wc-ccavenue-admin', WC_CCAVENUE_PAYMENTS_ASSETS_URL . 'js/admin.js', array( 'jquery' ), WC_CCAVENUE_PAYMENTS_VERSION, true );
        }
    }
    
    /**
     * Frontend scripts
     */
    public function frontend_scripts() {
        if ( is_checkout() ) {
            wp_enqueue_style( 'wc-ccavenue-frontend', WC_CCAVENUE_PAYMENTS_ASSETS_URL . 'css/frontend.css', array(), WC_CCAVENUE_PAYMENTS_VERSION );
            wp_enqueue_script( 'wc-ccavenue-frontend', WC_CCAVENUE_PAYMENTS_ASSETS_URL . 'js/frontend.js', array( 'jquery' ), WC_CCAVENUE_PAYMENTS_VERSION, true );
            
            wp_localize_script( 'wc-ccavenue-frontend', 'wc_ccavenue_params', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wc-ccavenue-nonce' ),
                'i18n' => array(
                    'loading' => __( 'Processing...', 'woocommerce-ccavenue-payments' ),
                    'error' => __( 'An error occurred. Please try again.', 'woocommerce-ccavenue-payments' ),
                )
            ) );
        }
    }
    
    /**
     * Daily cleanup task
     */
    public function daily_cleanup() {
        global $wpdb;
        $expiration_time = time() - ( 30 * DAY_IN_SECONDS );
        $wpdb->query( $wpdb->prepare( "
            DELETE FROM {$wpdb->prefix}ccavenue_transactions 
            WHERE created_at < %d AND status IN ('pending', 'temp')
        ", $expiration_time ) );
    }
    
    /**
     * Get gateway instance
     *
     * @return WC_Gateway_CCAvenue
     */
    public function get_gateway() {
        return $this->gateway;
    }
    
    /**
     * Get encryption instance
     *
     * @return WC_CCAvenue_Encryption
     */
    public function get_encryption() {
        return $this->encryption;
    }
    
    /**
     * Get webhook handler instance
     *
     * @return WC_CCAvenue_Webhook_Handler
     */
    public function get_webhook_handler() {
        return $this->webhook_handler;
    }
}
