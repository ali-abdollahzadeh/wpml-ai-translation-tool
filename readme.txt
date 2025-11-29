=== AI Translator for WPML ===
Contributors: Ali ABZ
Tags: translation, wpml, openai, chatgpt, multilingual  
Requires at least: 6.0  
Tested up to: 6.8  
Requires PHP: 7.4  
Stable tag: 1.3.6
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Automatically translate your WordPress posts into multiple languages using WPML, OpenAI (ChatGPT), and Google Gemini.

== Description ==

AI-Powered WPML Translator bridges WPML with the OpenAI and Google Gemini APIs, automatically translating posts into all active languages when you publish or update a post.

**Main Features:**
* Works with WPML’s language management system.  
* Automatically translates posts when published or updated.  
* Uses OpenAI’s ChatGPT (`gpt-4o-mini`) or Google Gemini (`gemini-2.5-flash`) models for high-quality translations.  
* Separate settings page for managing API keys.  
* Updates existing translations or creates new ones when needed.  

**How it works:**
1. When you publish or update a post, the plugin detects your site’s active WPML languages.  
2. For each target language, it sends the post content and title to ChatGPT.  
3. The translated post is automatically created or updated in that language.  

== Installation ==

1. Upload the plugin folder `chatgpt-wpml-translator` to the `/wp-content/plugins/` directory.  
2. Activate the plugin through the **Plugins** menu in WordPress.  
3. Go to **Settings → AI-Powered WPML** and enter your OpenAI and/or Gemini API keys.  
4. Make sure WPML is installed and configured with your desired languages.  
5. Create or update a post — translations will be generated automatically.  

== Frequently Asked Questions ==

= Does it work with custom post types? =  
By default, it handles regular posts. You can easily extend it to support custom post types.  

= Does it cost money to use? =  
Using ChatGPT translations requires an OpenAI API key and will incur API usage costs according to your OpenAI plan.  

= Which AI model is used? =  
Currently uses the `gpt-4o-mini` model (OpenAI) or `gemini-2.5-flash` model (Google Gemini) for best balance of quality and cost.  

= Can I manually trigger translations? =  
Not yet, but a “Translate Now” button can be added in a future update.  

== Screenshots ==

1. Settings page where you enter your OpenAI API key.  
2. Example of automatic translations appearing in WPML language list.  

== Changelog ==

= 1.0.0 =
* Initial release.  
* Automatic post translation on save.  
* WPML integration for multiple languages.  
* Settings page for API key.  

== Upgrade Notice ==

= 1.0.0 =
First stable release with automatic translation support.  

== License ==

This plugin is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 or later.
