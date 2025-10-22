<?php
/**
 * Administración del plugin.
 *
 * @package AI_Voice_Text_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Voice_Text_Widget_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'AI Widget', 'ai-voice-text-widget' ),
            __( 'AI Widget', 'ai-voice-text-widget' ),
            'manage_options',
            'ai-voice-text-widget',
            array( $this, 'render_settings_page' ),
            'dashicons-format-chat',
            30
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        include AI_VOICE_TEXT_WIDGET_PLUGIN_DIR . 'admin/partials/settings-page.php';
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_ai-voice-text-widget' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        
        wp_enqueue_style(
            'ai-widget-admin',
            AI_VOICE_TEXT_WIDGET_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            AI_VOICE_TEXT_WIDGET_VERSION
        );
        
        wp_enqueue_script(
            'ai-widget-admin',
            AI_VOICE_TEXT_WIDGET_PLUGIN_URL . 'admin/js/admin-script.js',
            array( 'jquery', 'wp-color-picker' ),
            AI_VOICE_TEXT_WIDGET_VERSION,
            true
        );
    }
}
