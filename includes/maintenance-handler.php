<?php
// Gestion des indisponibilités pour maintenance

// Ajouter une période d'indisponibilité
function dsi_location_set_maintenance($product_id, $unit_id, $start_date, $end_date) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_maintenance';
    // Vérification de chevauchement avec maintenance
    if (function_exists('dsi_location_is_in_maintenance') && dsi_location_is_in_maintenance($product_id, $unit_id, $start_date, $end_date)) {
        return new WP_Error('maintenance_conflict', 'Chevauchement avec une maintenance existante.');
    }
    // Vérification de chevauchement avec réservation
    if (function_exists('dsi_location_is_in_reservation') && dsi_location_is_in_reservation($product_id, $unit_id, $start_date, $end_date)) {
        return new WP_Error('reservation_conflict', 'Chevauchement avec une réservation existante.');
    }
    $wpdb->insert($table, [
        'product_id' => $product_id,
        'unit_id'    => $unit_id,
        'start_date' => $start_date,
        'end_date'   => $end_date
    ]);
    return true;
}

// Récupérer les périodes d’indisponibilité pour une unité donnée
function dsi_location_get_maintenance_periods($product_id, $unit_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_maintenance';

    return $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table
        WHERE product_id = %d AND unit_id = %d AND completed = 0
    ", $product_id, $unit_id), ARRAY_A);
}

// Vérifie si une unité est en maintenance pendant une période donnée
function dsi_location_is_in_maintenance($product_id, $unit_id, $start_date, $end_date) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_maintenance';

    $conflit = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table
        WHERE product_id = %d AND unit_id = %d
        AND start_date <= %s AND end_date >= %s
        AND completed = 0
    ", $product_id, $unit_id, $end_date, $start_date));
    return $conflit > 0;
}

// Vérifie si une unité est réservée pendant une période donnée
function dsi_location_is_in_reservation($product_id, $unit_id, $start_date, $end_date) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table
        WHERE product_id = %d AND unit_id = %d
        AND start_date <= %s AND end_date >= %s
        AND returned = 0
        AND waiting_validation IN (0,1)
    ", $product_id, $unit_id, $end_date, $start_date));
    return $count > 0;
}