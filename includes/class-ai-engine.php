<?php
/**
 * Motor de IA - Procesa los mensajes y genera respuestas.
 *
 * @package AI_Voice_Text_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Voice_Text_Widget_AI_Engine {

    private $provider;
    private $model;
    private $api_key;
    private $personality;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->provider = get_option( 'ai_widget_ai_provider', 'vapi' );
        $this->model = get_option( 'ai_widget_model', '' );
        $this->api_key = get_option( 'ai_widget_api_key', '' );
        $this->personality = get_option( 'ai_widget_personality', 'friendly' );
    }

    /**
     * Procesa un mensaje y genera una respuesta.
     *
     * @param string $message Mensaje del usuario.
     * @param string $session_id ID de sesión.
     * @return array|WP_Error Respuesta de la IA o error.
     */
    public function process_message( $message, $session_id ) {
        $start_time = microtime( true );

        // Obtener contexto previo
        $context = $this->get_conversation_context( $session_id );

        // Generar respuesta según el proveedor
        switch ( $this->provider ) {
            case 'vapi':
                $response = $this->process_vapi( $message, $context );
                break;
            case 'openai':
                $response = $this->process_openai( $message, $context );
                break;
            case 'elevenlabs':
                $response = $this->process_elevenlabs( $message, $context );
                break;
            case 'anthropic':
                $response = $this->process_anthropic( $message, $context );
                break;
            case 'local':
                $response = $this->process_local( $message, $context );
                break;
            default:
                $response = $this->process_fallback( $message );
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $end_time = microtime( true );
        $response_time = $end_time - $start_time;

        return array(
            'text' => $response['text'],
            'tokens' => $response['tokens'] ?? 0,
            'time' => $response_time
        );
    }

    /**
     * Procesa con VAPI (para chat de texto).
     */
    private function process_vapi( $message, $context ) {
        // VAPI se maneja principalmente desde el frontend para voz
        // Para chat de texto, usamos OpenAI si está configurado
        $openai_key = get_option( 'ai_widget_openai_api_key', '' );
        if ( ! empty( $openai_key ) ) {
            return $this->process_openai_direct( $message, $context, $openai_key );
        }
        
        return $this->process_fallback( $message );
    }

    /**
     * Procesa con ElevenLabs (para chat de texto con TTS).
     */
    private function process_elevenlabs( $message, $context ) {
        // ElevenLabs se usa principalmente para TTS
        // Para chat, podemos usar OpenAI o fallback
        $openai_key = get_option( 'ai_widget_openai_api_key', '' );
        if ( ! empty( $openai_key ) ) {
            return $this->process_openai_direct( $message, $context, $openai_key );
        }
        
        return $this->process_fallback( $message );
    }

    /**
     * Procesa con OpenAI directamente.
     */
    private function process_openai_direct( $message, $context, $api_key ) {
        // Verificar si se debe usar un asistente de OpenAI
        $use_assistant = get_option( 'ai_widget_use_openai_assistant', '0' );
        $assistant_id = get_option( 'ai_widget_openai_assistant_id', '' );
        
        if ( $use_assistant === '1' && ! empty( $assistant_id ) ) {
            return $this->process_with_assistant( $message, $context, $api_key, $assistant_id );
        }
        
        // Usar Chat Completions API con system prompt
        $system_message = $this->get_system_prompt();
        
        $messages = array(
            array( 'role' => 'system', 'content' => $system_message )
        );

        foreach ( array_slice( $context, -5 ) as $msg ) {
            $messages[] = array(
                'role' => $msg['message_type'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['message_text']
            );
        }

        $messages[] = array( 'role' => 'user', 'content' => $message );

        $body = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 500,
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        
        if ( $response_code !== 200 ) {
            return new WP_Error( 'api_error', __( 'Error al comunicarse con OpenAI.', 'ai-voice-text-widget' ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'invalid_response', __( 'Respuesta inválida de OpenAI.', 'ai-voice-text-widget' ) );
        }

        return array(
            'text' => $body['choices'][0]['message']['content'],
            'tokens' => $body['usage']['total_tokens'] ?? 0
        );
    }

    /**
     * Procesa con OpenAI Assistants API.
     */
    private function process_with_assistant( $message, $context, $api_key, $assistant_id ) {
        // Crear un thread
        $thread_response = wp_remote_post( 'https://api.openai.com/v1/threads', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ),
            'body' => wp_json_encode( array() ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $thread_response ) ) {
            return $thread_response;
        }

        $thread_body = json_decode( wp_remote_retrieve_body( $thread_response ), true );
        $thread_id = $thread_body['id'] ?? null;

        if ( ! $thread_id ) {
            return new WP_Error( 'assistant_error', __( 'No se pudo crear el thread del asistente.', 'ai-voice-text-widget' ) );
        }

        // Agregar mensaje al thread
        $message_response = wp_remote_post( 'https://api.openai.com/v1/threads/' . $thread_id . '/messages', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ),
            'body' => wp_json_encode( array(
                'role' => 'user',
                'content' => $message
            ) ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $message_response ) ) {
            return $message_response;
        }

        // Ejecutar el asistente
        $run_response = wp_remote_post( 'https://api.openai.com/v1/threads/' . $thread_id . '/runs', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ),
            'body' => wp_json_encode( array(
                'assistant_id' => $assistant_id
            ) ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $run_response ) ) {
            return $run_response;
        }

        $run_body = json_decode( wp_remote_retrieve_body( $run_response ), true );
        $run_id = $run_body['id'] ?? null;

        if ( ! $run_id ) {
            return new WP_Error( 'assistant_error', __( 'No se pudo ejecutar el asistente.', 'ai-voice-text-widget' ) );
        }

        // Esperar a que el asistente complete (polling)
        $max_attempts = 30;
        $attempt = 0;
        
        while ( $attempt < $max_attempts ) {
            sleep( 1 );
            
            $status_response = wp_remote_get( 'https://api.openai.com/v1/threads/' . $thread_id . '/runs/' . $run_id, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'OpenAI-Beta' => 'assistants=v2'
                ),
                'timeout' => 15,
            ) );

            if ( is_wp_error( $status_response ) ) {
                return $status_response;
            }

            $status_body = json_decode( wp_remote_retrieve_body( $status_response ), true );
            $status = $status_body['status'] ?? 'unknown';

            if ( $status === 'completed' ) {
                break;
            } elseif ( in_array( $status, array( 'failed', 'cancelled', 'expired' ) ) ) {
                return new WP_Error( 'assistant_error', __( 'El asistente falló: ' . $status, 'ai-voice-text-widget' ) );
            }

            $attempt++;
        }

        if ( $attempt >= $max_attempts ) {
            return new WP_Error( 'assistant_timeout', __( 'El asistente tardó demasiado en responder.', 'ai-voice-text-widget' ) );
        }

        // Obtener los mensajes del thread
        $messages_response = wp_remote_get( 'https://api.openai.com/v1/threads/' . $thread_id . '/messages', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'OpenAI-Beta' => 'assistants=v2'
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $messages_response ) ) {
            return $messages_response;
        }

        $messages_body = json_decode( wp_remote_retrieve_body( $messages_response ), true );
        $messages_data = $messages_body['data'] ?? array();

        // Obtener la última respuesta del asistente
        foreach ( $messages_data as $msg ) {
            if ( $msg['role'] === 'assistant' ) {
                $content = $msg['content'][0]['text']['value'] ?? '';
                if ( $content ) {
                    return array(
                        'text' => $content,
                        'tokens' => 0 // Assistants API no devuelve token count directamente
                    );
                }
            }
        }

        return new WP_Error( 'assistant_error', __( 'No se pudo obtener la respuesta del asistente.', 'ai-voice-text-widget' ) );
    }

    /**
     * Procesa con OpenAI.
     */
    private function process_openai( $message, $context ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'No se ha configurado la API key de OpenAI.', 'ai-voice-text-widget' ) );
        }

        $system_message = $this->get_system_prompt();
        
        $messages = array(
            array( 'role' => 'system', 'content' => $system_message )
        );

        // Añadir contexto previo (últimos 5 mensajes)
        foreach ( array_slice( $context, -5 ) as $msg ) {
            $messages[] = array(
                'role' => $msg['message_type'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['message_text']
            );
        }

        // Añadir mensaje actual
        $messages[] = array( 'role' => 'user', 'content' => $message );

        $body = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 500,
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        
        if ( $response_code !== 200 ) {
            return new WP_Error( 'api_error', __( 'Error al comunicarse con OpenAI.', 'ai-voice-text-widget' ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'invalid_response', __( 'Respuesta inválida de OpenAI.', 'ai-voice-text-widget' ) );
        }

        return array(
            'text' => $body['choices'][0]['message']['content'],
            'tokens' => $body['usage']['total_tokens'] ?? 0
        );
    }

    /**
     * Procesa con Anthropic Claude.
     */
    private function process_anthropic( $message, $context ) {
        // Implementación futura
        return new WP_Error( 'not_implemented', __( 'Anthropic Claude aún no está implementado.', 'ai-voice-text-widget' ) );
    }

    /**
     * Procesa con modelo local.
     */
    private function process_local( $message, $context ) {
        // Implementación futura con Ollama o LocalAI
        return new WP_Error( 'not_implemented', __( 'Modelo local aún no está implementado.', 'ai-voice-text-widget' ) );
    }

    /**
     * Respuesta de fallback (respuestas predefinidas).
     */
    private function process_fallback( $message ) {
        $responses = array(
            'hola' => '¡Hola! 👋 ¿En qué puedo ayudarte hoy?',
            'ayuda' => 'Estoy aquí para ayudarte. Puedes preguntarme lo que quieras.',
            'gracias' => '¡De nada! Estoy aquí si necesitas algo más. 😊',
            'adiós' => '¡Hasta luego! Fue un placer ayudarte. 👋',
        );

        $message_lower = strtolower( trim( $message ) );
        
        foreach ( $responses as $key => $response ) {
            if ( strpos( $message_lower, $key ) !== false ) {
                return array( 'text' => $response, 'tokens' => 0 );
            }
        }

        return array(
            'text' => 'Gracias por tu mensaje. Por favor, configura una API key para obtener respuestas más inteligentes.',
            'tokens' => 0
        );
    }

    /**
     * Obtiene el contexto de la conversación.
     */
    private function get_conversation_context( $session_id ) {
        $database = new AI_Voice_Text_Widget_Database();
        return $database->get_recent_messages( $session_id, 10 );
    }

    /**
     * Obtiene el prompt del sistema según la personalidad.
     */
    private function get_system_prompt() {
        // Primero verificar si hay un system prompt personalizado de la pestaña System Prompt
        $use_assistant = get_option( 'ai_widget_use_openai_assistant', '0' );
        
        // Si no está usando asistente, verificar el system prompt personalizado
        if ( $use_assistant !== '1' ) {
            $system_prompt = get_option( 'ai_widget_system_prompt', '' );
            if ( ! empty( $system_prompt ) ) {
                return $system_prompt;
            }
        }
        
        // Fallback a los prompts predefinidos por personalidad (legacy)
        $prompts = array(
            'friendly' => 'Eres un asistente amigable y servicial. Respondes de manera clara, concisa y amable. Usa emojis ocasionalmente para ser más expresivo.',
            'professional' => 'Eres un asistente profesional y formal. Proporcionas respuestas precisas y bien estructuradas. Mantienes un tono respetuoso y corporativo.',
            'casual' => 'Eres un asistente casual y relajado. Hablas de manera informal y cercana, como un amigo. Usa lenguaje coloquial.',
            'technical' => 'Eres un asistente técnico especializado. Proporcionas respuestas detalladas con terminología técnica cuando sea apropiado. Eres preciso y específico.',
            'sales' => 'Eres un asistente de ventas persuasivo y entusiasta. Ayudas a los usuarios a encontrar lo que necesitan y destacas los beneficios de productos y servicios.',
            'support' => 'Eres un asistente de soporte técnico paciente y metódico. Ayudas a resolver problemas paso a paso, haciendo preguntas clarificadoras cuando es necesario.',
        );

        $custom_prompt = get_option( 'ai_widget_custom_prompt', '' );
        
        if ( ! empty( $custom_prompt ) ) {
            return $custom_prompt;
        }

        return $prompts[ $this->personality ] ?? $prompts['friendly'];
    }
}
