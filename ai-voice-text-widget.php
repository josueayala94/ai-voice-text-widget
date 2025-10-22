<?php
/**
 * Plugin Name: AI Widget by Workfluz
 * Plugin URI: https://workfluz.com
 * Description: Widget de IA con voz y texto usando VAPI, ElevenLabs y OpenAI. Incluye modelo freemium de alta calidad.
 * Version: 1.0.0
 * Author: Workfluz
 * Author URI: https://workfluz.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ai-voice-text-widget
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AI_VOICE_TEXT_WIDGET_VERSION', '1.0.0' );
define( 'AI_VOICE_TEXT_WIDGET_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_VOICE_TEXT_WIDGET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Activación del plugin.
 */
function activate_ai_voice_text_widget() {
    require_once AI_VOICE_TEXT_WIDGET_PLUGIN_DIR . 'includes/class-activator.php';
    AI_Voice_Text_Widget_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_ai_voice_text_widget' );

/**
 * Desactivación del plugin.
 */
function deactivate_ai_voice_text_widget() {
    require_once AI_VOICE_TEXT_WIDGET_PLUGIN_DIR . 'includes/class-deactivator.php';
    AI_Voice_Text_Widget_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_ai_voice_text_widget' );

/**
 * Carga las clases principales.
 */
require AI_VOICE_TEXT_WIDGET_PLUGIN_DIR . 'includes/class-database.php';
require AI_VOICE_TEXT_WIDGET_PLUGIN_DIR . 'includes/class-ai-engine.php';
require AI_VOICE_TEXT_WIDGET_PLUGIN_DIR . 'includes/class-freemium.php';
require AI_VOICE_TEXT_WIDGET_PLUGIN_DIR . 'includes/class-analytics.php';

/**
 * Carga la administración si estamos en el admin.
 */
if ( is_admin() ) {
    require AI_VOICE_TEXT_WIDGET_PLUGIN_DIR . 'admin/class-admin.php';
    require AI_VOICE_TEXT_WIDGET_PLUGIN_DIR . 'admin/class-settings.php';
    
    $admin = new AI_Voice_Text_Widget_Admin();
    $settings = new AI_Voice_Text_Widget_Settings();
    
    add_action( 'admin_init', array( $settings, 'register_settings' ) );
}

/**
 * Carga el frontend.
 */
require AI_VOICE_TEXT_WIDGET_PLUGIN_DIR . 'public/class-public.php';
$public = new AI_Voice_Text_Widget_Public();

/**
 * Registra los endpoints de la API REST.
 */
require AI_VOICE_TEXT_WIDGET_PLUGIN_DIR . 'includes/class-api.php';
$api = new AI_Voice_Text_Widget_API();
add_action( 'rest_api_init', array( $api, 'register_routes' ) );
