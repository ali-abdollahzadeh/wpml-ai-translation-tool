<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AI_Powered_WPML_Translator {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_translate_metabox']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_chatgpt_wpml_translate', [$this, 'handle_ajax_translation']);
    }

    public function add_settings_page() {
        add_options_page(
            'AI Translator for WPML',
            'AI Translator for WPML',
            'manage_options',
            'chatgpt-wpml-translator',
            [$this, 'settings_page_html']
        );
    }

    public function register_settings() {
        register_setting('chatgpt_wpml_options', 'chatgpt_wpml_service', [
            'sanitize_callback' => 'sanitize_text_field',
            'type' => 'string',
        ]);
        register_setting('chatgpt_wpml_options', 'chatgpt_wpml_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
            'type' => 'string',
        ]);
        register_setting('chatgpt_wpml_options', 'chatgpt_wpml_gemini_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
            'type' => 'string',
        ]);
    }

    public function settings_page_html() {
        ?>
        <div class="wrap">
            <h1>AI Translator for WPML</h1>
            <form method="POST" action="options.php">
                <?php
                settings_fields('chatgpt_wpml_options');
                $service = esc_attr(get_option('chatgpt_wpml_service', 'openai'));
                $openai_key = esc_attr(get_option('chatgpt_wpml_api_key', ''));
                $gemini_key = esc_attr(get_option('chatgpt_wpml_gemini_api_key', ''));
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="chatgpt_wpml_service">Translation Service</label></th>
                        <td>
                            <select id="chatgpt_wpml_service" name="chatgpt_wpml_service">
                                <option value="openai" <?php selected($service, 'openai'); ?>>OpenAI (gpt-4o-mini)</option>
                                <option value="gemini" <?php selected($service, 'gemini'); ?>>Google (gemini-2.5-flash)</option>
                            </select>
                            <p class="description">Select which AI service to use for translations.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="chatgpt_wpml_api_key">OpenAI API Key</label></th>
                        <td>
                            <input type="text" id="chatgpt_wpml_api_key" name="chatgpt_wpml_api_key" value="<?php echo esc_attr($openai_key); ?>" size="50"/>
                            <p class="description">Required if using OpenAI. Uses the <strong>gpt-4o-mini</strong> model.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="chatgpt_wpml_gemini_api_key">Google Gemini API Key</label></th>
                        <td>
                            <input type="text" id="chatgpt_wpml_gemini_api_key" name="chatgpt_wpml_gemini_api_key" value="<?php echo esc_attr($gemini_key); ?>" size="50"/>
                            <p class="description">Required if using Google Gemini. Uses the <strong>gemini-2.5-flash</strong> model. Get one from Google AI Studio.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function add_translate_metabox() {
        $post_types = ['post', 'page', 'product'];
        foreach ($post_types as $type) {
            add_meta_box(
                'chatgpt_wpml_translator_box',
                'AI WPML Translator',
                [$this, 'render_metabox'],
                $type,
                'side',
                'default'
            );
        }
    }

    public function render_metabox($post) {
        $service = get_option('chatgpt_wpml_service', 'openai');
        $api_key = ($service == 'gemini') ? get_option('chatgpt_wpml_gemini_api_key') : get_option('chatgpt_wpml_api_key');

        if (empty($api_key)) {
            echo '<p style="color:red;">Please set your API key in <a href="'.esc_url(admin_url('options-general.php?page=chatgpt-wpml-translator')).'" >Settings → AI Translator for WPML</a>.</p>';
            return;
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WPML's filters
        $languages = apply_filters('wpml_active_languages', NULL, ['skip_missing' => 0]);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WPML's filters
        $current_lang = apply_filters('wpml_post_language_details', NULL, $post->ID)['language_code'] ?? '';

        if (!$languages) {
            echo '<p>No WPML languages found.</p>';
            return;
        }

        $service_name = ($service == 'gemini') ? 'Gemini (2.5 Flash)' : 'OpenAI (GPT-4o mini)';

        echo '<p>Select language to translate into:</p>';
        echo '<select id="chatgpt-wpml-lang" style="width:100%;">';
        foreach ($languages as $code => $lang) {
            if ($code !== $current_lang) {
                echo '<option value="'.esc_attr($code).'">'.esc_html($lang['native_name']).'</option>';
            }
        }
        echo '</select>';
        
        echo '<div id="chatgpt-wpml-token-estimate" style="margin-top:10px; font-style: italic; font-size: 12px; color: #666;">Calculating tokens...</div>';
        
        echo '<button type="button" id="chatgpt-wpml-btn" class="button button-primary" style="margin-top:10px;">Translate with ' . esc_html($service_name) . '</button>';
        echo '<div id="chatgpt-wpml-status" style="margin-top:10px; font-size:12px;"></div>';

        wp_nonce_field('chatgpt_wpml_translate', 'chatgpt_wpml_nonce');
        ?>
        <script>
        jQuery(function($){
            
            function updateTokenEstimate() {
                let title = '';
                let contentText = '';

                if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                    title = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
                    let contentHtml = wp.data.select('core/editor').getEditedPostAttribute('content') || '';
                    
                    let tempDiv = document.createElement('div');
                    tempDiv.innerHTML = contentHtml;
                    contentText = tempDiv.textContent || tempDiv.innerText || '';

                } 
                else {
                    title = $('#title').val() || '';
                    if (window.tinymce && tinymce.get('content')) {
                        contentText = tinymce.get('content').getContent({ format: 'text' }) || '';
                    } else {
                        contentText = $('#content').val() || ''; 
                    }
                }
                
                let text = title + ' ' + contentText;
                let charCount = text.replace(/\s+/g, ' ').trim().length; 
                let tokenEstimate = Math.ceil(charCount / 4); 
                
                $('#chatgpt-wpml-token-estimate').text('Estimated Input Tokens: ~' + tokenEstimate);
            }

            function debounce(func, wait) {
                let timeout;
                return function(...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            }
            
            const debouncedUpdate = debounce(updateTokenEstimate, 300);

            setTimeout(updateTokenEstimate, 500); 

            $('#title, #content').on('keyup input change', debouncedUpdate);
            if (window.tinymce && tinymce.get('content')) {
                tinymce.get('content').on('keyup input change', debouncedUpdate);
            }
            
            if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
                wp.data.subscribe(debouncedUpdate);
            }
            
            $('#chatgpt-wpml-btn').on('click', function(){
                const btn = $(this);
                const lang = $('#chatgpt-wpml-lang').val();
                const post_id = <?php echo esc_js($post->ID); ?>;
                const nonce = $('#chatgpt_wpml_nonce').val();
                const statusDiv = $('#chatgpt-wpml-status');
                
                statusDiv.html('Translating...');
                btn.prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'chatgpt_wpml_translate',
                    post_id: post_id,
                    target_lang: lang,
                    nonce: nonce
                }, function(res){
                    if (res.success) {
                        let links = ' <br><strong>Links:</strong> <a href="'+res.data.edit_link+'" target="_blank">Edit</a> | <a href="'+res.data.view_link+'" target="_blank">View</a>';
                        statusDiv.html('<strong style="color:green;">' + res.data.message + '</strong>' + links);
                    } else {
                        statusDiv.html('<strong style="color:red;">Error: ' + res.data + '</strong>');
                    }
                    btn.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    public function handle_ajax_translation() {
        check_ajax_referer('chatgpt_wpml_translate', 'nonce');
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $target_lang = isset($_POST['target_lang']) ? sanitize_text_field(wp_unslash($_POST['target_lang'])) : '';

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WPML's filters
        $source_lang = apply_filters('wpml_post_language_details', NULL, $post_id)['language_code'];
        $content = get_post_field('post_content', $post_id);
        $title   = get_post_field('post_title', $post_id);

        $prompt = "Translate the following text from {$source_lang} to {$target_lang} for a WordPress post. Return *only* a JSON object with 'title' and 'content' keys.\n\n"
                . "Title: {$title}\n\nContent:\n{$content}";

        $translation_result = $this->do_translation($prompt);

        if ( !$translation_result['success'] ) {
            wp_send_json_error( $translation_result['message'] ); 
            return;
        }

        $translation = $translation_result['translation'];
        $usage_message = $translation_result['message'];

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WPML's filters
        $translated_post_id = apply_filters('wpml_object_id', $post_id, 'post', false, $target_lang);

        if (!$translated_post_id) {
            $translated_post_id = wp_insert_post([
                'post_title'   => $translation['title'] ?? $title,
                'post_content' => $translation['content'] ?? $content,
                'post_status'  => 'publish',
                'post_type'    => get_post_type($post_id)
            ]);

            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WPML's actions/filters
            do_action('wpml_set_element_language_details', [
                'element_id'    => $translated_post_id,
                'element_type'  => 'post_' . get_post_type($post_id),
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WPML's filters
                'trid'          => apply_filters('wpml_element_trid', NULL, $post_id, 'post_' . get_post_type($post_id)),
                'language_code' => $target_lang,
                'source_language_code' => $source_lang
            ]);
        } else {
            wp_update_post([
                'ID'           => $translated_post_id,
                'post_title'   => $translation['title'],
                'post_content' => $translation['content']
            ]);
        }
        
        wp_send_json_success([
            'message' => $usage_message,
            'edit_link' => get_edit_post_link($translated_post_id, 'raw'),
            'view_link' => get_permalink($translated_post_id)
        ]);
    }

    public function do_translation($prompt) {
        $service = get_option('chatgpt_wpml_service', 'openai');

        if ($service == 'gemini') {
            return $this->gemini_translate($prompt);
        } else {
            return $this->chatgpt_translate($prompt);
        }
    }

    private function chatgpt_translate($prompt) {
        $api_key = get_option('chatgpt_wpml_api_key');
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => "You are a professional translator for WordPress content. Return *only* a JSON object with 'title' and 'content' keys."],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object']
            ]),
            'timeout' => 30 // **FIX:** Increased timeout from 5 to 30 seconds
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $error = $data['error']['message'] ?? 'API returned HTTP Error ' . $code;
            return ['success' => false, 'message' => $error];
        }

        $text = $data['choices'][0]['message']['content'] ?? null;
        if (empty($text)) {
            return ['success' => false, 'message' => 'API returned an empty response.'];
        }

        $json = json_decode($text, true);
        if ($json === null) {
            return ['success' => false, 'message' => 'Failed to decode JSON from API. Response: ' . esc_html($text)];
        }
        
        $tokens = $data['usage']['total_tokens'] ?? 0;
        $message = "Translated with OpenAI (GPT-4o mini). Tokens: {$tokens}.";

        return ['success' => true, 'translation' => $json, 'message' => $message];
    }

    private function gemini_translate($prompt) {
        $api_key = get_option('chatgpt_wpml_gemini_api_key');
        
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

        $response = wp_remote_post($url, [
            'headers' => [ 'Content-Type'  => 'application/json' ],
            'body' => json_encode([
                'contents' => [
                    ['role' => 'user', 'parts' => [
                        ['text' => $prompt]
                    ]]
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'responseMimeType' => 'application/json',
                ]
            ]),
            'timeout' => 30 // **FIX:** Increased timeout from 5 to 30 seconds
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $error = $data['error']['message'] ?? 'API returned HTTP Error ' . $code;
            return ['success' => false, 'message' => $error];
        }

        if (empty($data['candidates'])) {
            $block_reason = $data['promptFeedback']['blockReason'] ?? 'Unknown error';
            return ['success' => false, 'message' => 'Translation blocked by Gemini. Reason: ' . $block_reason];
        }
        
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = str_replace(['```json', '```'], '', $text); 
        $json = json_decode(trim($text), true);

        if ($json === null) {
            return ['success' => false, 'message' => 'Failed to decode JSON from Gemini. Response: ' . esc_html(trim($text))];
        }
        
        $tokens = $data['usageMetadata']['totalTokenCount'] ?? 0;
        $message = "Translated with Gemini (2.5 Flash). Tokens: {$tokens}.";

        return ['success' => true, 'translation' => $json, 'message' => $message];
    }
}