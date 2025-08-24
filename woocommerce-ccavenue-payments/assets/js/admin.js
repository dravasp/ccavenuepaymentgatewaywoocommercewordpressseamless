/**
 * Admin JavaScript for WooCommerce CCAvenue Payments
 */

jQuery(document).ready(function($) {
    // Example: Add some dynamic behavior to admin settings if needed
    // This script is enqueued specifically for the CCAvenue settings page.

    // You can add logic here to show/hide fields based on other settings,
    // perform client-side validation, or enhance the UI.

    // For instance, if 'enable_voice_alerts' checkbox is unchecked,
    // you might want to disable or hide the Alexa/Google Home related fields.
    var voiceAlertsCheckbox = $('#woocommerce_ccavenue_enable_voice_alerts');
    var voiceAlertsSettings = voiceAlertsCheckbox.closest('table').find('tr').filter(function() {
        return $(this).find('th label[for^="woocommerce_ccavenue_alexa"], th label[for^="woocommerce_ccavenue_google"]').length > 0;
    });

    function toggleVoiceAlertsSettings() {
        if (voiceAlertsCheckbox.is(':checked')) {
            voiceAlertsSettings.show();
        } else {
            voiceAlertsSettings.hide();
        }
    }

    // Initial state on page load
    toggleVoiceAlertsSettings();

    // Bind change event
    voiceAlertsCheckbox.on('change', toggleVoiceAlertsSettings);

    // Example: Show a confirmation dialog before saving settings (optional)
    // $('form#mainform').submit(function(e) {
    //     if (!confirm('Are you sure you want to save these CCAvenue settings?')) {
    //         e.preventDefault();
    //     }
    // });
});
