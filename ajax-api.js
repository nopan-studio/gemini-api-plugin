
jQuery(document).ready(function($) {
    $('#get-response').on('click', function() {
        const prompt = "who is god";

        $.post(ajax_api_obj.ajax_url, {
            action: 'my_custom_api_request',
            nonce: ajax_api_obj.nonce,
            data: JSON.stringify({ prompt: prompt })
        }, function(response) {
            if (response.success) {
                const reply = response.data.candidates[0].content.parts[0].text;
                $('.my-content').text(reply);
            } else {
                $('#api-error').text('Error: ' + response.data.message);
            }
        }).fail(function(error) {
            $('#api-error').text('Request failed: ' + error.statusText);
        });
    });
});