<?php
/**
 * Admin Class for WooCommerce CCAvenue Payments
 *
 * @package WooCommerce_CCAvenue_Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_CCAvenue_Payments_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add admin menu item
     */
    public function admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'CCAvenue Settings', 'woocommerce-ccavenue-payments' ),
            __( 'CCAvenue', 'woocommerce-ccavenue-payments' ),
            'manage_options',
            'wc-ccavenue-settings',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // General Settings Section
        add_settings_section(
            'wc_ccavenue_general_settings',
            __( 'General Settings', 'woocommerce-ccavenue-payments' ),
            array( $this, 'general_settings_section_callback' ),
            'wc-ccavenue-settings'
        );

        add_settings_field(
            'wc_ccavenue_upi_vpa',
            __( 'UPI VPA', 'woocommerce-ccavenue-payments' ),
            array( $this, 'text_input_callback' ),
            'wc-ccavenue-settings',
            'wc_ccavenue_general_settings',
            array(
                'name'        => 'wc_ccavenue_upi_vpa',
                'label_for'   => 'wc_ccavenue_upi_vpa',
                'description' => __( 'Your Virtual Payment Address (VPA) for UPI payments (e.g., yourname@bankname).', 'woocommerce-ccavenue-payments' ),
            )
        );

        add_settings_field(
            'wc_ccavenue_merchant_name',
            __( 'Merchant Name for UPI', 'woocommerce-ccavenue-payments' ),
            array( $this, 'text_input_callback' ),
            'wc-ccavenue-settings',
            'wc_ccavenue_general_settings',
            array(
                'name'        => 'wc_ccavenue_merchant_name',
                'label_for'   => 'wc_ccavenue_merchant_name',
                'description' => __( 'Your merchant name to be displayed on UPI payment requests.', 'woocommerce-ccavenue-payments' ),
            )
        );

        // Voice Notifications Section
        add_settings_section(
            'wc_ccavenue_voice_notifications_settings',
            __( 'Voice Notifications Settings', 'woocommerce-ccavenue-payments' ),
            array( $this, 'voice_notifications_settings_section_callback' ),
            'wc-ccavenue-settings'
        );

        add_settings_field(
            'wc_ccavenue_alexa_skill_id',
            __( 'Alexa Skill ID', 'woocommerce-ccavenue-payments' ),
            array( $this, 'text_input_callback' ),
            'wc-ccavenue-settings',
            'wc_ccavenue_voice_notifications_settings',
            array(
                'name'        => 'wc_ccavenue_alexa_skill_id',
                'label_for'   => 'wc_ccavenue_alexa_skill_id',
                'description' => __( 'Your Alexa Skill ID for voice notifications.', 'woocommerce-ccavenue-payments' ),
            )
        );

        add_settings_field(
            'wc_ccavenue_alexa_access_token',
            __( 'Alexa Access Token', 'woocommerce-ccavenue-payments' ),
            array( $this, 'text_input_callback' ),
            'wc-ccavenue-settings',
            'wc_ccavenue_voice_notifications_settings',
            array(
                'name'        => 'wc_ccavenue_alexa_access_token',
                'label_for'   => 'wc_ccavenue_alexa_access_token',
                'description' => __( 'Your Alexa Access Token for voice notifications.', 'woocommerce-ccavenue-payments' ),
            )
        );

        add_settings_field(
            'wc_ccavenue_google_project_id',
            __( 'Google Home Project ID', 'woocommerce-ccavenue-payments' ),
            array( $this, 'text_input_callback' ),
            'wc-ccavenue-settings',
            'wc_ccavenue_voice_notifications_settings',
            array(
                'name'        => 'wc_ccavenue_google_project_id',
                'label_for'   => 'wc_ccavenue_google_project_id',
                'description' => __( 'Your Google Home Project ID for voice notifications.', 'woocommerce-ccavenue-payments' ),
            )
        );

        add_settings_field(
            'wc_ccavenue_google_api_key',
            __( 'Google Home API Key', 'woocommerce-ccavenue-payments' ),
            array( $this, 'text_input_callback' ),
            'wc-ccavenue-settings',
            'wc_ccavenue_voice_notifications_settings',
            array(
                'name'        => 'wc_ccavenue_google_api_key',
                'label_for'   => 'wc_ccavenue_google_api_key',
                'description' => __( 'Your Google Home API Key for voice notifications.', 'woocommerce-ccavenue-payments' ),
            )
        );

        // Register settings
        register_setting( 'wc-ccavenue-settings-group', 'wc_ccavenue_upi_vpa' );
        register_setting( 'wc-ccavenue-settings-group', 'wc_ccavenue_merchant_name' );
        register_setting( 'wc-ccavenue-settings-group', 'wc_ccavenue_alexa_skill_id' );
        register_setting( 'wc-ccavenue-settings-group', 'wc_ccavenue_alexa_access_token' );
        register_setting( 'wc-ccavenue-settings-group', 'wc_ccavenue_google_project_id' );
        register_setting( 'wc-ccavenue-settings-group', 'wc_ccavenue_google_api_key' );
    }

    /**
     * General Settings Section Callback
     */
    public function general_settings_section_callback() {
        echo '<p>' . esc_html__( 'Configure general settings for CCAvenue Payments.', 'woocommerce-ccavenue-payments' ) . '</p>';
    }

    /**
     * Voice Notifications Settings Section Callback
     */
    public function voice_notifications_settings_section_callback() {
        echo '<p>' . esc_html__( 'Configure settings for Alexa and Google Home voice notifications.', 'woocommerce-ccavenue-payments' ) . '</p>';

        $voice_notifications = WC_CCAvenue_Payments::instance()->voice_notifications;

        echo '<h4>' . esc_html__( 'Alexa Setup Instructions:', 'woocommerce-ccavenue-payments' ) . '</h4>';
        echo '<ol>';
        foreach ( $voice_notifications->get_alexa_setup_instructions()['steps'] as $step ) {
            echo '<li>' . esc_html( $step ) . '</li>';
        }
        echo '</ol>';
        echo '<p><a href="' . esc_url( $voice_notifications->get_alexa_setup_instructions()['documentation'] ) . '" target="_blank">' . esc_html__( 'Alexa Documentation', 'woocommerce-ccavenue-payments' ) . '</a></p>';

        echo '<h4>' . esc_html__( 'Google Home Setup Instructions:', 'woocommerce-ccavenue-payments' ) . '</h4>';
        echo '<ol>';
        foreach ( $voice_notifications->get_google_home_setup_instructions()['steps'] as $step ) {
            echo '<li>' . esc_html( $step ) . '</li>';
        }
        echo '</ol>';
        echo '<p><a href="' . esc_url( $voice_notifications->get_google_home_setup_instructions()['documentation'] ) . '" target="_blank">' . esc_html__( 'Google Home Documentation', 'woocommerce-ccavenue-payments' ) . '</a></p>';
    }

    /**
     * Text input field callback
     */
    public function text_input_callback( $args ) {
        $name = $args['name'];
        $label_for = $args['label_for'];
        $description = $args['description'];
        $value = get_option( $name );
        ?>
        <input type="text" id="<?php echo esc_attr( $label_for ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php
    }

    /**
     * Settings page content
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'wc-ccavenue-settings-group' );
                do_settings_sections( 'wc-ccavenue-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
