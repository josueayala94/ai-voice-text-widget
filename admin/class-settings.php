<?php
/**
 * Configuración del plugin.
 *
 * @package AI_Voice_Text_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Voice_Text_Widget_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        // Agregar hooks para AJAX
        add_action( 'wp_ajax_ai_widget_load_assistants', array( $this, 'ajax_load_assistants' ) );
    }

    /**
     * Registra los ajustes.
     */
    public function register_settings() {
        // Todas las opciones en un solo grupo para simplificar
        $option_group = 'ai_widget_settings';

        // Configuración general
        register_setting( $option_group, 'ai_widget_enabled' );
        register_setting( $option_group, 'ai_widget_position' );
        register_setting( $option_group, 'ai_widget_primary_color' );
        register_setting( $option_group, 'ai_widget_secondary_color' );
        register_setting( $option_group, 'ai_widget_welcome_message' );
        register_setting( $option_group, 'ai_widget_placeholder' );
        register_setting( $option_group, 'ai_widget_assistant_name' );
        register_setting( $option_group, 'ai_widget_logo_svg' );

        // Configuración de proveedor de IA
        register_setting( $option_group, 'ai_widget_provider' );
        
        // VAPI
        register_setting( $option_group, 'ai_widget_vapi_public_key' );
        register_setting( $option_group, 'ai_widget_vapi_assistant_id' );
        
        // ElevenLabs
        register_setting( $option_group, 'ai_widget_elevenlabs_api_key' );
        register_setting( $option_group, 'ai_widget_elevenlabs_voice_id' );
        
        // OpenAI (para chat de texto)
        register_setting( $option_group, 'ai_widget_openai_api_key' );
        register_setting( $option_group, 'ai_widget_openai_model' );
        register_setting( $option_group, 'ai_widget_personality' );
        register_setting( $option_group, 'ai_widget_custom_prompt' );

        // System Prompt Configuration
        register_setting( $option_group, 'ai_widget_use_openai_assistant' );
        register_setting( $option_group, 'ai_widget_openai_assistant_id' );
        register_setting( $option_group, 'ai_widget_system_prompt' );

        // Configuración de modos
        register_setting( $option_group, 'ai_widget_voice_enabled' );
        register_setting( $option_group, 'ai_widget_text_enabled' );

        // Configuración freemium
        register_setting( $option_group, 'ai_widget_free_limit' );
    }

    /**
     * AJAX handler para cargar asistentes de OpenAI
     */
    public function ajax_load_assistants() {
        // Verificar nonce
        check_ajax_referer( 'ai_widget_load_assistants', 'nonce' );

        // Verificar permisos
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'No tienes permisos para realizar esta acción.' ) );
            return;
        }

        // Obtener API key
        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
        
        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => 'API key de OpenAI no configurada.' ) );
            return;
        }

        // Llamar a la API de OpenAI
        $response = wp_remote_get(
            'https://api.openai.com/v1/assistants',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'OpenAI-Beta'   => 'assistants=v2'
                ),
                'timeout' => 15
            )
        );

        // Verificar errores
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Error de conexión: ' . $response->get_error_message() ) );
            return;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $status_code !== 200 ) {
            $error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Error desconocido';
            wp_send_json_error( array( 'message' => 'Error de OpenAI: ' . $error_message ) );
            return;
        }

        // Extraer los asistentes
        $assistants = array();
        if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
            foreach ( $data['data'] as $assistant ) {
                $assistants[] = array(
                    'id'           => $assistant['id'],
                    'name'         => $assistant['name'],
                    'model'        => $assistant['model'],
                    'instructions' => isset( $assistant['instructions'] ) ? $assistant['instructions'] : ''
                );
            }
        }

        wp_send_json_success( array( 'assistants' => $assistants ) );
    }
}
