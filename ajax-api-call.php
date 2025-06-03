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
            use shortcode [gemini_floater] in your post or page to display the button and response area.
            
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
        //wp_send_json_error(['message' => 'Prompt is required.']);
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
            'parts' => $post_data['prompt']
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

function gemini_floater() {

    $button_text = get_option('button_text');
    if (!$button_text) {
        $button_text = 'SEND';
    }

    $reply_box_text = get_option('reply_box_text');
    if (!$reply_box_text) {
        $reply_box_text = 'Response will be shown here...';
    }

    ob_start();
    ?>

    <style>
        .hidden {
            display: none;
        }
        .bordered {
            border-top: 1px solid #484848;
            border-right: 1px solid #282828;
            border-bottom: 1px solid #282828;
            border-left: 1px solid #484848;
        }
        
        .gemini-modal {
            position: fixed ;
            border-radius: 25px;
            bottom: 0% ;
            right: 0% ;
            width: 500px ;
            background-color: #fff;
            margin: 1%;
            padding: 0px 20px 20px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 998;
        }

        .gemini-modal .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0px 10px 10px 10px;
        }

        .gemini-modal .input-wrapper {
            display: flex;
            align-items: flex-end;
            flex-direction: column;
            background-color: #f1f1f1;
            border: 1px solid #c1c1c1;
            border-radius: 25px;
            padding: 10px;
        }
        
        .gemini-modal .input-wrapper .text-input {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #f1f1f1;
            color:#a1a1a1;
            border: 0px solid #ccc;
            border-radius: 25px;
        }

        .gemini-modal .input-wrapper .text-input:focus {
            outline: none;
            border-color: #282828;
        }

        .gemini-modal .chat-box {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 10px;
            padding: 10px;
            font-size: 14px;
            color: #282828;
        }

    
        .gemini-modal button {
            width: 40px;
            height: 40px;
            background-color: #d1d1d1;
            color: #a1a1a1;
            border: 1px solid #c1c1c1;
            border-radius: 25px;
            cursor: pointer;
            font-size: 10px;
            font-weight: bold;
        }


        .gemini-toggle{
            background-color: white;
            border-radius: 25px;
            color: #282828;
            position: fixed ;
            font-size: 20px;
            font-weight: bold;
            bottom: 0% ;
            right: 0% ;
            width: 50px ;
            height: 50px ;
            margin: 1%;
            z-index: 9999;
        }

        .gemini-toggle:hover {
            background-color: #fefefe;
            color: gray;
            border-color: #181818;
        }

        .chat-box {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
            display: flex;
            flex-direction: column;
        }

        .chat-box .user-message{ 
            background-color: #e1e1e1;
            color: #282828;
            width: fit-content;
            align-self: flex-end;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .chat-box .gemini-message{ 
            background-color: #fff;
            color: #282828;
            width: fit-content;
            align-self: flex-start;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
        }


    </style>

    <script>
        jQuery(document).ready(function($) {
            $('.gemini-modal').hide()
        });

        function toggle_modal(timer) {
                $('.gemini-modal').animate({
                    height: 'toggle',
                    width: 'toggle',
                    opacity: 'toggle',
                }, 150);
                $('.gemini-toggle').fadeToggle(timer);
            }
          
    </script>

    <div class="gemini-modal bordered">
        <div class="top-bar">
              <h2>Chat with Gemini</h2>
            <button class="" onclick='toggle_modal(200)'>X</button>
        </div>
      
        <div class="chat-box" id="chat-box">
            <div class="gemini-reply"></div>
        </div>

        <form class="input-wrapper" onsubmit="event.preventDefault(); getGeminiResponse();"> 
            <input type="text" id="user-payload" name="user-payload" class="user-payload text-input" placeholder="Enter your payload here..."/>
            <button id="run-gemini" type="submit">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" class="icon-md"><path d="M7.99992 14.9993V5.41334L4.70696 8.70631C4.31643 9.09683 3.68342 9.09683 3.29289 8.70631C2.90237 8.31578 2.90237 7.68277 3.29289 7.29225L8.29289 2.29225L8.36906 2.22389C8.76184 1.90354 9.34084 1.92613 9.70696 2.29225L14.707 7.29225L14.7753 7.36842C15.0957 7.76119 15.0731 8.34019 14.707 8.70631C14.3408 9.07242 13.7618 9.09502 13.3691 8.77467L13.2929 8.70631L9.99992 5.41334V14.9993C9.99992 15.5516 9.55221 15.9993 8.99992 15.9993C8.44764 15.9993 7.99993 15.5516 7.99992 14.9993Z" fill="currentColor"></path></svg>
            </button>
        </form>
    </div>

    <button class="gemini-toggle"  onclick='toggle_modal(150)'>G</button>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
    <script>
        let chat_history = [];

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
            const prompt = $('.user-payload').val(); //scrapeParagraphText('.my-content') + " " + $('.user-payload').val();

            insertChatMessage(prompt, true); 
            
            $.post(ajax_api_obj.ajax_url, {
                action: 'my_custom_api_request',
                nonce: ajax_api_obj.nonce,
                data: JSON.stringify({ prompt: chat_history })
            }, function(response) {
                if (response.success) {
                    const reply = response.data.data.candidates[0].content.parts[0].text;
                    //$('.gemini-reply').text(reply);
                    insertChatMessage(reply, false); // Insert user message
                } else {
                    $('.gemini-reply').text('Failed to get response.');
                }
            });
        }
        
        //-------------------------------------------
        function insertChatMessage(message, isUser = true) {
            const chatBox = $('#chat-box');
            const messageClass = isUser ? 'user-message' : 'gemini-message';
            chatBox.append(
                `<div class="${messageClass}">
                    <strong>${isUser ? 'You' : 'Gemini'}:</strong>
                    ${message}
                </div>`);
            chatBox.scrollTop(chatBox[0].scrollHeight);

            if (isUser) {
                // Add user message to chat history
                chat_history.push({
                    text: "User: " + message,
                });
            } else {
                // Add Gemini response to chat history
                chat_history.push({
                    text: "Model: " + message,
                });
            }
        }
      
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('gemini_floater', 'gemini_floater');
