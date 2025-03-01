<?php
/**
 * Classe per la gestione dei log
 */
class Drilion_Logs {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Registra post type per log
        add_action('init', array($this, 'register_log_post_type'));
    }
    
    /**
     * Registra il post type per i log
     */
    public function register_log_post_type() {
        register_post_type('drilion_log', array(
            'public' => false,
            'has_archive' => false,
            'supports' => array('title', 'custom-fields'),
        ));
    }
    
    /**
     * Registra la richiesta API nei log
     */
    public function log_api_request($email, $data) {
        $log_id = wp_insert_post(array(
            'post_type' => 'drilion_log',
            'post_title' => 'API Request - ' . $email . ' - ' . date('Y-m-d H:i:s'),
            'post_status' => 'publish',
        ));
        
        if (!is_wp_error($log_id)) {
            update_post_meta($log_id, 'email', $email);
            update_post_meta($log_id, 'request_time', current_time('mysql'));
            update_post_meta($log_id, 'response_data', wp_json_encode($data));
        }
    }
    
    /**
     * Ottiene i log recenti per visualizzarli nella pagina admin
     */
    public function get_recent_logs($limit = 10) {
        $args = array(
            'post_type' => 'drilion_log',
            'posts_per_page' => $limit,
            'order' => 'DESC',
            'orderby' => 'date'
        );
        
        return new WP_Query($args);
    }
}