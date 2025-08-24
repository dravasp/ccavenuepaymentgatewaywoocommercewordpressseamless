<?php
/**
 * Dynamic QR Code Generator for UPI Payments
 *
 * @package WooCommerce_CCAvenue_Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_CCAvenue_QR_Generator {
    
    /**
     * QR code size
     */
    const QR_SIZE = 300;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_ajax_generate_upi_qr', array( $this, 'ajax_generate_qr' ) );
        add_action( 'wp_ajax_nopriv_generate_upi_qr', array( $this, 'ajax_generate_qr' ) );
    }
    
    /**
     * Generate QR code for UPI payment
     */
    public function generate_qr_code( $amount, $currency, $transaction_ref ) {
        $vpa = get_option( 'wc_ccavenue_upi_vpa' );
        $merchant_name = get_option( 'wc_ccavenue_merchant_name' );
        
        if ( ! $vpa || ! $merchant_name ) {
            return $this->get_placeholder_qr();
        }
        
        // Generate UPI payment URL
        $upi_url = $this->generate_upi_url( $vpa, $merchant_name, $amount, $currency, $transaction_ref );
        
        // Generate QR code using Google Charts API
        $qr_url = 'https://chart.googleapis.com/chart?cht=qr&chs=' . self::QR_SIZE . 'x' . self::QR_SIZE . '&chl=' . urlencode( $upi_url );
        
        return $qr_url;
    }
    
    /**
     * Generate UPI payment URL
     */
    private function generate_upi_url( $vpa, $merchant_name, $amount, $currency, $transaction_ref ) {
        $params = array(
            'pa' => $vpa,
            'pn' => urlencode( $merchant_name ),
            'am' => $amount,
            'cu' => $currency,
            'tn' => urlencode( 'Payment for order ' . $transaction_ref )
        );
        
        return 'upi://pay?' . http_build_query( $params );
    }
    
    /**
     * AJAX handler for generating QR codes
     */
    public function ajax_generate_qr() {
        check_ajax_referer( 'wc-ccavenue-nonce', 'nonce' );
        
        $amount = floatval( $_POST['amount'] ?? 0 );
        $currency = sanitize_text_field( $_POST['currency'] ?? 'INR' );
        $transaction_ref = sanitize_text_field( $_POST['transaction_ref'] ?? '' );
        
        if ( ! $amount || ! $transaction_ref ) {
            wp_send_json_error( array( 'error' => 'Invalid parameters' ) );
        }
        
        $qr_url = $this->generate_qr_code( $amount, $currency, $transaction_ref );
        
        wp_send_json_success( array(
            'qr_url' => $qr_url,
            'transaction_ref' => $transaction_ref
        ) );
    }
    
    /**
     * Get placeholder QR code
     */
    private function get_placeholder_qr() {
        return WC_CCAVENUE_PAYMENTS_ASSETS_URL . 'images/qr-placeholder.png';
    }
    
    /**
     * Validate UPI VPA
     */
    public function validate_upi_vpa( $vpa ) {
        // Basic VPA validation - should be in format name@provider
        return preg_match( '/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/', $vpa );
    }
    
    /**
     * Get supported UPI apps
     */
    public function get_supported_upi_apps() {
        return array(
            'google_pay' => array(
                'name' => 'Google Pay',
                'logo' => WC_CCAVENUE_PAYMENTS_ASSETS_URL . 'images/upi/google-pay.png',
                'url' => 'https://pay.google.com/'
            ),
            'phonepe' => array(
                'name' => 'PhonePe',
                'logo' => WC_CCAVENUE_PAYMENTS_ASSETS_URL . 'images/upi/phonepe.png',
                'url' => 'https://www.phonepe.com/'
            ),
            'paytm' => array(
                'name' => 'Paytm',
                'logo' => WC_CCAVENUE_PAYMENTS_ASSETS_URL . 'images/upi/paytm.png',
                'url' => 'https://paytm.com/'
            ),
            'bhim' => array(
                'name' => 'BHIM UPI',
                'logo' => WC_CCAVENUE_PAYMENTS_ASSETS_URL . 'images/upi/bhim.png',
                'url' => 'https://www.npci.org.in/what-we-do/bhim/product-overview'
            )
        );
    }
}
