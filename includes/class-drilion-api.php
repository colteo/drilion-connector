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
        global $drilion_connector;
        
        // Ottieni i parametri dalla richiesta
        $params = $request->get_params();
        
        // Verifica che l'email dell'utente sia fornita
        if (empty($params['email'])) {
            return new WP_Error('missing_email', 'Email utente mancante', array('status' => 400));
        }
        
        $email = sanitize_email($params['email']);
        
        // Cerca l'utente per email
        $user = get_user_by('email', $email);
        
        if (!$user) {
            return new WP_Error('user_not_found', 'Utente non trovato', array('status' => 404));
        }
        
        // Ottieni i dati dell'utente attraverso la classe dei punti
        // Verifica se la classe dei punti è disponibile
        $user_data = array();
        
        if (isset($drilion_connector) && method_exists($drilion_connector->points, 'get_user_points_data')) {
            $user_data = $drilion_connector->points->get_user_points_data($user->ID);
        } else {
            // Risposta predefinita se la classe dei punti non è disponibile
            $user_data = array(
                'email' => $email,
                'buoni_piatto' => 0,
                'buoni_piatto_euro' => 0.00,
                'punti_esperienza' => 0,
                'punti_per_livello_successivo' => 500,
                'birthday' => '',
            );
        }
        
        // Registra la richiesta nei log
        if (isset($drilion_connector) && method_exists($drilion_connector->logs, 'log_api_request')) {
            $drilion_connector->logs->log_api_request($email, $user_data);
        }
        
        return rest_ensure_response($user_data);
    }
}