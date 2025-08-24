<?php
/**
 * CCAvenue Webhook Handler
 *
 * @package WooCommerce_CCAvenue_Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_CCAvenue_Webhook_Handler {
    
    /**
     * Webhook events
     */
    const WEBHOOK_EVENTS = array(
        'payment_success' => 'payment.success',
        'payment_failure' => 'payment.failure',
        'payment_pending' => 'payment.pending',
        'payment_refund' => 'payment.refund',
        'payment_fraud' => 'payment.fraud'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'woocommerce_api_ccavenue_webhook', array( $this, 'handle_webhook' ) );
        add_action( 'wc_ccavenue_webhook_payment_success', array( $this, 'process_payment_success' ), 10, 2 );
        add_action( 'wc_ccavenue_webhook_payment_failure', array( $this, 'process_payment_failure' ), 10, 2 );
        add_action( 'wc_ccavenue_webhook_payment_pending', array( $this, 'process_payment_pending' ), 10, 2 );
        add_action( 'wc_ccavenue_webhook_payment_refund', array( $this, 'process_payment_refund' ), 10, 2 );
        add_action( 'wc_ccavenue_webhook_payment_fraud', array( $this, 'process_payment_fraud' ), 10, 2 );
    }
    
    /**
     * Handle incoming webhook
     */
    public function handle_webhook() {
        // Get webhook data
        $payload = file_get_contents( 'php://input' );
        $signature = $_SERVER['HTTP_X_CCAVENUE_SIGNATURE'] ?? '';
        $event_type = $_SERVER['HTTP_X_CCAVENUE_EVENT'] ?? '';
        
        // Verify webhook signature
        $encryption = WC_CCAvenue_Payments::instance()->get_encryption();
        $is_valid = $encryption->validate_webhook_signature( $payload, $signature, WC_CCAvenue_Payments::instance()->get_gateway()->working_key );
        
        if ( ! $is_valid ) {
            wp_send_json_error( array( 'error' => 'Invalid signature' ), 401 );
            exit;
        }
        
        // Parse payload
        $data = json_decode( $payload, true );
        
        if ( ! $data || ! isset( $data['order_id'] ) ) {
            wp_send_json_error( array( 'error' => 'Invalid payload' ), 400 );
            exit;
        }
        
        // Sanitize data
        $data = $encryption->sanitize_payment_data( $data );
        
        // Process webhook event
        $this->process_webhook_event( $event_type, $data );
        
        wp_send_json_success( array( 'message' => 'Webhook processed' ) );
        exit;
    }
    
    /**
     * Process webhook event
     */
    private function process_webhook_event( $event_type, $data ) {
        switch ( $event_type ) {
            case self::WEBHOOK_EVENTS['payment_success']:
                do_action( 'wc_ccavenue_webhook_payment_success', $data['order_id'], $data );
                break;
                
            case self::WEBHOOK_EVENTS['payment_failure']:
                do_action( 'wc_ccavenue_webhook_payment_failure', $data['order_id'], $data );
                break;
                
            case self::WEBHOOK_EVENTS['payment_pending']:
                do_action( 'wc_ccavenue_webhook_payment_pending', $data['order_id'], $data );
                break;
                
            case self::WEBHOOK_EVENTS['payment_refund']:
                do_action( 'wc_ccavenue_webhook_payment_refund', $data['order_id'], $data );
                break;
                
            case self::WEBHOOK_EVENTS['payment_fraud']:
                do_action( 'wc_ccavenue_webhook_payment_fraud', $data['order_id'], $data );
                break;
                
            default:
                error_log( 'Unknown CCAvenue webhook event: ' . $event_type );
                break;
        }
    }
    
    /**
     * Process payment success
     */
    public function process_payment_success( $order_id, $data ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            error_log( 'CCAvenue webhook: Order not found - ' . $order_id );
            return;
        }
        
        // Update order status
        $order->update_status( 'processing', __( 'Payment successful via CCAvenue.', 'woocommerce-ccavenue-payments' ) );
        
        // Add order notes
        $order->add_order_note( sprintf(
            __( 'CCAvenue payment successful. Tracking ID: %s, Bank Ref: %s', 'woocommerce-ccavenue-payments' ),
            $data['tracking_id'] ?? 'N/A',
            $data['bank_ref_no'] ?? 'N/A'
        ) );
        
        // Update meta data
        $order->update_meta_data( '_ccavenue_payment_status', 'success' );
        $order->update_meta_data( '_ccavenue_tracking_id', $data['tracking_id'] ?? '' );
        $order->update_meta_data( '_ccavenue_bank_ref_no', $data['bank_ref_no'] ?? '' );
        $order->update_meta_data( '_ccavenue_payment_mode', $data['payment_mode'] ?? '' );
        $order->save();
        
        // Trigger voice notification
        if ( WC_CCAvenue_Payments::instance()->get_gateway()->enable_voice_alerts ) {
            WC_CCAvenue_Payments::instance()->voice_notifications->send_payment_notification( $order, 'success' );
        }
        
        // Log transaction
        WC_CCAvenue_Payments::instance()->get_gateway()->log_transaction( $order_id, $order_id, 'success', $data );
    }
    
    /**
     * Process payment failure
     */
    public function process_payment_failure( $order_id, $data ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            error_log( 'CCAvenue webhook: Order not found - ' . $order_id );
            return;
        }
        
        $retry_count = $order->get_meta( '_ccavenue_retry_count' ) ?: 0;
        
        if ( $retry_count < 5 && WC_CCAvenue_Payments::instance()->get_gateway()->retry_payments ) {
            // Retry payment
            $retry_count++;
            $order->update_meta_data( '_ccavenue_retry_count', $retry_count );
            $order->add_order_note( sprintf(
                __( 'Payment failed. Retry attempt %d of 5. Reason: %s', 'woocommerce-ccavenue-payments' ),
                $retry_count,
                $data['failure_message'] ?? 'Unknown error'
            ) );
            $order->save();
        } else {
            // Mark as failed after max retries
            $order->update_status( 'failed', __( 'Payment failed via CCAvenue after maximum retries.', 'woocommerce-ccavenue-payments' ) );
            $order->add_order_note( sprintf(
                __( 'Payment failed after %d attempts. Reason: %s', 'woocommerce-ccavenue-payments' ),
                $retry_count,
                $data['failure_message'] ?? 'Unknown error'
            ) );
            
            $order->update_meta_data( '_ccavenue_payment_status', 'failed' );
            $order->save();
        }
        
        // Log transaction
        WC_CCAvenue_Payments::instance()->get_gateway()->log_transaction( $order_id, $order_id, 'failed', $data );
    }
    
    /**
     * Process payment pending
     */
    public function process_payment_pending( $order_id, $data ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            error_log( 'CCAvenue webhook: Order not found - ' . $order_id );
            return;
        }
        
        $order->update_status( 'on-hold', __( 'Payment pending via CCAvenue.', 'woocommerce-ccavenue-payments' ) );
        $order->add_order_note( __( 'Payment is pending verification with bank.', 'woocommerce-ccavenue-payments' ) );
        
        $order->update_meta_data( '_ccavenue_payment_status', 'pending' );
        $order->save();
        
        // Log transaction
        WC_CCAvenue_Payments::instance()->get_gateway()->log_transaction( $order_id, $order_id, 'pending', $data );
    }
    
    /**
     * Process payment refund
     */
    public function process_payment_refund( $order_id, $data ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            error_log( 'CCAvenue webhook: Order not found - ' . $order_id );
            return;
        }
        
        $refund_amount = $data['refund_amount'] ?? 0;
        $refund_reason = $data['refund_reason'] ?? '';
        
        // Create refund
        $refund = wc_create_refund( array(
            'amount'   => $refund_amount,
            'reason'   => $refund_reason,
            'order_id' => $order_id,
        ) );
        
        if ( is_wp_error( $refund ) ) {
            error_log( 'CCAvenue refund failed: ' . $refund->get_error_message() );
            return;
        }
        
        $order->add_order_note( sprintf(
            __( 'Refund processed via CCAvenue. Amount: %s, Reason: %s', 'woocommerce-ccavenue-payments' ),
            wc_price( $refund_amount ),
            $refund_reason
        ) );
        
        $order->update_meta_data( '_ccavenue_refund_amount', $refund_amount );
        $order->update_meta_data( '_ccavenue_refund_reason', $refund_reason );
        $order->save();
        
        // Log transaction
        WC_CCAvenue_Payments::instance()->get_gateway()->log_transaction( $order_id, $order_id, 'refund', $data );
    }
    
    /**
     * Process payment fraud
     */
    public function process_payment_fraud( $order_id, $data ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            error_log( 'CCAvenue webhook: Order not found - ' . $order_id );
            return;
        }
        
        $order->update_status( 'failed', __( 'Payment marked as fraud by CCAvenue.', 'woocommerce-ccavenue-payments' ) );
        $order->add_order_note( sprintf(
            __( 'Payment flagged as fraudulent. Reason: %s', 'woocommerce-ccavenue-payments' ),
            $data['fraud_reason'] ?? 'Suspicious activity detected'
        ) );
        
        $order->update_meta_data( '_ccavenue_payment_status', 'fraud' );
        $order->update_meta_data( '_ccavenue_fraud_reason', $data['fraud_reason'] ?? '' );
        $order->save();
        
        // Log transaction
        WC_CCAvenue_Payments::instance()->get_gateway()->log_transaction( $order_id, $order_id, 'fraud', $data );
    }
    
    /**
     * Get webhook URL
     */
    public function get_webhook_url() {
        return WC()->api_request_url( 'ccavenue_webhook' );
    }
}
