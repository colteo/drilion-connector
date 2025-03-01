<?php
/**
 * Plugin Name: Drilion Connector
 * Plugin URI: https://pranzoalvolo.it
 * Description: Connector per integrare Klaviyo e Make.com con il sistema di punti e buoni del Club dei Non-Cuochi
 * Version: 1.0.0
 * Author: Pranzoalvolo.it
 * Author URI: https://pranzoalvolo.it
 * Text Domain: drilion-connector
 * Requires at least: 5.6
 * Requires PHP: 7.3
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Impedisci l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit;
}

// Definizione delle costanti
define('DRILION_CONNECTOR_VERSION', '1.0.0');
define('DRILION_CONNECTOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DRILION_CONNECTOR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Carica i file necessari
require_once DRILION_CONNECTOR_PLUGIN_DIR . 'includes/class-drilion-api.php';
require_once DRILION_CONNECTOR_PLUGIN_DIR . 'includes/class-drilion-points.php';
require_once DRILION_CONNECTOR_PLUGIN_DIR . 'includes/class-drilion-logs.php';

// Classe principale del plugin
class Drilion_Connector {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Riferimenti alle classi
     */
    public $api;
    public $points;
    public $logs;
    
    /**
     * Ritorna l'istanza singleton della classe
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Costruttore
     */
    private function __construct() {
        // Inizializza le classi
        $this->init_classes();
        
        // Aggiungi menu admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Registra impostazioni
        register_setting('drilion_connector_options', 'drilion_connector_api_key');
    }
    
    /**
     * Inizializza le classi principali
     */
    private function init_classes() {
        $this->api = new Drilion_API();
        $this->points = new Drilion_Points();
        $this->logs = new Drilion_Logs();
    }
    
    /**
     * Aggiunge menu admin
     */
    public function add_admin_menu() {
        add_menu_page(
            'Drilion Connector',
            'Drilion Connector',
            'manage_options',
            'drilion-connector',
            array($this, 'admin_page'),
            'dashicons-superhero', // Icona per il menu
            56 // Posizione nel menu (dopo i commenti)
        );
    }
    
    /**
     * Pagina admin
     */
    public function admin_page() {
        include_once DRILION_CONNECTOR_PLUGIN_DIR . 'admin/admin-page.php';
    }
    
    // Metodo rimosso (non verifichiamo più la dipendenza da WooCommerce)
}

// Inizializza il plugin
function drilion_connector_init() {
    global $drilion_connector;
    $drilion_connector = Drilion_Connector::get_instance();
    return $drilion_connector;
}
add_action('plugins_loaded', 'drilion_connector_init');

// Attivazione del plugin
register_activation_hook(__FILE__, 'drilion_connector_activate');
function drilion_connector_activate() {
    // Crea una chiave API casuale se non esiste
    if (!get_option('drilion_connector_api_key')) {
        update_option('drilion_connector_api_key', wp_generate_password(32, false));
    }
    
    // Aggiorna il rewrite per l'API
    flush_rewrite_rules();
}

// Disattivazione del plugin
register_deactivation_hook(__FILE__, 'drilion_connector_deactivate');
function drilion_connector_deactivate() {
    flush_rewrite_rules();
}