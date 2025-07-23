<?php
// Ajout du formulaire de réservation en bas de la fiche produit
add_action('woocommerce_single_product_summary', 'dsi_afficher_tarifs_reservation', 100);

function dsi_afficher_tarifs_reservation() {
log_error('Charlychargement: frontend-hooks.php: dsi_afficher_tarifs_reservation called');
    global $post;

    if ($post->post_type !== 'product') return;

    $post_id = $post->ID;
    $product = wc_get_product( $post_id );
	
    $prix_demi_journee = get_post_meta($post_id, '_prix_demi_journee', true);
    $prix_journee = get_post_meta($post_id, '_prix_journee', true);
    $prix_weekend = get_post_meta($post_id, '_prix_weekend', true);
    $montant_caution = get_post_meta($post_id, '_montant_caution', true);
    $product_cat = $product->get_category_ids();

	echo '<h3>Tarifs de la location :</h3><div id="dsi-tarifs">';
	if($prix_demi_journee > 0)
		echo '<p>Demi-journée : <b>' . wc_price($prix_demi_journee) . '</b></p>';
	if($prix_journee > 0)
		echo '<p>Journée : <b>' . wc_price($prix_journee) . '</b></p>';
	if($prix_weekend > 0)
		echo '<p>Weekend : <b>' . wc_price($prix_weekend) . '</b></p>';
	echo '</div><div>';
	if($montant_caution > 0)
		echo '<p>Caution : <b>' . wc_price($montant_caution) . '</b></p>';
    if($product_cat){
        $terms = get_the_terms($post_id, 'product_cat');
        foreach ( $terms as $term ) {
            $cat_name = $term->name;
            $options = get_option('dsi_location_cancellation_days', []);
            echo '<p>Catégorie : <b>' . $cat_name . '</b></p>';
            echo '<p>Délais d\'annulation : <b>' . $options[$term->term_id] . ' jours</b></p>';
        }
    }
	echo '<input id="tarifs-loc" type="hidden" data-demi="'.$prix_demi_journee.'" data-jour="'.$prix_journee.'" data-we="'.$prix_weekend.'"></div>';
	
}

add_action('woocommerce_after_single_product', 'dsi_afficher_formulaire_reservation', 101);
function dsi_afficher_formulaire_reservation() {
    global $post;
    if ($post->post_type !== 'product') return;
    $product_id = $post->ID;
    $nb_units = get_post_meta($product_id, '_nombre_articles_location', true);
    $nb_units = $nb_units ? intval($nb_units) : 1;
    $today = date('Y-m-d');
    include dirname(__DIR__) . '/views/reservation-form.php';
}

// Endpoint AJAX pour vérifier la disponibilité d'une réservation AVANT ajout au panier
add_action('wp_ajax_dsi_check_reservation_availability', 'dsi_check_reservation_availability');
add_action('wp_ajax_nopriv_dsi_check_reservation_availability', 'dsi_check_reservation_availability');
function dsi_check_reservation_availability() {
    global $wpdb;

    check_ajax_referer('dsi_nonce_action', '_ajax_nonce');

    $product_id = intval($_POST['product_id']);
    $unit_id = intval($_POST['unit_id']);
    $start_date = sanitize_text_field($_POST['start_date']);
    $end_date = sanitize_text_field($_POST['end_date']);

    $query = $wpdb->prepare("
        SELECT id FROM {$wpdb->prefix}dsi_location_reservations
        WHERE product_id = %d
        AND unit_id = %d
        AND waiting_validation IN (0,1)
        AND returned = 0
        AND (
                (start_date <= %s AND end_date >= %s)
            OR  (start_date <= %s AND end_date >= %s)
            OR  (start_date >= %s AND end_date <= %s)
          )
        LIMIT 1
    ", $product_id, $unit_id, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date);

    $reservation_exists = $wpdb->get_var($query);

    if ($reservation_exists) {
        wp_send_json_error(['message' => 'Créneau déjà réservé !']);
    } else {
        wp_send_json_success(['message' => 'Disponible']);
    }
}


// À la toute fin du fichier, ajouter ce script pour exposer l'URL de base à JS
add_action('wp_enqueue_scripts', function() {
    if (is_product()) {
        wp_enqueue_script('dsi-front', plugin_dir_url(__DIR__) . 'js/dsi-front.js', ['jquery'], null, true);
        wp_localize_script('dsi-front', 'DSI_Calendar', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dsi_calendar')
        ]);
    }
});