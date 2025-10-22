<?php
/**
 * Funcionalidad pública del plugin.
 *
 * @package AI_Voice_Text_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Voice_Text_Widget_Public {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_widget' ) );
    }

    public function enqueue_assets() {
        if ( ! get_option( 'ai_widget_enabled', true ) ) {
            return;
        }

        wp_enqueue_style(
            'ai-widget-public',
            AI_VOICE_TEXT_WIDGET_PLUGIN_URL . 'public/css/widget-style.css',
            array(),
            AI_VOICE_TEXT_WIDGET_VERSION
        );

        wp_enqueue_script(
            'ai-widget-vapi',
            AI_VOICE_TEXT_WIDGET_PLUGIN_URL . 'public/js/widget-vapi.js',
            array(),
            AI_VOICE_TEXT_WIDGET_VERSION,
            true
        );

        wp_localize_script( 'ai-widget-vapi', 'aiWidgetData', array(
            'apiUrl' => rest_url( 'ai-widget/v1' ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'provider' => get_option( 'ai_widget_provider', 'vapi' ),
            'vapiPublicKey' => get_option( 'ai_widget_vapi_public_key', '' ),
            'vapiAssistantId' => get_option( 'ai_widget_vapi_assistant_id', '' ),
            'elevenlabsApiKey' => get_option( 'ai_widget_elevenlabs_api_key', '' ),
            'elevenlabsVoiceId' => get_option( 'ai_widget_elevenlabs_voice_id', '' ),
            'primaryColor' => get_option( 'ai_widget_primary_color', '#76b4e3' ),
            'secondaryColor' => get_option( 'ai_widget_secondary_color', '#009bf0' ),
            'position' => get_option( 'ai_widget_position', 'bottom-right' ),
            'welcomeMessage' => get_option( 'ai_widget_welcome_message', '¡Hola! 👋 ¿Cómo le gustaría interactuar?' ),
            'placeholder' => get_option( 'ai_widget_placeholder', 'Escribe tu mensaje...' ),
            'assistantName' => get_option( 'ai_widget_assistant_name', 'Workfluz Assistant' ),
            'logoSvg' => get_option( 'ai_widget_logo_svg', '' ),
            'voiceEnabled' => get_option( 'ai_widget_voice_enabled', true ),
            'textEnabled' => get_option( 'ai_widget_text_enabled', true ),
        ) );
    }

    public function render_widget() {
        if ( ! get_option( 'ai_widget_enabled', true ) ) {
            return;
        }
        // El widget se renderiza via JavaScript
    }
}
