/**
 * Check if user clicks .js-send-test-email button
 * 
 * ! response message not displaying
 */
function sendTestEmail() {

    var email_field = jQuery('.js-test-email');
    var email = email_field.val();
    var nonce = jQuery('#update_login_history_settings_nonce').val();
    var response_message = jQuery('.js-test-email-response');

    response_message.empty();
    
    if (email === '') {
        alert('Please enter email address');
        return false;
    }
    
    var data = {
        action: 'wppl_send_test_email',
        email: email,
        nonce: nonce
    };

    jQuery.ajax({
        type: 'POST',
        dataType: 'json',
        url: ajaxurl,
        data: data,
        complete: function(response) {
            var message = response.responseJSON.data;
            email_field.val('');
            response_message.html(message);
        }
    });

}

jQuery(document).ready(function () {
    
    var send_test_email_button = jQuery('.js-send-test-email');
    if (send_test_email_button.length > 0) {

        send_test_email_button.on('click', function(e) {
            e.preventDefault();
            sendTestEmail();
        });

    }

});