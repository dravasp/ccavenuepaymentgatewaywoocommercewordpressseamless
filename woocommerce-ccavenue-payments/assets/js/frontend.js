/**
 * Frontend JavaScript for WooCommerce CCAvenue Payments
 */

jQuery(document).ready(function($) {
    // This script is enqueued on the checkout page and potentially on the receipt page.

    // Handle AJAX QR code generation if needed (though the current QR generation is server-side)
    // This part would be relevant if QR codes were generated dynamically after page load.
    // For now, the QR code is generated on the server and displayed directly.

    // Example of how you might use wc_ccavenue_params (localized script data)
    if (typeof wc_ccavenue_params !== 'undefined') {
        console.log('CCAvenue Frontend Params:', wc_ccavenue_params);

        // Example: Display a loading message when the payment form is submitted
        $('form[name="redirect"]').on('submit', function() {
            var $button = $(this).find('button[type="submit"]');
            $button.text(wc_ccavenue_params.i18n.loading).prop('disabled', true);
            // You might also add a spinner or overlay here
        });

        // If you had an AJAX call for QR code, it might look something like this:
        // function generateDynamicQrCode(amount, currency, transactionRef) {
        //     $.ajax({
        //         url: wc_ccavenue_params.ajax_url,
        //         type: 'POST',
        //         data: {
        //             action: 'generate_upi_qr',
        //             amount: amount,
        //             currency: currency,
        //             transaction_ref: transactionRef,
        //             nonce: wc_ccavenue_params.nonce
        //         },
        //         beforeSend: function() {
        //             // Show loading indicator for QR
        //         },
        //         success: function(response) {
        //             if (response.success) {
        //                 $('.qr-code-container img').attr('src', response.data.qr_url);
        //                 $('.qr-code-container .qr-ref').text('Reference: ' + response.data.transaction_ref);
        //             } else {
        //                 console.error('Error generating QR:', response.data.error);
        //                 alert(wc_ccavenue_params.i18n.error + ' ' + response.data.error);
        //             }
        //         },
        //         error: function() {
        //             console.error('AJAX error generating QR.');
        //             alert(wc_ccavenue_params.i18n.error);
        //         },
        //         complete: function() {
        //             // Hide loading indicator
        //         }
        //     });
        // }

        // This would be called on the receipt page if the QR was dynamic
        // var $qrSection = $('.ccavenue-qr-section');
        // if ($qrSection.length) {
        //     var amount = $qrSection.data('amount'); // Assuming you add data attributes to the section
        //     var currency = $qrSection.data('currency');
        //     var transactionRef = $qrSection.data('transaction-ref');
        //     if (amount && currency && transactionRef) {
        //         generateDynamicQrCode(amount, currency, transactionRef);
        //     }
        // }
    }
});
