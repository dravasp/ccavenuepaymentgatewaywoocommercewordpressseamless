<?php
/**
 * CCAvenue Encryption/Decryption Handler
 *
 * @package WooCommerce_CCAvenue_Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_CCAvenue_Encryption {
    
    /**
     * Encrypt data using CCAvenue's method
     *
     * @param string $plainText Plain text to encrypt
     * @param string $key Working key
     * @return string Encrypted text
     */
    public function encrypt( $plainText, $key ) {
        $key = $this->hextobin( md5( $key ) );
        $initVector = pack( "C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f );
        $openMode = openssl_encrypt( $plainText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector );
        $encryptedText = bin2hex( $openMode );
        return $encryptedText;
    }
    
    /**
     * Decrypt data using CCAvenue's method
     *
     * @param string $encryptedText Encrypted text
     * @param string $key Working key
     * @return string Decrypted text
     */
    public function decrypt( $encryptedText, $key ) {
        $key = $this->hextobin( md5( $key ) );
        $initVector = pack( "C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f );
        $encryptedText = $this->hextobin( $encryptedText );
        $decryptedText = openssl_decrypt( $encryptedText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector );
        return $decryptedText;
    }
    
    /**
     * Convert hexadecimal to binary
     *
     * @param string $hexString Hexadecimal string
     * @return string Binary string
     */
    private function hextobin( $hexString ) {
        $length = strlen( $hexString );
        $binString = "";
        $count = 0;
        
        while ( $count < $length ) {
            $subString = substr( $hexString, $count, 2 );
            $packedString = pack( "H*", $subString );
            
            if ( $count == 0 ) {
                $binString = $packedString;
            } else {
                $binString .= $packedString;
            }
            
            $count += 2;
        }
        
        return $binString;
    }
    
    /**
     * Sanitize and validate payment data
     *
     * @param array $data Payment data
     * @return array Sanitized data
     */
    public function sanitize_payment_data( $data ) {
        $sanitized = array();
        
        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) {
                $sanitized[ $key ] = $this->sanitize_payment_data( $value );
            } else {
                $sanitized[ $key ] = $this->sanitize_field( $value, $key );
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize individual field
     *
     * @param mixed $value Field value
     * @param string $key Field key
     * @return mixed Sanitized value
     */
    private function sanitize_field( $value, $key ) {
        $value = trim( $value );
        
        // Different sanitization based on field type
        switch ( $key ) {
            case 'billing_email':
            case 'delivery_email':
                return sanitize_email( $value );
                
            case 'amount':
            case 'order_total':
                return floatval( $value );
                
            case 'order_id':
            case 'merchant_id':
            case 'tracking_id':
                return sanitize_text_field( $value );
                
            case 'billing_address':
            case 'delivery_address':
            case 'failure_message':
                return sanitize_textarea_field( $value );
                
            default:
                return sanitize_text_field( $value );
        }
    }
    
    /**
     * Validate webhook signature
     *
     * @param string $payload Webhook payload
     * @param string $signature Signature header
     * @param string $secret Webhook secret
     * @return bool Whether signature is valid
     */
    public function validate_webhook_signature( $payload, $signature, $secret ) {
        $expected_signature = hash_hmac( 'sha256', $payload, $secret );
        return hash_equals( $expected_signature, $signature );
    }
}
