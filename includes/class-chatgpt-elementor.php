<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ChatGPT_WPML_Elementor {
    public function __construct() {
        add_action('elementor/editor/footer', [$this, 'inject_translate_button']);
        add_action('wp_ajax_chatgpt_wpml_translate_elementor', [$this, 'handle_elementor_translation']);
    }

    public function inject_translate_button() {
        if ( ! current_user_can('edit_posts') ) return;

        $service = get_option('chatgpt_wpml_service', 'openai');
        $api_key = ($service == 'gemini') ? get_option('chatgpt_wpml_gemini_api_key') : get_option('chatgpt_wpml_api_key');
        
        if (empty($api_key)) return;

        $post_id = get_the_ID();
        if ( ! $post_id ) return;

        $languages = apply_filters('wpml_active_languages', NULL, ['skip_missing' => 0]);
        $current_lang = apply_filters('wpml_post_language_details', NULL, $post_id)['language_code'] ?? '';
        
        // **MODIFIED:** Clarified service name
        $service_name = ($service == 'gemini') ? 'Gemini (2.5 Flash)' : 'OpenAI (GPT-4o mini)';
        ?>
        <style>
        #chatgpt-elementor-panel {
            position: fixed;
            bottom: 20px;
            right: 25px;
            background: #1e1e1e;
            color: #fff;
            padding: 10px 15px;
            border-radius: 6px;
            z-index: 9999;
            font-size: 13px;
            min-width: 220px;
        }
        #chatgpt-elementor-panel select, #chatgpt-elementor-panel button {
            margin-top: 6px;
            width: 100%;
        }
        #chatgpt-elementor-panel button {
            background: #0085ba;
            color: #fff;
            border: none;
            padding: 5px 0;
            border-radius: 4px;
            cursor: pointer;
        }
        #chatgpt-elementor-panel #chatgpt-elementor-status {
             margin-top:8px; 
             font-size: 12px;
        }
        #chatgpt-elementor-panel #chatgpt-elementor-status a {
            color: #00a0d2;
        }
        </style>
        <div id="chatgpt-elementor-panel">
            <strong>AI WPML Translator</strong><br>
            <select id="chatgpt-elementor-lang">
                <?php foreach ($languages as $code => $lang) {
                    if ($code !== $current_lang) echo '<option value="'.$code.'">'.$lang['native_name'].'</option>';
                } ?>
            </select>
            <button id="chatgpt-elementor-btn">Translate with <?php echo $service_name; ?></button>
            <div id="chatgpt-elementor-status"></div>
        </div>

        <script>
        jQuery(function($){
            $('#chatgpt-elementor-btn').on('click', function(){
                const btn = $(this);
                const lang = $('#chatgpt-elementor-lang').val();
                const post_id = elementor.config.document.id;
                const statusDiv = $('#chatgpt-elementor-status');
                
                statusDiv.text('Translating...');
                btn.prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'chatgpt_wpml_translate_elementor',
                    post_id: post_id,
                    target_lang: lang,
                }, function(res){
                     if (res.success) {
                        let links = ' <br><a href="'+res.data.edit_link+'" target="_blank">Edit</a> | <a href="'+res.data.view_link+'" target="_blank">View</a>';
                        statusDiv.html('<strong style="color:#7ad03a;">' + res.data.message + '</strong>' + links);
                    } else {
                        // Display the specific error message from the API
                        statusDiv.html('<strong style="color:#dd3d36;">Error: ' + res.data + '</strong>');
                    }
                    btn.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    public function handle_elementor_translation() {
        $post_id = intval($_POST['post_id']);
        $target_lang = sanitize_text_field($_POST['target_lang']);

        $source_lang = apply_filters('wpml_post_language_details', NULL, $post_id)['language_code'];
        $content = get_post_field('post_content', $post_id);
        $title   = get_post_field('post_title', $post_id);

        $prompt = "Translate the following text from {$source_lang} to {$target_lang} for a WordPress post. Return *only* a JSON object with 'title' and 'content' keys.\n\n"
                . "Title: {$title}\n\nContent:\n{$content}";

        // Use the new public method from the main class
        $translator = new ChatGPT_WPML_Translator();
        $translation_result = $translator->do_translation($prompt);

        if ( !$translation_result['success'] ) {
            wp_send_json_error( $translation_result['message'] ); 
            return;
        }

        $translation = $translation_result['translation'];
        $usage_message = $translation_result['message'];

        $translated_post_id = apply_filters('wpml_object_id', $post_id, 'post', false, $target_lang);

        if (!$translated_post_id) {
            $translated_post_id = wp_insert_post([
                'post_title'   => $translation['title'] ?? $title,
                'post_content' => $translation['content'] ?? $content,
                'post_status'  => 'publish',
                'post_type'    => get_post_type($post_id)
            ]);

            do_action('wpml_set_element_language_details', [
                'element_id'    => $translated_post_id,
                'element_type'  => 'post_' . get_post_type($post_id),
                'trid'          => apply_filters('wpml_element_trid', NULL, $post_id, 'post_' . get_post_type($post_id)),
                'language_code' => $target_lang,
                'source_language_code' => $source_lang
            ]);
            
            $elementor_data = get_post_meta($post_id, '_elementor_data', true);
            if ($elementor_data) {
                update_post_meta($translated_post_id, '_elementor_data', $elementor_data);
                update_post_meta($translated_post_id, '_elementor_edit_mode', 'builder');
            }

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
}