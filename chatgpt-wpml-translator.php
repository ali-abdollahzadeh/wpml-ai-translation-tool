<?php
/**
 * Plugin Name: AI Translator for WPML
 * Description: Translate posts, pages, products, or Elementor content manually using OpenAI (ChatGPT), Google Gemini, and WPML.
 * Version: 1.3.6
 * Author: AliABZ
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CHATGPT_WPML_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHATGPT_WPML_URL', plugin_dir_url( __FILE__ ) );

require_once CHATGPT_WPML_PATH . 'includes/class-chatgpt-translator.php';

add_action('plugins_loaded', function() {
    if ( defined('ICL_SITEPRESS_VERSION') ) {
        new AI_Powered_WPML_Translator();

        // Elementor integration if Elementor is active
        if ( did_action('elementor/loaded') ) {
            require_once CHATGPT_WPML_PATH . 'includes/class-chatgpt-elementor.php';
            new ChatGPT_WPML_Elementor();
        }

    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>AI Translator for WPML</strong> requires WPML to be installed and active.</p></div>';
        });
    }
});