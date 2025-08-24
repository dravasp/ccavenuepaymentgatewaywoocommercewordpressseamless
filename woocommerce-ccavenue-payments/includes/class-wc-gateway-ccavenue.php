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
                    <label for="payment_netbanking"><?php _e( 'Net Banking', 'woocommerce-ccavenue-payments' ); ?></label>
                </div>
                <?php endif; ?>
                
                <?php if ( in_array( 'upi', $this->get_option( 'payment_methods', array() ) ) ) : ?>
                <div class="payment-method">
                    <input type="radio" id="payment_upi" name="payment_method" value="upi">
                    <label for="payment_upi"><?php _e( 'UPI', 'woocommerce-ccavenue-payments' ); ?></label>
                </div>
                <?php endif; ?>
                
                <?php if ( in_array( 'wallet', $this->get_option( 'payment_methods', array() ) ) ) : ?>
                <div class="payment-method">
                    <input type="radio" id="payment_wallet" name="payment_method" value="wallet">
                    <label for="payment_wallet"><?php _e( 'Mobile Wallets', 'woocommerce-ccavenue-payments' ); ?></label>
                </div>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="button alt"><?php _e( 'Proceed to Payment', 'woocommerce-ccavenue-payments' ); ?></button>
        </form>
        
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.forms.redirect;
                const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
                
                paymentMethods.forEach(method => {
                    method.addEventListener('change', function() {
                        // Update form action based on selected payment method
                        const methodValue = this.value;
                        let newAction = '<?php echo esc_url( $this->api_url ); ?>';
                        
                        if (methodValue === 'upi') {
                            newAction += '&payment_type=upi';
                        } else if (methodValue === 'wallet') {
                            newAction += '&payment_type=wallet';
                        }
                        
                        form.action = newAction;
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Prepare merchant data
     */
    private function prepare_merchant_data( $order, $transaction_ref ) {
        $data = array(
            'merchant_id'    => $this->merchant_id,
            'order_id'       => $transaction_ref,
            'amount'         => $order->get_total(),
            'currency'       => $order->get_currency(),
            'redirect_url'   => WC()->api_request_url( 'WC_Gateway_CCAvenue' ),
            'cancel_url'     => $order->get_cancel_order_url(),
            'language'       => 'EN',
            'billing_name'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'billing_address'=> $order->get_billing_address_1(),
            'billing_city'   => $order->get_billing_city(),
            'billing_state'  => $order->get_billing_state(),
            'billing_zip'    => $order->get_billing_postcode(),
            'billing_country'=> $order->get_billing_country(),
            'billing_tel'    => $order->get_billing_phone(),
            'billing_email'  => $order->get_billing_email(),
            'delivery_name'  => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'delivery_address'=> $order->get_shipping_address_1(),
            'delivery_city'  => $order->get_shipping_city(),
            'delivery_state' => $order->get_shipping_state(),
            'delivery_zip'   => $order->get_shipping_postcode(),
            'delivery_country'=> $order->get_shipping_country(),
            'delivery_tel'   => $order->get_billing_phone(),
            'merchant_param1'=> $order->get_id(),
            'merchant_param2'=> wp_get_current_user()->ID ?? 'guest',
            'merchant_param3'=> get_bloginfo( 'name' ),
            'merchant_param4'=> site_url(),
            'merchant_param5'=> WC_CCAVENUE_PAYMENTS_VERSION
        );
        
        return http_build_query( $data );
    }
    
    /**
     * Handle CCAvenue response
     */
    public function handle_ccavenue_response() {
        $enc_response = $_POST['encResponse'] ?? '';
        
        if ( empty( $enc_response ) ) {
            wp_die( __( 'Invalid response from payment gateway', 'woocommerce-ccavenue-payments' ) );
        }
        
        // Decrypt response
        $response = WC_CCAvenue_Payments::instance()->encryption->decrypt( $enc_response, $this->working_key );
        parse_str( $response, $data );
        
        $order_id = $data['order_id'] ?? '';
        $tracking_id = $data['tracking_id'] ?? '';
        $bank_ref_no = $data['bank_ref_no'] ?? '';
        $order_status = $data['order_status'] ?? '';
        $failure_message = $data['failure_message'] ?? '';
        $payment_mode = $data['payment_mode'] ?? '';
        $card_name = $data['card_name'] ?? '';
        
        // Get order from merchant param
        $order = wc_get_order( $data['merchant_param1'] ?? 0 );
        
        if ( ! $order ) {
            wp_die( __( 'Order not found', 'woocommerce-ccavenue-payments' ) );
        }
        
        // Update order meta
        $order->update_meta_data( '_ccavenue_tracking_id', $tracking_id );
        $order->update_meta_data( '_ccavenue_bank_ref_no', $bank_ref_no );
        $order->update_meta_data( '_ccavenue_payment_mode', $payment_mode );
        $order->update_meta_data( '_ccavenue_card_name', $card_name );
        
        // Handle order status
        switch ( strtolower( $order_status ) ) {
            case 'success':
                $order->payment_complete();
                $order->add_order_note( sprintf(
                    __( 'CCAvenue payment successful. Tracking ID: %s, Bank Ref: %s', 'woocommerce-ccavenue-payments' ),
                    $tracking_id,
                    $bank_ref_no
                ) );
                
                // Trigger voice notification
                if ( $this->enable_voice_alerts ) {
                    WC_CCAvenue_Payments::instance()->voice_notifications->send_payment_notification( $order, 'success' );
                }
                break;
                
            case 'failure':
                $retry_count = $order->get_meta( '_ccavenue_retry_count' ) ?: 0;
                
                if ( $retry_count < self::MAX_RETRY_ATTEMPTS && $this->retry_payments ) {
                    $retry_count++;
                    $order->update_meta_data( '_ccavenue_retry_count', $retry_count );
                    $order->add_order_note( sprintf(
                        __( 'Payment failed. Retry attempt %d of %d. Reason: %s', 'woocommerce-ccavenue-payments' ),
                        $retry_count,
                        self::MAX_RETRY_ATTEMPTS,
                        $failure_message
                    ) );
                } else {
                    $order->update_status( 'failed', sprintf(
                        __( 'Payment failed via CCAvenue. Reason: %s', 'woocommerce-ccavenue-payments' ),
                        $failure_message
                    ) );
                }
                break;
                
            case 'pending':
                $order->update_status( 'on-hold', __( 'Payment pending via CCAvenue.', 'woocommerce-ccavenue-payments' ) );
                $order->add_order_note( __( 'Payment is pending verification with bank.', 'woocommerce-ccavenue-payments' ) );
                break;
                
            default:
                $order->update_status( 'on-hold', sprintf(
                    __( 'Unknown payment status: %s', 'woocommerce-ccavenue-payments' ),
                    $order_status
                ) );
                break;
        }
        
        $order->save();
        
        // Log transaction
        $this->log_transaction( $order->get_id(), $order_id, $order_status, $data );
        
        // Redirect to thank you page
        wp_redirect( $this->get_return_url( $order ) );
        exit;
    }
    
    /**
     * Thank you page
     */
    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        $status = $order->get_status();
        
        echo '<div class="ccavenue-thankyou">';
        
        if ( 'processing' === $status || 'completed' === $status ) {
            echo '<div class="payment-success">';
            echo '<h3>' . __( 'Payment Successful!', 'woocommerce-ccavenue-payments' ) . '</h3>';
            echo '<p>' . sprintf(
                __( 'Your payment of %s was successfully processed via CCAvenue.', 'woocommerce-ccavenue-payments' ),
                wc_price( $order->get_total() )
            ) . '</p>';
            
            $tracking_id = $order->get_meta( '_ccavenue_tracking_id' );
            if ( $tracking_id ) {
                echo '<p>' . sprintf(
                    __( 'Tracking ID: %s', 'woocommerce-ccavenue-payments' ),
                    $tracking_id
                ) . '</p>';
            }
            echo '</div>';
        } elseif ( 'failed' === $status ) {
            echo '<div class="payment-failed">';
            echo '<h3>' . __( 'Payment Failed', 'woocommerce-ccavenue-payments' ) . '</h3>';
            echo '<p>' . __( 'Your payment could not be processed. Please try again.', 'woocommerce-ccavenue-payments' ) . '</p>';
            echo '<a href="' . esc_url( $order->get_checkout_payment_url() ) . '" class="button">' . __( 'Retry Payment', 'woocommerce-ccavenue-payments' ) . '</a>';
            echo '</div>';
        } else {
            echo '<div class="payment-pending">';
            echo '<h3>' . __( 'Payment Pending', 'woocommerce-ccavenue-payments' ) . '</h3>';
            echo '<p>' . __( 'Your payment is being processed. You will receive a confirmation shortly.', 'woocommerce-ccavenue-payments' ) . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Generate transaction reference
     */
    private function generate_transaction_reference( $order_id ) {
        $prefix = 'CCA';
        $timestamp = time();
        $random = wp_generate_password( 6, false );
        return $prefix . $order_id . $timestamp . $random;
    }
    
    /**
     * Log transaction
     */
    public function log_transaction( $order_id, $transaction_ref, $status, $data = array() ) {
        WC_CCAvenue_Payments_Install::log_transaction(
            $order_id,
            $transaction_ref,
            $data['amount'] ?? 0,
            $data['currency'] ?? get_woocommerce_currency(),
            $data['payment_mode'] ?? 'unknown',
            $status,
            $data['tracking_id'] ?? null,
            $data['bank_ref_no'] ?? null,
            $data['failure_message'] ?? null
        );
    }
    
    /**
     * Process refund
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order = wc_get_order( $order_id );
        $tracking_id = $order->get_meta( '_ccavenue_tracking_id' );
        
        if ( ! $tracking_id ) {
            return new WP_Error( 'error', __( 'No tracking ID found for refund', 'woocommerce-ccavenue-payments' ) );
        }
        
        // Implement refund logic with CCAvenue API
        // This would typically involve making an API call to CCAvenue's refund endpoint
        
        $refund_data = array(
            'tracking_id' => $tracking_id,
            'refund_amount' => $amount,
            'refund_reason' => $reason,
            'refund_ref' => $this->generate_transaction_reference( $order_id ) . '_REFUND'
        );
        
        // Log refund attempt
        $this->log_transaction( $order_id, $refund_data['refund_ref'], 'refund_initiated', $refund_data );
        
        // In a real implementation, you would make an API call here
        // For now, we'll simulate a successful refund
        $order->add_order_note( sprintf(
            __( 'Refund initiated via CCAvenue. Amount: %s, Reason: %s', 'woocommerce-ccavenue-payments' ),
            wc_price( $amount ),
            $reason
        ) );
        
        return true;
    }
}
