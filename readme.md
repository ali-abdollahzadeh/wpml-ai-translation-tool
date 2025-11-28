# WPML GPT Translator

A lightweight tool that connects **WPML** with **OpenAI GPT models** to automate multilingual content creation inside WordPress.  
It sends text to GPT, receives high-quality translations, and updates WPML fields automatically.

## Features
- Automated translation using OpenAI GPT models  
- Works with WPML content (posts, pages, custom fields)  
- Faster workflow for multilingual websites  
- Improves translation consistency  
- Easy to configure and extend  

## Requirements
- WordPress with WPML installed  
- OpenAI API key  
- PHP 7.4+  

## Installation
1. Download the project files.  
2. Place the folder inside the `/wp-content/plugins/` directory.  
3. Activate the plugin from **WordPress → Plugins**.  
4. Add your OpenAI API key in the plugin settings.  

## Usage
1. Select the content you want to translate.  
2. Choose the target language in WPML.  
3. The tool sends the text to GPT and returns translations automatically.  

## Configuration
You can configure:
- Source and target languages  
- Model version  
- Translation prompt style  
- Fields to translate  

## Roadmap
- Batch translation support  
- Custom prompts per language  
- Support for WooCommerce product fields  
- Logging and usage statistics  

## License
MIT License  
Feel free to modify and adapt the project.
