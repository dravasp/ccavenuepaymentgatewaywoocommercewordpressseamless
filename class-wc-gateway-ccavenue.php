<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Gateway_CCAvenue extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'ccavenue';
        $this->method_title = __('CCAvenue', 'woocommerce');
        $this->method_description = __('Accept payments via CCAvenue.', 'woocommerce');
        $this->title = __('CCAvenue', 'woocommerce');
        $this->icon = ''; // URL to an icon
        $this->has_fields = true;

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user settings
        $this->merchant_id = $this->get_option('merchant_id');
        $this->access_code = $this->get_option('access_code');
        $this->working_key = $this->get_option('working_key');

        // Action hooks
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_response'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable CCAvenue Payment Gateway', 'woocommerce'),
                'default' => 'yes'
            ),
            'merchant_id' => array(
                'title' => __('Merchant ID', 'woocommerce'),
                'type' => 'text',
                'description' => __('Enter your CCAvenue Merchant ID.', 'woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ),
            'access_code' => array(
                'title' => __('Access Code', 'woocommerce'),
                'type' => 'text',
                'description' => __('Enter your CCAvenue Access Code.', 'woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ),
            'working_key' => array(
                'title' => __('Working Key', 'woocommerce'),
                'type' => 'text',
                'description' => __('Enter your CCAvenue Working Key.', 'woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // Prepare data for CCAvenue API
        $data = array(
            'merchant_id' => $this->merchant_id,
            'order_id' => $order->get_order_number(),
            'currency' => get_woocommerce_currency(),
            'amount' => $order->get_total(),
            'redirect_url' => $this->get_return_url($order),
            'cancel_url' => $order->get_cancel_order_url(),
            // Add other required parameters
        );

        // Redirect to CCAvenue payment page
        return array(
            'result' => 'success',
            'redirect' => $this->get_ccavenue_url($data),
        );
    }

    private function get_ccavenue_url($data) {
        // Construct the CCAvenue payment URL
        return 'https://secure.ccavenue.com/transaction/init'; // Update with the correct URL
    }

    public function check_response() {
        // Check if the request is coming from CCAvenue
        if (!isset($_POST['order_id']) || !isset($_POST['tracking_id'])) {
            return; // Invalid response
        }

        // Sanitize and validate the response data
        $order_id = sanitize_text_field($_POST['order_id']);
        $tracking_id = sanitize_text_field($_POST['tracking_id']);
        $status = sanitize_text_field($_POST['status']);
        $amount = sanitize_text_field($_POST['amount']);
        $merchant_id = sanitize_text_field($_POST['merchant_id']);
        $checksum = sanitize_text_field($_POST['checksum']);

        // Verify the checksum for security
        $calculated_checksum = $this->generate_checksum($order_id, $tracking_id, $status, $amount);
        
        if ($checksum !== $calculated_checksum) {
            // Invalid checksum
            return; // Handle error
        }

        // Get the order
        $order = wc_get_order($order_id);

        if ($status === 'Success') {
            // Payment was successful
            $order->payment_complete($tracking_id);
            $order->add_order_note(__('Payment received, transaction ID: ' . $tracking_id, 'woocommerce'));
            // Redirect to thank you page
            wp_redirect($this->get_return_url($order));
            exit;
        } else {
            // Payment failed
            $order->update_status('failed', __('Payment failed: ' . $status, 'woocommerce'));
            // Redirect to failure page
            wp_redirect($order->get_cancel_order_url());
            exit;
        }
    }

    private function generate_checksum($order_id, $tracking_id, $status, $amount) {
        // Generate checksum using the working key and other parameters
        $data = $this->merchant_id . '|' . $order_id . '|' . $tracking_id . '|' . $status . '|' . $amount . '|' . $this->working_key;
        return hash('sha256', $data); // Example using SHA-256
    }
}
