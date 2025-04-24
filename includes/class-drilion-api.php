<?php
/**
 * Classe per la gestione delle API REST
 */
class Drilion_API {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Registra endpoint per l'API
        add_action('rest_api_init', array($this, 'register_api_endpoints'));
    }
    
    /**
     * Registra endpoint API REST
     */
    public function register_api_endpoints() {
        register_rest_route('drilion/v1', '/user-data', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_user_data'),
            'permission_callback' => array($this, 'check_api_permission'),
        ));
        
        // Endpoint per azzerare i punti dell'utente
        register_rest_route('drilion/v1', '/reset-user-points', array(
            'methods' => 'POST',
            'callback' => array($this, 'reset_user_points'),
            'permission_callback' => array($this, 'check_api_permission'),
        ));

        // Endpoint per gestire l'esclusione di email dai coupon
        register_rest_route('drilion/v1', '/coupon-email-exclusion', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_coupon_email_exclusion'),
            'permission_callback' => array($this, 'check_api_permission'),
        ));
    }
    
    /**
     * Controlla i permessi delle API
     */
    public function check_api_permission($request) {
        $headers = $request->get_headers();
        $stored_api_key = get_option('drilion_connector_api_key');
        
        // Debug: Registra gli header ricevuti in un file di log
        $headers_log = print_r($headers, true);
        $log_file = DRILION_CONNECTOR_PLUGIN_DIR . 'api_headers_log.txt';
        file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Headers ricevuti:\n" . $headers_log . "\n\n", FILE_APPEND);
        
        // Verifica se la chiave API è configurata nel plugin
        if (empty($stored_api_key)) {
            return new WP_Error(
                'api_key_not_configured',
                'La chiave API non è configurata nel plugin. Configurala nella dashboard di Drilion Connector.',
                array('status' => 500)
            );
        }
        
        // Verifica se l'header X-API-Key è presente (controlla sia con trattino che con underscore)
        $api_key = '';
        if (isset($headers['x-api-key']) && !empty($headers['x-api-key'][0])) {
            $api_key = $headers['x-api-key'][0];
        } elseif (isset($headers['x_api_key']) && !empty($headers['x_api_key'][0])) {
            $api_key = $headers['x_api_key'][0];
        }
        
        if (empty($api_key)) {
            // Debug: Registra il problema specifico
            file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Problema: Header X-API-Key mancante\n\n", FILE_APPEND);
            return new WP_Error(
                'api_key_missing',
                'Header X-API-Key mancante nella richiesta. Assicurati di includere l\'header X-API-Key nella tua richiesta.',
                array('status' => 401)
            );
        }
        
        // Verifica se la chiave API fornita è corretta
        if ($api_key !== $stored_api_key) {
            // Debug: Registra il problema specifico
            file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Problema: API key non valida\nFornita: " . $api_key . "\nAttesa: " . $stored_api_key . "\n\n", FILE_APPEND);
            return new WP_Error(
                'api_key_invalid',
                'La chiave API fornita non è valida. Verifica di star usando la chiave corretta dalla dashboard di Drilion Connector.',
                array('status' => 403)
            );
        }
        
        // Debug: Registra autenticazione riuscita
        file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Autenticazione riuscita\n\n", FILE_APPEND);
        return true;
    }
    
    /**
     * Callback per l'endpoint di recupero dati utente
     */
    public function get_user_data($request) {
        global $drilion_connector, $wpdb;
        
        // Ottieni i parametri dalla richiesta
        $params = $request->get_params();
        
        // Verifica che l'email dell'utente sia fornita
        if (empty($params['email'])) {
            return new WP_Error('missing_email', 'Email utente mancante', array('status' => 400));
        }
        
        $email = sanitize_email($params['email']);
        
        // CASO 1: Cerca l'utente per email nel database WordPress
        $user = get_user_by('email', $email);
        
        if ($user) {
            // L'utente è registrato, ottieni i dati attraverso la classe dei punti
            if (isset($drilion_connector) && method_exists($drilion_connector->points, 'get_user_points_data')) {
                $user_data = $drilion_connector->points->get_user_points_data($user->ID);
                
                // Registra la richiesta nei log
                if (isset($drilion_connector) && method_exists($drilion_connector->logs, 'log_api_request')) {
                    $drilion_connector->logs->log_api_request($email, $user_data);
                }
                
                return rest_ensure_response($user_data);
            }
        }
        
        // CASO 2: Controlla se l'email corrisponde a un cliente ospite (non registrato) in WooCommerce
        // Verifica se WooCommerce è attivo
        if (function_exists('wc_get_orders')) {
            // Cerca un ordine con questa email come billing_email
            $orders = wc_get_orders(array(
                'billing_email' => $email,
                'limit' => 1,
            ));
            
            if (!empty($orders)) {
                // Trovato un cliente ospite con ordini
                $user_data = array(
                    'email' => $email,
                    'is_guest' => true
                );
                
                // Registra la richiesta nei log
                if (isset($drilion_connector) && method_exists($drilion_connector->logs, 'log_api_request')) {
                    $drilion_connector->logs->log_api_request($email, $user_data);
                }
                
                return rest_ensure_response($user_data);
            }
        }
        
        // CASO 3: Se arriviamo qui, l'email non corrisponde a nessun cliente
        return new WP_Error(
            'user_not_found',
            'Utente non trovato',
            array('status' => 404)
        );
    }
    
    /**
     * Callback per l'endpoint di azzeramento punti utente
     */
    public function reset_user_points($request) {
        global $drilion_connector;
        
        // Ottieni i parametri dalla richiesta
        $params = $request->get_params();
        
        // Verifica che l'email dell'utente sia fornita
        if (empty($params['email'])) {
            return new WP_Error('missing_email', 'Email utente mancante', array('status' => 400));
        }
        
        $email = sanitize_email($params['email']);
        
        // Cerca l'utente per email nel database WordPress
        $user = get_user_by('email', $email);
        
        if (!$user) {
            return new WP_Error(
                'user_not_found',
                'Utente non trovato',
                array('status' => 404)
            );
        }
        
        // Valori precedenti per logging
        $previous_exp_points = (int)get_user_meta($user->ID, 'lws_wre_points_programma_fedelta', true);
        $previous_buoni_piatto = (int)get_user_meta($user->ID, 'lws_wre_points_buoni_piatto', true);
        
        // Ottieni la soglia del livello precedente e il livello corrente
        $current_level_data = $drilion_connector->points->get_user_level_data($previous_exp_points);
        $current_level = $current_level_data['current_level'];
        
        // Definizione delle soglie di livello (ripreso da class-drilion-points.php)
        $levels = array(
            1 => array('required_points' => 1),
            2 => array('required_points' => 500),
            3 => array('required_points' => 1000),
            4 => array('required_points' => 2000),
            5 => array('required_points' => 3000),
        );
        
        // Calcola i nuovi punti esperienza (al livello precedente)
        $new_exp_points = $previous_exp_points;
        
        // Se l'utente non è già al livello 1, riduci il livello
        if ($current_level > 1) {
            $previous_level = $current_level - 1;
            
            // Imposta punti alla soglia del livello corrente (non successivo) meno 1
            // In questo modo l'utente sarà esattamente appena sotto la soglia del suo livello originario
            $new_exp_points = $levels[$current_level]['required_points'] - 1;
            
            // Se per qualche motivo questo valore è minore della soglia del livello precedente,
            // assicuriamoci che sia almeno al minimo per mantenere il livello precedente
            if ($new_exp_points < $levels[$previous_level]['required_points']) {
                $new_exp_points = $levels[$previous_level]['required_points'];
            }
        }
        
        // Azzera solo i buoni piatto
        update_user_meta($user->ID, 'lws_wre_points_buoni_piatto', 0);
        
        // Aggiorna i punti esperienza per portare l'utente al livello precedente
        update_user_meta($user->ID, 'lws_wre_points_programma_fedelta', $new_exp_points);
        
        // Prepara risposta
        $response_data = array(
            'email' => $email,
            'success' => true,
            'previous_values' => array(
                'punti_esperienza' => $previous_exp_points,
                'buoni_piatto' => $previous_buoni_piatto,
                'livello' => $current_level
            ),
            'current_values' => array(
                'punti_esperienza' => $new_exp_points,
                'buoni_piatto' => 0,
                'livello' => $current_level > 1 ? $current_level - 1 : 1
            )
        );
        
        // Registra la richiesta nei log
        if (isset($drilion_connector) && method_exists($drilion_connector->logs, 'log_api_request')) {
            $log_data = array(
                'action' => 'reset_points',
                'email' => $email,
                'previous_values' => $response_data['previous_values'],
                'current_values' => $response_data['current_values']
            );
            $drilion_connector->logs->log_api_request($email, $log_data);
        }
        
        return rest_ensure_response($response_data);
    }

    /**
     * Callback per l'endpoint di gestione dell'esclusione di email dai coupon
     */
    public function handle_coupon_email_exclusion($request) {
        global $drilion_connector, $wpdb;
        
        // Ottieni i parametri dalla richiesta
        $params = $request->get_params();
        
        // Verifica che entrambi i parametri siano forniti
        if (empty($params['coupon_code'])) {
            return new WP_Error('missing_coupon_code', 'Codice coupon mancante', array('status' => 400));
        }
        
        if (empty($params['email'])) {
            return new WP_Error('missing_email', 'Email mancante', array('status' => 400));
        }
        
        $coupon_code = sanitize_text_field($params['coupon_code']);
        $email = sanitize_email($params['email']);
        
        // Cerca il coupon con il codice fornito
        $coupon_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_title = %s 
                 AND post_type = 'shop_coupon' 
                 AND post_status = 'publish'",
                $coupon_code
            )
        );
        
        if (!$coupon_id) {
            return new WP_Error(
                'coupon_not_found',
                'Coupon non trovato',
                array('status' => 404)
            );
        }
        
        // Cerca se esiste già un meta per le email escluse
        $meta_key = 'wc_sc_excluded_customer_email';
        $excluded_emails_serialized = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} 
                 WHERE post_id = %d 
                 AND meta_key = %s",
                $coupon_id,
                $meta_key
            )
        );
        
        // Inizializza l'array delle email escluse
        if ($excluded_emails_serialized) {
            $excluded_emails = maybe_unserialize($excluded_emails_serialized);
            if (!is_array($excluded_emails)) {
                $excluded_emails = array();
            }
        } else {
            $excluded_emails = array();
        }
        
        // Verifica se l'email è già nella lista
        $email_already_excluded = false;
        foreach ($excluded_emails as $existing_email) {
            if ($existing_email === $email) {
                $email_already_excluded = true;
                break;
            }
        }
        
        // Se l'email non è già esclusa, aggiungila
        if (!$email_already_excluded) {
            $excluded_emails[] = $email;
            
            // Aggiorna il meta del coupon
            update_post_meta($coupon_id, $meta_key, $excluded_emails);
            
            $success = true;
            $message = "Email '{$email}' esclusa con successo dal coupon '{$coupon_code}'";
        } else {
            $success = false;
            $message = "L'email '{$email}' è già esclusa dal coupon '{$coupon_code}'";
        }
        
        // Prepara risposta
        $response_data = array(
            'success' => $success,
            'message' => $message,
            'coupon_code' => $coupon_code,
            'coupon_id' => $coupon_id,
            'email' => $email,
            'excluded_emails' => $excluded_emails
        );
        
        // Registra la richiesta nei log
        if (isset($drilion_connector) && method_exists($drilion_connector->logs, 'log_api_request')) {
            $log_data = array(
                'action' => 'coupon_email_exclusion',
                'coupon_code' => $coupon_code,
                'email' => $email,
                'success' => $success,
                'excluded_emails' => $excluded_emails
            );
            $drilion_connector->logs->log_api_request($email, $log_data);
        }
        
        return rest_ensure_response($response_data);
    }
}