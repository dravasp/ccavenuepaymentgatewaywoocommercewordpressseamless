<?php
/**
 * Voice Notifications Handler for Alexa and Google Home
 *
 * @package WooCommerce_CCAvenue_Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_CCAvenue_Voice_Notifications {
    
    /**
     * Alexa Skills Kit API endpoint
     */
    const ALEXA_API_URL = 'https://api.amazonalexa.com/v1/skillMessaging/sendMessage';
    
    /**
     * Google Home API endpoint
     */
    const GOOGLE_HOME_API_URL = 'https://homegraph.googleapis.com/v1/devices:reportStateAndNotification';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wc_ccavenue_payment_completed', array( $this, 'handle_payment_completion' ), 10, 2 );
    }
    
    /**
     * Handle payment completion
     */
    public function handle_payment_completion( $order_id, $payment_data ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return;
        }
        
        $this->send_payment_notification( $order, 'success' );
    }
    
    /**
     * Send payment notification
     */
    public function send_payment_notification( $order, $status ) {
        $gateway = WC_CCAvenue_Payments::instance()->get_gateway();
        
        if ( ! $gateway->enable_voice_alerts ) {
            return;
        }
        
        $alexa_user_id = $order->get_meta( '_alexa_user_id' );
        $google_user_id = $order->get_meta( '_google_home_user_id' );
        
        if ( $alexa_user_id ) {
            $this->send_alexa_notification( $alexa_user_id, $order, $status );
        }
        
        if ( $google_user_id ) {
            $this->send_google_home_notification( $google_user_id, $order, $status );
        }
    }
    
    /**
     * Send Alexa notification
     */
    private function send_alexa_notification( $user_id, $order, $status ) {
        $alexa_skill_id = get_option( 'wc_ccavenue_alexa_skill_id' );
        $alexa_access_token = get_option( 'wc_ccavenue_alexa_access_token' );
        
        if ( ! $alexa_skill_id || ! $alexa_access_token ) {
            return;
        }
        
        $message = $this->get_notification_message( $order, $status );
        
        $payload = array(
            'userId' => $user_id,
            'message' => array(
                'content' => $message,
                'type' => 'PlainText'
            )
        );
        
        $response = wp_remote_post( self::ALEXA_API_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $alexa_access_token
            ),
            'body' => json_encode( $payload ),
            'timeout' => 15
        ) );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'Alexa notification failed: ' . $response->get_error_message() );
        }
    }
    
    /**
     * Send Google Home notification
     */
    private function send_google_home_notification( $user_id, $order, $status ) {
        $google_project_id = get_option( 'wc_ccavenue_google_project_id' );
        $google_api_key = get_option( 'wc_ccavenue_google_api_key' );
        
        if ( ! $google_project_id || ! $google_api_key ) {
            return;
        }
        
        $message = $this->get_notification_message( $order, $status );
        
        $payload = array(
            'requestId' => uniqid(),
            'agentUserId' => $user_id,
            'payload' => array(
                'devices' => array(
                    'notifications' => array(
                        'Notification' => array(
                            'priority' => 'HIGH',
                            'message' => $message
                        )
                    )
                )
            )
        );
        
        $response = wp_remote_post( self::GOOGLE_HOME_API_URL . '?key=' . $google_api_key, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode( $payload ),
            'timeout' => 15
        ) );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'Google Home notification failed: ' . $response->get_error_message() );
        }
    }
    
    /**
     * Get notification message
     */
    private function get_notification_message( $order, $status ) {
        $amount = wc_price( $order->get_total() );
        $order_id = $order->get_id();
        
        switch ( $status ) {
            case 'success':
                return sprintf(
                    __( 'You have received %s payment via CCAvenue for order %d. Payment successful.', 'woocommerce-ccavenue-payments' ),
                    $amount,
                    $order_id
                );
                
            case 'failed':
                return sprintf(
                    __( 'Payment of %s for order %d via CCAvenue has failed. Please check your payment method.', 'woocommerce-ccavenue-payments' ),
                    $amount,
                    $order_id
                );
                
            case 'pending':
                return sprintf(
                    __( 'Payment of %s for order %d via CCAvenue is pending verification. Please wait for confirmation.', 'woocommerce-ccavenue-payments' ),
                    $amount,
                    $order_id
                );
                
            default:
                return sprintf(
                    __( 'Payment status update for order %d: %s', 'woocommerce-ccavenue-payments' ),
                    $order_id,
                    $status
                );
        }
    }
    
    /**
     * Get Alexa setup instructions
     */
    public function get_alexa_setup_instructions() {
        return array(
            'steps' => array(
                __( 'Go to Alexa Developer Console (https://developer.amazon.com/alexa/console/ask)', 'woocommerce-ccavenue-payments' ),
                __( 'Create a new skill or use existing one', 'woocommerce-ccavenue-payments' ),
                __( 'Enable Skill Messaging in the skill settings', 'woocommerce-ccavenue-payments' ),
                __( 'Note down your Skill ID and generate an access token', 'woocommerce-ccavenue-payments' ),
                __( 'Configure the plugin with your Skill ID and access token', 'woocommerce-ccavenue-payments' )
            ),
            'documentation' => 'https://developer.amazon.com/docs/smapi/skill-messaging-api-reference.html'
        );
    }
    
    /**
     * Get Google Home setup instructions
     */
    public function get_google_home_setup_instructions() {
        return array(
            'steps' => array(
                __( 'Go to Google Actions Console (https://console.actions.google.com/)', 'woocommerce-ccavenue-payments' ),
                __( 'Create a new project or use existing one', 'woocommerce-ccavenue-payments' ),
                __( 'Enable Home Graph API in Google Cloud Console', 'woocommerce-ccavenue-payments' ),
                __( 'Generate API key for your project', 'woocommerce-ccavenue-payments' ),
                __( 'Configure the plugin with your Project ID and API key', 'woocommerce-ccavenue-payments' )
            ),
            'documentation' => 'https://developers.google.com/assistant/smarthome/develop/notifications'
        );
    }
}
