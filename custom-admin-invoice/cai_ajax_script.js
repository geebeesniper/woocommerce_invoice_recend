jQuery(document).ready(function($) {
    $('#send_invoice').on('click', function(e) {
        e.preventDefault();

        // Get the input values
        var toEmail = $('#to_email').val();
        var ccEmail = $('#cc_email').val();
        var bccEmail = $('#bcc_email').val();
        var orderId = cai_ajax_object.order_id;

        // Ensure required fields are filled
        if (!toEmail) {
            alert(cai_ajax_object.empty_email_msg);
            return;
        }

        // Disable the send button to prevent multiple clicks
        $('#send_invoice').prop('disabled', true);

        // AJAX request
        $.ajax({
            url: cai_ajax_object.ajax_url, // WordPress AJAX handler
            type: 'POST',
            data: {
                action: 'cai_send_invoice', // Name of the AJAX action
                order_id: orderId,
                to_email: toEmail,
                cc_email: ccEmail,
                bcc_email: bccEmail,
                nonce: cai_ajax_object.nonce
            },
            success: function(response) {
                // Enable the send button
                $('#send_invoice').prop('disabled', false);

                // Display success or error message based on response
                if (response.success) {
                    alert(cai_ajax_object.success_msg);
                    // Update the send history
                    $('#cai_send_history').html(response.data.send_history);
                } else {
                    alert(cai_ajax_object.error_msg);
                }
            },
            error: function() {
                // Enable the send button
                $('#send_invoice').prop('disabled', false);
                alert(cai_ajax_object.error_msg);
            }
        });
    });
});
