<?php
/**
 * Gestión de base de datos.
 *
 * @package AI_Voice_Text_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Voice_Text_Widget_Database {

    private $table_users;
    private $table_conversations;
    private $table_analytics;

    public function __construct() {
        global $wpdb;
        $this->table_users = $wpdb->prefix . 'ai_widget_users';
        $this->table_conversations = $wpdb->prefix . 'ai_widget_conversations';
        $this->table_analytics = $wpdb->prefix . 'ai_widget_analytics';
    }

    /**
     * Obtiene o crea un usuario del widget.
     */
    public function get_or_create_user( $session_id, $user_id = null ) {
        global $wpdb;

        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_users} WHERE session_id = %s",
            $session_id
        ) );

        if ( ! $user ) {
            $wpdb->insert(
                $this->table_users,
                array(
                    'user_id' => $user_id,
                    'session_id' => $session_id,
                    'plan' => 'free',
                    'messages_count' => 0,
                    'messages_limit' => get_option( 'ai_widget_free_limit', 100 ),
                ),
                array( '%d', '%s', '%s', '%d', '%d' )
            );

            $user = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$this->table_users} WHERE session_id = %s",
                $session_id
            ) );
        }

        return $user;
    }

    /**
     * Guarda un mensaje en la conversación.
     */
    public function save_message( $session_id, $message_type, $message_text, $tokens = 0, $response_time = 0, $user_id = null ) {
        global $wpdb;

        $wpdb->insert(
            $this->table_conversations,
            array(
                'user_id' => $user_id,
                'session_id' => $session_id,
                'message_type' => $message_type,
                'message_text' => $message_text,
                'tokens_used' => $tokens,
                'response_time' => $response_time,
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%f' )
        );

        return $wpdb->insert_id;
    }

    /**
     * Incrementa el contador de mensajes de un usuario.
     */
    public function increment_message_count( $session_id ) {
        global $wpdb;

        $wpdb->query( $wpdb->prepare(
            "UPDATE {$this->table_users} 
            SET messages_count = messages_count + 1,
                last_message_date = NOW()
            WHERE session_id = %s",
            $session_id
        ) );
    }

    /**
     * Obtiene mensajes recientes de una sesión.
     */
    public function get_recent_messages( $session_id, $limit = 10 ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_conversations}
            WHERE session_id = %s
            ORDER BY created_at DESC
            LIMIT %d",
            $session_id,
            $limit
        ), ARRAY_A );
    }

    /**
     * Verifica si un usuario puede enviar más mensajes.
     */
    public function can_send_message( $session_id ) {
        global $wpdb;

        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT messages_count, messages_limit, plan FROM {$this->table_users}
            WHERE session_id = %s",
            $session_id
        ) );

        if ( ! $user ) {
            return false;
        }

        if ( $user->plan === 'premium' || $user->plan === 'unlimited' ) {
            return true;
        }

        return $user->messages_count < $user->messages_limit;
    }

    /**
     * Obtiene el uso restante de un usuario.
     */
    public function get_usage( $session_id ) {
        global $wpdb;

        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT messages_count, messages_limit, plan FROM {$this->table_users}
            WHERE session_id = %s",
            $session_id
        ) );

        if ( ! $user ) {
            return array(
                'used' => 0,
                'limit' => 100,
                'remaining' => 100,
                'plan' => 'free'
            );
        }

        $remaining = max( 0, $user->messages_limit - $user->messages_count );

        return array(
            'used' => (int) $user->messages_count,
            'limit' => (int) $user->messages_limit,
            'remaining' => $remaining,
            'plan' => $user->plan
        );
    }

    /**
     * Actualiza el plan de un usuario.
     */
    public function update_plan( $session_id, $plan, $limit = null ) {
        global $wpdb;

        $data = array( 'plan' => $plan );
        $format = array( '%s' );

        if ( $limit !== null ) {
            $data['messages_limit'] = $limit;
            $format[] = '%d';
        }

        if ( $plan !== 'free' ) {
            $data['subscription_expires'] = date( 'Y-m-d H:i:s', strtotime( '+1 month' ) );
            $format[] = '%s';
        }

        $wpdb->update(
            $this->table_users,
            $data,
            array( 'session_id' => $session_id ),
            $format,
            array( '%s' )
        );
    }

    /**
     * Limpia conversaciones antiguas.
     */
    public function cleanup_old_conversations( $days = 30 ) {
        global $wpdb;

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table_conversations}
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
    }

    /**
     * Guarda datos de analytics.
     */
    public function save_analytics( $date, $data ) {
        global $wpdb;

        $wpdb->replace(
            $this->table_analytics,
            array(
                'date' => $date,
                'total_messages' => $data['total_messages'] ?? 0,
                'total_users' => $data['total_users'] ?? 0,
                'free_users' => $data['free_users'] ?? 0,
                'premium_users' => $data['premium_users'] ?? 0,
                'avg_response_time' => $data['avg_response_time'] ?? 0,
                'popular_topics' => isset( $data['popular_topics'] ) ? wp_json_encode( $data['popular_topics'] ) : '',
            ),
            array( '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
        );
    }

    /**
     * Obtiene analytics de un rango de fechas.
     */
    public function get_analytics( $start_date, $end_date ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_analytics}
            WHERE date BETWEEN %s AND %s
            ORDER BY date ASC",
            $start_date,
            $end_date
        ), ARRAY_A );
    }
}
