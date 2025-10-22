<?php
/**
 * Analytics del plugin.
 *
 * @package AI_Voice_Text_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Voice_Text_Widget_Analytics {

    public function __construct() {
        add_action( 'ai_widget_daily_analytics', array( $this, 'generate_daily_analytics' ) );
    }

    public function generate_daily_analytics() {
        global $wpdb;
        
        $database = new AI_Voice_Text_Widget_Database();
        $yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );

        $table_users = $wpdb->prefix . 'ai_widget_users';
        $table_conversations = $wpdb->prefix . 'ai_widget_conversations';

        $total_messages = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_conversations} WHERE DATE(created_at) = %s",
            $yesterday
        ) );

        $total_users = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM {$table_conversations} WHERE DATE(created_at) = %s",
            $yesterday
        ) );

        $free_users = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_users} WHERE plan = 'free' AND DATE(last_message_date) = %s",
            $yesterday
        ) );

        $premium_users = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_users} WHERE plan != 'free' AND DATE(last_message_date) = %s",
            $yesterday
        ) );

        $avg_response_time = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(response_time) FROM {$table_conversations} WHERE DATE(created_at) = %s AND message_type = 'ai'",
            $yesterday
        ) );

        $database->save_analytics( $yesterday, array(
            'total_messages' => $total_messages,
            'total_users' => $total_users,
            'free_users' => $free_users,
            'premium_users' => $premium_users,
            'avg_response_time' => $avg_response_time,
        ) );
    }

    public function get_analytics_data( $days = 30 ) {
        $database = new AI_Voice_Text_Widget_Database();
        $end_date = date( 'Y-m-d' );
        $start_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );

        return $database->get_analytics( $start_date, $end_date );
    }
}
