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
            @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&display=swap');

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
                all:unsets;
                font-family: 'Manrope', sans-serif;
                position: fixed ;
                border-radius: 25px;
                bottom: 0% ;
                right: 0% ;
                width:450px ;
                background-color: #fff;
                margin: 1%;
                padding: 1%;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                z-index: 998;
            }

            .gemini-modal .top-bar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding:2%;
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
                font-family: 'Manrope', sans-serif;
                width: 100%;
                padding: 10px;
                margin-bottom: 10px;
                background-color: #f1f1f1;
                color:#a1a1a1;
                border: 0px solid #ccc;
                border-radius: 25px;
                box-shadow: 0 0 0 0;
            }

            .gemini-modal .input-wrapper .text-input:focus {
                outline: none;
                border-color: #282828;
            }

            .gemini-modal .chat-box {
                max-height: 400px;
                overflow-y: auto;
                margin-bottom: 10px;
                padding: 10px;
                font-size: 14px;
                color: #282828;
            }

            .gemini-modal .button{
                background-color: #282828;
                color: white;
                border: none;
                border-radius: 25px;
                width: 25px;
                height: 25px;
                cursor: pointer;
                padding: 1%;
                font-size: 12px;
                font-weight: bold;
            }

            .gemini-modal .button:hover {
                background-color: #484848;
                color: #fefefe;
            }
            
            .gemini-modal .button-reply {
                background-color: transparent;
                color:#181818;
                border: none;
                border-radius: 25px;
                width: 25px;
                height: 25px;
                display: flex;                /* Enables Flexbox */
                align-items: center;         /* Vertically center */
                justify-content: center;     /* Horizontally center */
                cursor: pointer;
                padding: 2px;
                margin: 5px 5px 5px 0px;
                font-size: 15px;
                font-weight: bold;5
            }

            .gemini-modal .button-reply:hover {
                background-color: #d1d1d1;
                color: #282828;
            }

            .gemini-toggle{
                background-color: white;
                border-radius: 25px;
                border: 1px solid #282828;
                color: #282828;
                position: fixed ;
                font-size: 20px;
                font-weight: bold;
                bottom: 0% ;
                right: 0% ;
                width: fit-content;
                padding: 10px;
                height: 50px ;
                width: 50px;
                cursor: pointer;

                margin: 1%;
                z-index: 9999;
            }

            .gemini-toggle:hover {
                background-color: #fefefe;
                color: gray;
                border-color: #181818;
            }

            .embed-button{
                background-color: #fff;
                color: #414141;
                border: #f1f1f1 solid 1px;
                border-radius: 5px;
                width: 30px;
                height: 30px;
                cursor: pointer;
                padding: 1%;
                font-size: 15px;
                font-weight: bold;
                position: absolute;
                margin: -10px 0 0 0;
                border-radius: 50%;
                top: 0px;
                right: 0px;
                display: flex;
                align-items: center;
                justify-content: center;
                text-align: center;
            }   
            
            .embed-button:hover {
                background-color: #f1f1f1;
                color: #515151;
            }
            

            .chat-box {
                background-color: transparent;
                border: 0px solid #ccc;
                border-radius: 10px;
                padding: 10px;
                margin-bottom: 10px;
                display: flex;
                flex-direction: column;
            }

            .chat-box .user-message{
                color: #fff;
                width: fit-content;
                display: flex;
                flex-direction: row;
                align-self: flex-end;
                padding: 10px;
                border-radius: 10px;
                margin-bottom: 10px;
            }

            .chat-box .user-message .message-text{
                background-color:rgb(20, 125, 245);
                color: #fff;
                padding: 10px;
                border-radius: 10px;
                max-width: 300px;
            }
            
            .chat-error{
                background-color: rgb(255, 175, 175) !important;
            }

            .chat-box .user-message .message-text *{
                padding: 0;
                margin: 0;
            }

            .chat-box .gemini-message{ 
                background-color: transparent;
                color: #282828;
                width: fit-content;
                align-self: flex-start;
                padding: 10px;
                border-radius: 10px;
                margin-bottom: 10px;
            }

            .chat-box .gemini-message .message-text{
                padding: 10px;
                border-radius: 10px;
                max-width: 300px;
                word-wrap: break-word;
            }


            .chat-box .gemini-message-content{
                color: #282828;
                padding: 10px;
                border-radius: 10px;
                width: fit-content;
                max-height: fit-content;
                word-wrap: break-word;
            }    

            .chat-box .gemini-message-content .message-text{ 
                background-color: #f1f1f1;
                border : 1px solid #181818;
                color: #282828;
                padding: 10px;
                border-radius: 10px;
                max-width: 100% ;
                max-height: 250px;
                overflow: hidden;
                word-wrap: break-word;
            }

            .chat-box .gemini-message-content .message-text *{ 
                margin: 0;
            }
        </style>

        <script>
            function embed_button () {
                const button = $('<button>', {
                    class: 'embed-button',
                    html: '?',
                    click: function () {
                        const $parent = $(this).parent();
                        const scraped = scrapeParagraphText($parent.html()); // This logs the "value" of the parent, if applicable
                        $('.user-payload').val(scraped);
                        getGeminiResponse(true);
                        toggle_modal(150);
                    }
                });
                $('.gemini-content').append(button);
            }  

            function toggle_modal(timer) {
                $('.gemini-modal').animate({
                        height: 'toggle',
                        width: 'toggle',
                        opacity: 'toggle',
                    }, 150);
                    $('.gemini-toggle').fadeToggle(timer);
            }

            jQuery(document).ready(function($) {
                $('.gemini-modal').hide();
                embed_button();
            });
    
        </script>

        <div class="gemini-modal bordered">
            <div class="top-bar">
                <h1>Ask Gemini</h1>
                <button class="button" onclick='toggle_modal(200)'>X</button>
            </div>
        
            <div class="chat-box" id="chat-box">
                <div class="gemini-reply"></div>
            </div>

            <form class="input-wrapper" onsubmit="event.preventDefault(); getGeminiResponse();"> 
                <input type="text" id="user-payload" name="user-payload" class="user-payload text-input" placeholder="Ask anything.."/>
                <button class="button" id="run-gemini" type="submit">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" class="icon-md"><path d="M7.99992 14.9993V5.41334L4.70696 8.70631C4.31643 9.09683 3.68342 9.09683 3.29289 8.70631C2.90237 8.31578 2.90237 7.68277 3.29289 7.29225L8.29289 2.29225L8.36906 2.22389C8.76184 1.90354 9.34084 1.92613 9.70696 2.29225L14.707 7.29225L14.7753 7.36842C15.0957 7.76119 15.0731 8.34019 14.707 8.70631C14.3408 9.07242 13.7618 9.09502 13.3691 8.77467L13.2929 8.70631L9.99992 5.41334V14.9993C9.99992 15.5516 9.55221 15.9993 8.99992 15.9993C8.44764 15.9993 7.99993 15.5516 7.99992 14.9993Z" fill="currentColor"></path></svg>
                </button>
            </form>
        </div>

        <button class="gemini-toggle"  onclick='toggle_modal(150)'>G</button>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.5/dist/purify.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

        <script>
            let firstload = true;
            let chat_history = [];
            let chat_history_length = 0;
            
            function escapeHTML(str) {
                return str
                    .replace(/&/g, '&amp')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

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

            function getGeminiResponse(isContent) {
                
                const prompt = DOMPurify.sanitize($('.user-payload').val())//scrapeParagraphText('.my-content') + " " + $('.user-payload').val()

                if (!prompt) {
                    return;
                }

                $('.user-payload').val('');
                
                const history= chat_history_length;

                insertChatMessage(prompt, true, isContent, chat_history_length);
                            
                $.post(ajax_api_obj.ajax_url, {
                    action: 'my_custom_api_request',
                    nonce: ajax_api_obj.nonce,
                    data: JSON.stringify({ prompt: chat_history })
                }, function(response) {
                    if (response.success && !response.data.data.error) {
                        const reply = response.data.data.candidates[0].content.parts[0].text;
                        //$('.gemini-reply').text(reply);
                        insertChatMessage(reply, false); // Insert user message
                    } else {
                       
                        console.log($('#message-' + history).length);
                            $('#message-' + history).addClass('chat-error');
                            $('.gemini-reply').text('Failed to get response.');
                    }
                });
            }

            function copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(() => {
                    console.log('Text copied to clipboard');
                }).catch(err => {
                    console.error('Failed to copy text: ', err);
                });
            };

            function insertChatMessage(message, isUser = true, isContent, id) {
                const chatBox = $('#chat-box');
                let messageClass = isUser ? 'user-message' : 'gemini-message';
                const messageButton= isUser ? 'hidden' :'';

                if (isContent) {
                    messageClass = 'gemini-message-content';
                } 

                const messageText = escapeHTML(message);
                id = chat_history_length++;
                
                if(!isUser) {
                    // If it's the first user message, clear the chat history
                    message = marked.parse(messageText, { sanitize: true ,
                    gfm: true, breaks: true, smartLists: true, smartypants: true });
                }else {
                    // If it's a user message, sanitize it
                    message = DOMPurify.sanitize(messageText);
                }

                chatBox.append(
                    `<div class="${messageClass}">
                        <div id="message-${id}" class="message-text" >
                            ${message}
                        </div>
                        <div class="button-wrapper ${messageButton}" >
                            <button class="button-reply" onclick='copyToClipboard(\`${messageText}\`)'>
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="icon-md-heavy"><path fill-rule="evenodd" clip-rule="evenodd" d="M7 5C7 3.34315 8.34315 2 10 2H19C20.6569 2 22 3.34315 22 5V14C22 15.6569 20.6569 17 19 17H17V19C17 20.6569 15.6569 22 14 22H5C3.34315 22 2 20.6569 2 19V10C2 8.34315 3.34315 7 5 7H7V5ZM9 7H14C15.6569 7 17 8.34315 17 10V15H19C19.5523 15 20 14.5523 20 14V5C20 4.44772 19.5523 4 19 4H10C9.44772 4 9 4.44772 9 5V7ZM5 9C4.44772 9 4 9.44772 4 10V19C4 19.5523 4.44772 20 5 20H14C14.5523 20 15 19.5523 15 19V10C15 9.44772 14.5523 9 14 9H5Z" fill="currentColor"></path></svg>
                            </button>
                        </div>
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
