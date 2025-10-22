<?php
/**
 * API REST del plugin.
 *
 * @package AI_Voice_Text_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Voice_Text_Widget_API {

    public function register_routes() {
        register_rest_route( 'ai-widget/v1', '/chat', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_chat' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'ai-widget/v1', '/usage', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_usage' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'ai-widget/v1', '/upgrade', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_upgrade' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function handle_chat( $request ) {
        $message = $request->get_param( 'message' );
        $session_id = $request->get_param( 'session_id' );

        if ( empty( $message ) || empty( $session_id ) ) {
            return new WP_Error( 'invalid_params', 'Parámetros inválidos', array( 'status' => 400 ) );
        }

        $database = new AI_Voice_Text_Widget_Database();
        
        if ( ! $database->can_send_message( $session_id ) ) {
            return new WP_Error( 'limit_reached', 'Límite de mensajes alcanzado', array( 'status' => 429 ) );
        }

        $database->get_or_create_user( $session_id );
        $database->save_message( $session_id, 'user', $message );

        $ai_engine = new AI_Voice_Text_Widget_AI_Engine();
        $response = $ai_engine->process_message( $message, $session_id );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $database->save_message( $session_id, 'ai', $response['text'], $response['tokens'], $response['time'] );
        $database->increment_message_count( $session_id );

        $usage = $database->get_usage( $session_id );

        return rest_ensure_response( array(
            'response' => $response['text'],
            'usage' => $usage,
        ) );
    }

    public function get_usage( $request ) {
        $session_id = $request->get_param( 'session_id' );

        if ( empty( $session_id ) ) {
            return new WP_Error( 'invalid_params', 'Parámetros inválidos', array( 'status' => 400 ) );
        }

        $database = new AI_Voice_Text_Widget_Database();
        $database->get_or_create_user( $session_id );
        $usage = $database->get_usage( $session_id );

        return rest_ensure_response( $usage );
    }

    public function handle_upgrade( $request ) {
        $session_id = $request->get_param( 'session_id' );
        $plan = $request->get_param( 'plan' );

        if ( empty( $session_id ) || empty( $plan ) ) {
            return new WP_Error( 'invalid_params', 'Parámetros inválidos', array( 'status' => 400 ) );
        }

        // Aquí iría la integración con Stripe o el sistema de pagos
        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Upgrade procesado (integración de pago pendiente)',
        ) );
    }
}
