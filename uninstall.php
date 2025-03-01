<?php
// Se non è una disinstallazione da WordPress, esci
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Rimuovi le opzioni
delete_option('drilion_connector_api_key');

// Rimuovi i post di log
$logs = get_posts(array(
    'post_type' => 'drilion_log',
    'numberposts' => -1,
    'fields' => 'ids'
));

if (!empty($logs)) {
    foreach ($logs as $log_id) {
        wp_delete_post($log_id, true);
    }
}

// Rimuovi il post type
unregister_post_type('drilion_log');

// Cancella eventuali tabelle personalizzate
global $wpdb;
$table_name = $wpdb->prefix . 'drilion_logs';
$wpdb->query("DROP TABLE IF EXISTS $table_name");