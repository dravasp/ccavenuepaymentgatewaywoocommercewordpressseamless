<?php
/**
 * Installation and Database Setup
 *
 * @package WooCommerce_CCAvenue_Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_CCAvenue_Payments_Install {
    
    /**
     * Plugin version for database updates
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Install plugin
     */
    public static function install() {
        self::create_tables();
        self::create_options();
        self::update_db_version();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'ccavenue_transactions';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            transaction_ref varchar(100) NOT NULL,
            amount decimal(15,2) NOT NULL,
            currency varchar(3) NOT NULL,
            payment_method varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            tracking_id varchar(100) DEFAULT NULL,
            bank_ref_no varchar(100) DEFAULT NULL,
            failure_message text DEFAULT NULL,
            retry_count int(11) DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY transaction_ref (transaction_ref),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
    
    /**
     * Create default options
     */
    private static function create_options() {
        // Set default currency to INR for CCAvenue
        if ( ! get_option( 'wc_ccavenue_default_currency' ) ) {
            update_option( 'wc_ccavenue_default_currency', 'INR' );
        }
        
        // Set default payment methods
        if ( ! get_option( 'wc_ccavenue_enabled_methods' ) ) {
            update_option( 'wc_ccavenue_enabled_methods', array( 'cards', 'netbanking', 'upi', 'wallet' ) );
        }
        
        // Set default retry settings
        if ( ! get_option( 'wc_ccavenue_retry_enabled' ) ) {
            update_option( 'wc_ccavenue_retry_enabled', 'yes' );
        }
        
        if ( ! get_option( 'wc_ccavenue_max_retries' ) ) {
            update_option( 'wc_ccavenue_max_retries', 5 );
        }
    }
    
    /**
     * Update database version
     */
    private static function update_db_version() {
        update_option( 'wc_ccavenue_db_version', self::DB_VERSION );
    }
    
    /**
     * Check if database needs update
     */
    public static function needs_update() {
        $current_version = get_option( 'wc_ccavenue_db_version', '0' );
        return version_compare( $current_version, self::DB_VERSION, '<' );
    }
    
    /**
     * Update database if needed
     */
    public static function update() {
        if ( self::needs_update() ) {
            self::install();
        }
    }
    
    /**
     * Create transaction log
     */
    public static function log_transaction( $order_id, $transaction_ref, $amount, $currency, $payment_method, $status, $tracking_id = null, $bank_ref_no = null, $failure_message = null, $retry_count = 0 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ccavenue_transactions';
        $current_time = current_time( 'mysql' );
        
        $wpdb->insert( $table_name, array(
            'order_id' => $order_id,
            'transaction_ref' => $transaction_ref,
            'amount' => $amount,
            'currency' => $currency,
            'payment_method' => $payment_method,
            'status' => $status,
            'tracking_id' => $tracking_id,
            'bank_ref_no' => $bank_ref_no,
            'failure_message' => $failure_message,
            'retry_count' => $retry_count,
            'created_at' => $current_time,
            'updated_at' => $current_time
        ) );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update transaction status
     */
    public static function update_transaction( $transaction_ref, $status, $tracking_id = null, $bank_ref_no = null, $failure_message = null ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ccavenue_transactions';
        
        $wpdb->update( $table_name, array(
            'status' => $status,
            'tracking_id' => $tracking_id,
            'bank_ref_no' => $bank_ref_no,
            'failure_message' => $failure_message,
            'updated_at' => current_time( 'mysql' )
        ), array( 'transaction_ref' => $transaction_ref ) );
        
        return $wpdb->rows_affected;
    }
    
    /**
     * Get transaction by reference
     */
    public static function get_transaction( $transaction_ref ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ccavenue_transactions';
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE transaction_ref = %s",
            $transaction_ref
        ) );
    }
    
    /**
     * Get transactions by order ID
     */
    public static function get_order_transactions( $order_id ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ccavenue_transactions';
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %d ORDER BY created_at DESC",
            $order_id
        ) );
    }
    
    /**
     * Clean up old transactions
     */
    public static function cleanup_old_transactions( $days = 30 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ccavenue_transactions';
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-$days days" ) );
        
        return $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s AND status IN ('pending', 'failed')",
            $cutoff_date
        ) );
    }
}
