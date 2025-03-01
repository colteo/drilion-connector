<?php
/**
 * Classe per la gestione dei punti e dei livelli
 */
class Drilion_Points {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Verifica se WooCommerce è attivo prima di aggiungere gli hook
        if (function_exists('wc_get_order')) {
            // Azione al completamento dell'ordine per aggiornare punti
            add_action('woocommerce_order_status_completed', array($this, 'update_points_on_order_complete'), 10, 1);
    
            // Azione per l'aggiunta di punti esperienza dopo una recensione prodotto
            add_action('comment_post', array($this, 'add_exp_points_for_review'), 10, 3);
        }
    }
    
    /**
     * Ottiene i dati dei punti utente
     */
    public function get_user_points_data($user_id) {
        // Recupera i punti esperienza
        $exp_points = (int) get_user_meta($user_id, 'lws_wre_points_programma_fedelta', true);
        if (empty($exp_points)) {
            $exp_points = 0;
        }
        
        // Recupera i buoni piatto
        $buoni_piatto = (int) get_user_meta($user_id, 'lws_wre_points_buoni_piatto', true);
        if (empty($buoni_piatto)) {
            $buoni_piatto = 0;
        }
        
        // Calcola il valore in euro dei buoni piatto (100 BP = 1€)
        // Arrotonda a 2 decimali e formatta con la virgola come separatore decimale
        $buoni_piatto_euro = number_format(round($buoni_piatto / 100, 2), 2, ',', '');
        
        // Determina il livello attuale e punti necessari per il livello successivo
        $level_data = $this->get_user_level_data($exp_points);
        
        // Ottieni l'email dell'utente
        $user_info = get_userdata($user_id);
        
        return array(
            'email' => $user_info->user_email,
            'buoni_piatto' => $buoni_piatto,
            'buoni_piatto_euro' => $buoni_piatto_euro, // Stringa
            'punti_esperienza' => $exp_points,
            'punti_per_livello_successivo' => $level_data['points_to_next_level']
        );
    }
    
    /**
     * Determina il livello dell'utente e i punti necessari per avanzare
     */
    public function get_user_level_data($exp_points) {
        // Definisci i livelli e i punti richiesti
        $levels = array(
            1 => array('name' => 'Esploratore di Pasti', 'required_points' => 1, 'bp_multiplier' => 1),
            2 => array('name' => 'Collezionista di Vaschette', 'required_points' => 500, 'bp_multiplier' => 2),
            3 => array('name' => 'Esperto di Piatti Pronti', 'required_points' => 1000, 'bp_multiplier' => 5),
            4 => array('name' => 'Mago dei Microonde', 'required_points' => 2000, 'bp_multiplier' => 8),
            5 => array('name' => 'Guru del Pranzo al Volo', 'required_points' => 3000, 'bp_multiplier' => 10),
        );
        
        // Determina il livello attuale
        $current_level = 1;
        $next_level = 2;
        
        foreach ($levels as $level => $data) {
            if ($exp_points >= $data['required_points']) {
                $current_level = $level;
                $next_level = $level + 1;
            }
        }
        
        // Calcola i punti necessari per il livello successivo
        if (isset($levels[$next_level])) {
            $points_to_next_level = $levels[$next_level]['required_points'] - $exp_points;
        } else {
            // L'utente è già al livello massimo
            $points_to_next_level = 0;
        }
        
        return array(
            'current_level' => $current_level,
            'level_name' => $levels[$current_level]['name'],
            'bp_multiplier' => $levels[$current_level]['bp_multiplier'],
            'points_to_next_level' => $points_to_next_level,
        );
    }
    
    /**
     * Aggiorna i punti quando un ordine è completato
     */
    public function update_points_on_order_complete($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        
        // Se non c'è un utente associato all'ordine, esci
        if (!$user_id) {
            return;
        }
        
        // Ottieni il totale dell'ordine
        $order_total = $order->get_total();
        
        // Calcola i punti esperienza (1 EXP per ogni 1€)
        $exp_points = floor($order_total);
        
        // Recupera i punti esperienza attuali
        $current_exp_points = (int) get_user_meta($user_id, 'drilion_exp_points', true);
        
        // Aggiorna i punti esperienza
        $new_exp_points = $current_exp_points + $exp_points;
        update_user_meta($user_id, 'drilion_exp_points', $new_exp_points);
        
        // Ottieni il livello e il moltiplicatore BP
        $level_data = $this->get_user_level_data($new_exp_points);
        $bp_multiplier = $level_data['bp_multiplier'];
        
        // Verifica se l'ordine supera i 150€ per il raddoppio dei BP
        if ($order_total >= 150) {
            $bp_multiplier *= 2;
        }
        
        // Calcola i buoni piatto guadagnati
        $buoni_piatto_earned = floor($order_total * $bp_multiplier);
        
        // Recupera i buoni piatto attuali
        $current_buoni_piatto = (int) get_user_meta($user_id, 'drilion_buoni_piatto', true);
        
        // Aggiorna i buoni piatto
        $new_buoni_piatto = $current_buoni_piatto + $buoni_piatto_earned;
        update_user_meta($user_id, 'drilion_buoni_piatto', $new_buoni_piatto);
        
        // Registra l'acquisizione di punti nei meta dell'ordine
        update_post_meta($order_id, '_drilion_exp_points_earned', $exp_points);
        update_post_meta($order_id, '_drilion_buoni_piatto_earned', $buoni_piatto_earned);
        update_post_meta($order_id, '_drilion_bp_multiplier_used', $bp_multiplier);
        
        // Aggiungi una nota all'ordine
        $order->add_order_note(
            sprintf(
                'Punti aggiornati per l\'utente. Guadagnati: %1$d EXP, %2$d BP. Nuovo totale: %3$d EXP, %4$d BP',
                $exp_points,
                $buoni_piatto_earned,
                $new_exp_points,
                $new_buoni_piatto
            )
        );
    }
    
    /**
     * Aggiungi punti esperienza per le recensioni dei prodotti
     */
    public function add_exp_points_for_review($comment_id, $comment_approved, $comment_data) {
        // Verifica che il commento sia approvato e sia una recensione di prodotto
        if ($comment_approved !== 1 || $comment_data['comment_type'] !== 'review') {
            return;
        }
        
        // Ottieni l'ID utente dal commento
        $user_id = $comment_data['user_id'];
        
        // Se non c'è un utente associato alla recensione, esci
        if (!$user_id) {
            return;
        }
        
        // Punti esperienza da aggiungere per una recensione
        $exp_points = 25;
        
        // Recupera i punti esperienza attuali
        $current_exp_points = (int) get_user_meta($user_id, 'drilion_exp_points', true);
        
        // Aggiorna i punti esperienza
        $new_exp_points = $current_exp_points + $exp_points;
        update_user_meta($user_id, 'drilion_exp_points', $new_exp_points);
        
        // Registra l'aggiunta di punti nei meta della recensione
        update_comment_meta($comment_id, '_drilion_exp_points_earned', $exp_points);
    }
}