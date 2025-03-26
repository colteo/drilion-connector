<?php
// Previeni l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Globale per accedere all'istanza del plugin
global $drilion_connector;
?>

<div class="wrap">
    <h1>Drilion Connector</h1>
    
    <div class="card" style="max-width: 1000px; margin-bottom: 20px; padding: 20px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.1);">
        <h2>Impostazioni API</h2>
        <form method="post" action="options.php">
            <?php settings_fields('drilion_connector_options'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Chiave API</th>
                    <td>
                        <?php $api_key = get_option('drilion_connector_api_key'); ?>
                        <input type="text" name="drilion_connector_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                        <p class="description">Questa chiave API è richiesta per le richieste da Make.com</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    
    <div class="card" style="max-width: 1000px; margin-bottom: 20px; padding: 20px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.1);">
        <h2>Documentazione API</h2>
        
        <!-- Endpoint per ottenere i dati dell'utente -->
        <div style="border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px;">
            <h3>Ottenere dati utente</h3>
            <p>Per ottenere i dati dell'utente, invia una richiesta POST all'endpoint seguente:</p>
            <code><?php echo rest_url('drilion/v1/user-data'); ?></code>
            
            <h4>Intestazioni richieste</h4>
            <ul>
                <li><code>Content-Type: application/json</code></li>
                <li><code>X-API-Key: <?php echo esc_html($api_key); ?></code></li>
            </ul>
            
            <div class="notice notice-info inline" style="margin: 10px 0; padding: 10px;">
                <strong>Nota:</strong> L'header <code>X-API-Key</code> è fondamentale. Se riscontri problemi con l'autenticazione, assicurati che:
                <ul style="margin-top: 5px; margin-left: 20px; list-style-type: disc;">
                    <li>L'header sia scritto correttamente come <code>X-API-Key</code></li>
                    <li>Il valore della chiave corrisponda esattamente a quello mostrato sopra</li>
                    <li>Alcuni server potrebbero convertire <code>X-API-Key</code> in <code>X_API_Key</code>, entrambi i formati sono accettati</li>
                </ul>
            </div>
            
            <h4>Parametri della richiesta</h4>
            <pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow: auto;">
{
    "email": "email@utente.com"
}
            </pre>
            
            <h4>Risposta di esempio</h4>
            <pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow: auto;">
{
    "email": "utente@esempio.com",
    "buoni_piatto": 500,
    "buoni_piatto_euro": "5,00",
    "punti_esperienza": 350,
    "punti_per_livello_successivo": 150,
    "livello": "livello 1",
    "birthday": "1990-07-22"
}
            </pre>
        </div>
        
        <!-- Nuovo endpoint per azzerare i punti dell'utente -->
        <div>
            <h3>Azzerare i buoni piatto e ridurre il livello dell'utente</h3>
            <p>Per azzerare i buoni piatto e ridurre l'utente al livello precedente, invia una richiesta POST all'endpoint seguente:</p>
            <code><?php echo rest_url('drilion/v1/reset-user-points'); ?></code>
            
            <h4>Intestazioni richieste</h4>
            <ul>
                <li><code>Content-Type: application/json</code></li>
                <li><code>X-API-Key: <?php echo esc_html($api_key); ?></code></li>
            </ul>
            
            <h4>Parametri della richiesta</h4>
            <pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow: auto;">
{
    "email": "email@utente.com"
}
            </pre>
            
            <h4>Risposta di esempio</h4>
            <pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow: auto;">
{
    "email": "utente@esempio.com",
    "success": true,
    "previous_values": {
        "punti_esperienza": 2500,
        "buoni_piatto": 500,
        "livello": 4
    },
    "current_values": {
        "punti_esperienza": 1999,
        "buoni_piatto": 0,
        "livello": 3
    }
}
            </pre>
        </div>
    </div>
    
    <div class="card" style="max-width: 1000px; padding: 20px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.1);">
        <h2>Log delle richieste recenti</h2>
        
        <?php
        $logs = $drilion_connector->logs->get_recent_logs(10);
        
        if ($logs->have_posts()) :
        ?>
        <table class="widefat striped" style="table-layout: fixed; width: 100%;">
            <thead>
                <tr>
                    <th style="width: 15%;">Data e ora</th>
                    <th style="width: 25%;">Email</th>
                    <th style="width: 60%;">Dati risposta</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($logs->have_posts()) : $logs->the_post(); ?>
                <tr>
                    <td><?php echo esc_html(get_post_meta(get_the_ID(), 'request_time', true)); ?></td>
                    <td><?php echo esc_html(get_post_meta(get_the_ID(), 'email', true)); ?></td>
                    <td>
                        <pre style="white-space: pre-wrap; word-wrap: break-word; max-width: 100%; margin: 0; background: #f5f5f5; padding: 5px; border: 1px solid #ddd;"><?php echo esc_html(get_post_meta(get_the_ID(), 'response_data', true)); ?></pre>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else : ?>
        <p>Nessun log da visualizzare.</p>
        <?php 
        endif;
        wp_reset_postdata();
        ?>
    </div>
</div>

<style>
/* Assicuriamo che il JSON vada a capo correttamente nelle celle della tabella */
.widefat td pre {
    white-space: pre-wrap;
    word-wrap: break-word;
    max-width: 100%;
    overflow: auto;
}
</style>