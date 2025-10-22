<?php
/**
 * Desactivador del plugin.
 *
 * @package AI_Voice_Text_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Voice_Text_Widget_Deactivator {

    /**
     * Desactiva el plugin.
     */
    public static function deactivate() {
        // Limpiar tareas programadas
        wp_clear_scheduled_hook( 'ai_widget_daily_cleanup' );
        wp_clear_scheduled_hook( 'ai_widget_daily_analytics' );
        wp_clear_scheduled_hook( 'ai_widget_monthly_reset' );
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
