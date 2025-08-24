<?php
/**
 * CCAvenue Payment Gateway
 *
 * @package WooCommerce_CCAvenue_Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gateway_CCAvenue extends WC_Payment_Gateway {
    
    /**
     * Transaction retry attempts
     */
    const MAX_RETRY_ATTEMPTS = 5;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'ccavenue';
        $this->has_fields         = true;
        $this->method_title       = __( 'CCAvenue Payments', 'woocommerce-ccavenue-payments' );
        $this->method_description = __( 'Accept payments via CCAvenue with voice notifications, dynamic QR codes, and enhanced security.', 'woocommerce-ccavenue-payments' );
        $this->supports           = array( 'products', 'refunds' );
        
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->title                = $this->get_option( 'title' );
        $this->description          = $this->get_option( 'description' );
        $this->merchant_id          = $this->get_option( 'merchant_id' );
        $this->access_code          = $this->get_option( 'access_code' );
        $this->working_key          = $this->get_option( 'working_key' );
        $this->test_mode            = 'yes' === $this->get_option( 'test_mode', 'no' );
        $this->enable_voice_alerts  = 'yes' === $this->get_option( 'enable_voice_alerts', 'no' );
        $this->enable_qr_payments   = 'yes' === $this->get_option( 'enable_qr_payments', 'yes' );
        $this->retry_payments       = 'yes' === $this->get_option( 'retry_payments', 'yes' );
        
        // Set API endpoints
        $this->api_url = $this->test_mode 
            ? 'https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction'
            : 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction';
        
        // Hooks
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_api_wc_gateway_ccavenue', array( $this, 'handle_ccavenue_response' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
    }
    
    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'woocommerce-ccavenue-payments' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable CCAvenue Payments', 'woocommerce-ccavenue-payments' ),
                'default' => 'no'
            ),
            'title' => array(
                'title'       => __( 'Title', 'woocommerce-ccavenue-payments' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-ccavenue-payments' ),
                'default'     => __( 'CCAvenue Payments', 'woocommerce-ccavenue-payments' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'woocommerce-ccavenue-payments' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce-ccavenue-payments' ),
                'default'     => __( 'Pay securely via CCAvenue with credit cards, debit cards, net banking, UPI, and wallets.', 'woocommerce-ccavenue-payments' ),
                'desc_tip'    => true,
            ),
            'merchant_id' => array(
                'title'       => __( 'Merchant ID', 'woocommerce-ccavenue-payments' ),
                'type'        => 'text',
                'description' => __( 'Your CCAvenue Merchant ID', 'woocommerce-ccavenue-payments' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'access_code' => array(
                'title'       => __( 'Access Code', 'woocommerce-ccavenue-payments' ),
                'type'        => 'text',
                'description' => __( 'Your CCAvenue Access Code', 'woocommerce-ccavenue-payments' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'working_key' => array(
                'title'       => __( 'Working Key', 'woocommerce-ccavenue-payments' ),
                'type'        => 'password',
                'description' => __( 'Your CCAvenue Working Key', 'woocommerce-ccavenue-payments' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_mode' => array(
                'title'       => __( 'Test Mode', 'woocommerce-ccavenue-payments' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Test Mode', 'woocommerce-ccavenue-payments' ),
                'default'     => 'no',
                'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-ccavenue-payments' ),
            ),
            'enable_voice_alerts' => array(
                'title'       => __( 'Voice Alerts', 'woocommerce-ccavenue-payments' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Voice Alerts', 'woocommerce-ccavenue-payments' ),
                'default'     => 'no',
                'description' => __( 'Enable voice notifications for successful payments via Alexa and Google Home', 'woocommerce-ccavenue-payments' ),
            ),
            'enable_qr_payments' => array(
                'title'       => __( 'QR Code Payments', 'woocommerce-ccavenue-payments' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable QR Code Payments', 'woocommerce-ccavenue-payments' ),
                'default'     => 'yes',
                'description' => __( 'Enable UPI and UPI Lite payments via dynamic QR codes', 'woocommerce-ccavenue-payments' ),
            ),
            'retry_payments' => array(
                'title'       => __( 'Payment Retries', 'woocommerce-ccavenue-payments' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Payment Retries', 'woocommerce-ccavenue-payments' ),
                'default'     => 'yes',
                'description' => __( 'Automatically retry failed payments up to 5 times before marking as failed', 'woocommerce-ccavenue-payments' ),
            ),
            'payment_methods' => array(
                'title'       => __( 'Enabled Payment Methods', 'woocommerce-ccavenue-payments' ),
                'type'        => 'multiselect',
                'description' => __( 'Select which payment methods to enable', 'woocommerce-ccavenue-payments' ),
                'default'     => array( 'cards', 'netbanking', 'upi', 'wallet', 'qr' ),
                'options'     => array(
                    'cards'      => __( 'Credit/Debit Cards', 'woocommerce-ccavenue-payments' ),
                    'netbanking' => __( 'Net Banking', 'woocommerce-ccavenue-payments' ),
                    'upi'        => __( 'UPI', 'woocommerce-ccavenue-payments' ),
                    'wallet'     => __( 'Mobile Wallets', 'woocommerce-ccavenue-payments' ),
                    'qr'         => __( 'QR Code Payments', 'woocommerce-ccavenue-payments' ),
                ),
                'desc_tip'    => true,
            ),
        );
    }
    
    /**
     * Process payment
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        
        // Generate unique transaction reference
        $transaction_ref = $this->generate_transaction_reference( $order_id );
        
        // Save transaction reference to order
        $order->update_meta_data( '_ccavenue_transaction_ref', $transaction_ref );
        $order->update_meta_data( '_ccavenue_retry_count', 0 );
        $order->save();
        
        // Log transaction
        $this->log_transaction( $order_id, $transaction_ref, 'initiated', 
            array( 'amount' => $order->get_total(), 'currency' => $order->get_currency() ) );
        
        // Return thankyou redirect
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url( true )
        );
    }
    
    /**
     * Receipt page
     */
    public function receipt_page( $order_id ) {
        $order = wc_get_order( $order_id );
        $transaction_ref = $order->get_meta( '_ccavenue_transaction_ref' );
        
        echo '<div class="ccavenue-payment-container">';
        
        if ( $this->enable_qr_payments ) {
            echo $this->get_qr_payment_section( $order, $transaction_ref );
        }
        
        echo $this->get_payment_form( $order, $transaction_ref );
        echo '</div>';
    }
    
    /**
     * Get QR payment section
     */
    private function get_qr_payment_section( $order, $transaction_ref ) {
        $qr_url = WC_CCAvenue_Payments::instance()->qr_generator->generate_qr_code( 
            $order->get_total(), 
            $order->get_currency(),
            $transaction_ref 
        );
        
        ob_start();
        ?>
        <div class="ccavenue-qr-section">
            <h3><?php _e( 'Scan & Pay with UPI', 'woocommerce-ccavenue-payments' ); ?></h3>
            <div class="qr-code-container">
                <img src="<?php echo esc_url( $qr_url ); ?>" alt="<?php esc_attr_e( 'Scan this QR code to pay via UPI', 'woocommerce-ccavenue-payments' ); ?>" />
                <p class="qr-amount"><?php printf( __( 'Amount: %s', 'woocommerce-ccavenue-payments' ), wc_price( $order->get_total() ) ); ?></p>
                <p class="qr-ref"><?php printf( __( 'Reference: %s', 'woocommerce-ccavenue-payments' ), $transaction_ref ); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get payment form
     */
    private function get_payment_form( $order, $transaction_ref ) {
        $merchant_data = $this->prepare_merchant_data( $order, $transaction_ref );
        $encrypted_data = WC_CCAvenue_Payments::instance()->encryption->encrypt( $merchant_data, $this->working_key );
        
        ob_start();
        ?>
        <form method="post" name="redirect" action="<?php echo esc_url( $this->api_url ); ?>">
            <input type="hidden" name="encRequest" value="<?php echo esc_attr( $encrypted_data ); ?>">
            <input type="hidden" name="access_code" value="<?php echo esc_attr( $this->access_code ); ?>">
            
            <div class="payment-methods">
                <h3><?php _e( 'Choose Payment Method', 'woocommerce-ccavenue-payments' ); ?></h3>
                
                <?php if ( in_array( 'cards', $this->get_option( 'payment_methods', array() ) ) ) : ?>
                <div class="payment-method">
                    <input type="radio" id="payment_cards" name="payment_method" value="cards" checked>
                    <label for="payment_cards"><?php _e( 'Credit/Debit Cards', 'woocommerce-ccavenue-payments' ); ?></label>
                </div>
                <?php endif; ?>
                
                <?php if ( in_array( 'netbanking', $this->get_option( 'payment_methods', array() ) ) ) : ?>
                <div class="payment-method">
                    <input type="radio" id="payment_netbanking" name="payment_method" value="netbanking">
                    <label for
