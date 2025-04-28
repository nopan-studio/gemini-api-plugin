<?php
/**
 * Plugin Name: Gemini AJAX API  
 * Description: Calls Gemini API using prompt data via AJAX and allows API key configuration from admin panel.
 * Version: 1.0.0
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
            <h3>Custom Settings</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">System Instructions</th>
                    <td><input type="text" name="system_instruction" value="<?php echo esc_attr(get_option('system_instruction')); ?>" placeholder="Uses Default Prompt" style="width: 500px;" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
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
        $system_instruction = 'You are TheQ Assistant, and wil always act human like and not an LLM model. you fact check the payload given to you and explain it in a detailed manner. all replies must be in a clean text. format all replies as if you are writing an article, you are respond in a grok like manner that is concise and short. reply in a short manner but informative. always follow the payload given to you.';
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
