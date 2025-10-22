<?php
/**
 * Sistema Freemium del plugin.
 *
 * @package AI_Voice_Text_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Voice_Text_Widget_Freemium {

    private $plans = array(
        'free' => array(
            'name' => 'Plan Gratuito',
            'messages' => 100,
            'price' => 0,
        ),
        'basic' => array(
            'name' => 'Plan Básico',
            'messages' => 1000,
            'price' => 9.99,
        ),
        'pro' => array(
            'name' => 'Plan Pro',
            'messages' => 10000,
            'price' => 29.99,
        ),
        'unlimited' => array(
            'name' => 'Plan Ilimitado',
            'messages' => -1,
            'price' => 99.99,
        ),
    );

    public function get_plans() {
        return $this->plans;
    }

    public function get_plan( $plan_name ) {
        return $this->plans[ $plan_name ] ?? null;
    }

    public function upgrade_user( $session_id, $plan_name ) {
        $plan = $this->get_plan( $plan_name );
        
        if ( ! $plan ) {
            return new WP_Error( 'invalid_plan', 'Plan inválido' );
        }

        $database = new AI_Voice_Text_Widget_Database();
        $limit = $plan['messages'] === -1 ? 999999 : $plan['messages'];
        
        $database->update_plan( $session_id, $plan_name, $limit );

        return true;
    }

    public function check_limits( $session_id ) {
        $database = new AI_Voice_Text_Widget_Database();
        return $database->can_send_message( $session_id );
    }
}
