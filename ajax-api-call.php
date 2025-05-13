<?php
/**
 * Plugin Name: Gemini AJAX API  
 * Description: Calls Gemini API using prompt data via AJAX and allows API key configuration from admin panel.
 * Version: 1.0.1
 * Author: Sherdelle Caneda
 */

// 1. Add settings page to admin
function gemini_api_add_settings_page() {
    add_options_page(
        'Gemini API Settings',
        'Gemini API',
        'manage_options',
        'gemini-api-settings',
        'gemini_api_render_settings_page'
    );
}
add_action('admin_menu', 'gemini_api_add_settings_page');

// 2. Register the setting
function gemini_api_register_settings() {
    register_setting('gemini-api-settings-group', 'gemini_api_key');
}
add_action('admin_init', 'gemini_api_register_settings');

// 3. Render the settings page
function gemini_api_render_settings_page() {
    ?>
    <div class="wrap">
        <h2>TheQ API</h2>
        <form method="post" action="options.php">
            <?php settings_fields('gemini-api-settings-group'); ?>
            <?php do_settings_sections('gemini-api-settings-group'); ?>
            <h3>Gemini API Settings</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Gemini API Key</th>
                    <td><input type="text" name="gemini_api_key" value="<?php echo esc_attr(get_option('gemini_api_key')); ?>"  placeholder="Add your API Key Here" style="width: 500px;" /></td>
                </tr>
            </table>
            <h3>Gemini Reply Customize</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Button Text</th>
                    <td><input type="text" name="button_text" value="<?php echo esc_attr(get_option('button_text')); ?>" placeholder="Uses Default Prompt" style="width: 500px;" /></td>
                </tr>
            </table>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Reply Box placeholder text</th>
                    <td><input type="text" name="reply_box_text" value="<?php echo esc_attr(get_option('reply_box_text')); ?>" placeholder="Uses Default Prompt" style="width: 500px;" /></td>
                </tr>
            </table>
            <h3>Custom Settings</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">System Instructions</th>
                    <td><input type="text" name="system_instruction" value="<?php echo esc_attr(get_option('system_instruction')); ?>" placeholder="Uses Default Prompt" style="width: 500px;" /></td>
                </tr>
            </table>
            
            
            <?php submit_button(); ?>
        </form>
        <h3>How to Use</h3>
        <p>
            To use this plugin, follow these steps:
            <br>
            add a .my-content class to the div or section you want to scrape.
            <br>
            use shortcode [gemini_scraper] in your post or page to display the button and response area.
            
        </p>
    </div>
    <?php
}

// 4. Enqueue JS
add_action('wp_enqueue_scripts', 'ajax_api_enqueue_scripts');
function ajax_api_enqueue_scripts() {
    wp_enqueue_script('ajax-api-script', plugin_dir_url(__FILE__) . 'ajax-api.js', ['jquery'], null, true);
    wp_localize_script('ajax-api-script', 'ajax_api_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('ajax_api_nonce'),
    ]);
}

// 5. Handle the AJAX request
add_action('wp_ajax_my_custom_api_request', 'handle_my_custom_api_request');
add_action('wp_ajax_nopriv_my_custom_api_request', 'handle_my_custom_api_request');

function handle_my_custom_api_request() {
    check_ajax_referer('ajax_api_nonce', 'nonce');

    $post_data = json_decode(stripslashes($_POST['data']), true);
    $prompt = sanitize_text_field($post_data['prompt']);
    if (!$prompt) {
        wp_send_json_error(['message' => 'Prompt is required.']);
    }

    $api_key = get_option('gemini_api_key');
    if (!$api_key) {
        wp_send_json_error(['message' => 'API key not set in plugin settings.']);
    }

    $system_instruction = get_option('system_instruction');
    if (!$system_instruction) {
        $system_instruction = 'You are a human-like assistant tasked with fact-checking provided content. Never refer to yourself as an AI or language model. Your responses must be clear, concise, and informative, written in the style of a well-edited article using clean, plain text. Avoid special characters, symbols, or unnecessary embellishments. Format all replies with clear headings and short paragraphs. Stay strictly focused on the content of the payload—do not add external information or commentary.';
        //wp_send_json_error(['message' => 'System instruction not set in plugin settings.']);
    }

    $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$api_key}";

    $payload = [
        'contents' => [[
            'parts' => [[ 'text' => $prompt ]]
        ]],
        'systemInstruction' => [
            'parts' => [[
                'text' => $system_instruction
            ]]
        ],
        'tools' => [
            [ 'googleSearch' => (object)[] ]
        ]
    ];

    $response = wp_remote_post($api_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode($payload)
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'API request failed.']);
    }

    $body = wp_remote_retrieve_body($response);
    $decoded_body = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => 'Invalid JSON received from API.']);
    }

    wp_send_json_success(['data' => $decoded_body]);
}

function gemini_scraper_shortcode() {

    $button_text = get_option('button_text');
    if (!$button_text) {
        $button_text = 'Run Gemini';
    }

    $reply_box_text = get_option('reply_box_text');
    if (!$reply_box_text) {
        $reply_box_text = 'Response will be shown here...';
    }

    ob_start();
    ?>
    <div class="gemini-reply"><?php echo($reply_box_text); ?></div>
    <button id="run-gemini"><?php echo($button_text); ?></button>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
    <script>
        function scrapeParagraphText(containerSelector) {
            let combinedText = '';

            // Find all <p> elements inside the specified container
            $(containerSelector).find('p').each(function() {
                combinedText += $(this).text() + ' ';
            });

            // Optional: trim excess whitespace
            console.log(combinedText);
            return combinedText.trim();
        }

        function getGeminiResponse() {
            const prompt = scrapeParagraphText('.my-content');
            $.post(ajax_api_obj.ajax_url, {
                action: 'my_custom_api_request',
                nonce: ajax_api_obj.nonce,
                data: JSON.stringify({ prompt: prompt })
            }, function(response) {
                if (response.success) {
                    const reply = response.data.data.candidates[0].content.parts[0].text;
                    $('.gemini-reply').text(reply);
                } else {
                    $('.gemini-reply').text('Failed to get response.');
                }
            });
        }

        // Trigger via button click
        jQuery(document).ready(function($) {
            $('#run-gemini').on('click', function() {
                getGeminiResponse();
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('gemini_scraper', 'gemini_scraper_shortcode');
