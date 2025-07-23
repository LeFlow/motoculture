<?php
// Hooks WooCommerce pour DSI Location

// Hook : Validation de commande, mise à jour de la réservation en attente
remove_action('woocommerce_checkout_order_processed', 'dsi_location_save_reservation_on_order', 20);
add_action('woocommerce_new_order', 'dsi_location_save_reservation_on_order', 20, 1);
function dsi_location_save_reservation_on_order($order_id) {
log_error('woocommerce-hooks : DSI VALIDATION: function called');
    global $wpdb;
    $order = wc_get_order($order_id);
    $table = $wpdb->prefix . 'dsi_location_reservations';
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $meta = $item->get_meta('dsi_location');
log_error('woocommerce-hooks : DSI VALIDATION: meta=' . print_r($meta, true));
        if (!empty($meta['is_dsi_reservation'])) {
            $user_id = $order->get_user_id();
            $unit_id = intval($meta['unit_id'] ?? 0);
            $start_date = sanitize_text_field($meta['start_date'] ?? '');
            $end_date = sanitize_text_field($meta['end_date'] ?? $start_date);
            $expected_date = sanitize_text_field($meta['expected_date'] ?? $end_date);
            $time_start = $meta['time-start'] ?? 'jour';
            $time_end = $meta['time-end'] ?? 'jour';
            // Conversion horaires
            $start_hour = ($time_start === 'matin') ? 9 : (($time_start === 'aprem') ? 14 : 9);
            $end_hour = ($time_end === 'matin') ? 12 : (($time_end === 'aprem') ? 18 : 18);
            // Utilisation d'un identifiant unique pour fiabiliser la mise à jour
            $unique_hash = md5($user_id . '_' . $product_id . '_' . $unit_id . '_' . $start_date . '_' . $end_date . '_' . $start_hour . '_' . $end_hour);
log_error('woocommerce-hooks : DSI VALIDATION: unique_hash=' . $unique_hash);
            // Mise à jour de la réservation en attente pour la passer à validée
            $updated = $wpdb->update($table,
                ['waiting_validation' => 0, 'expected_date' => $expected_date],
                ['unique_hash' => $unique_hash],
                ['%d', '%s'],
                ['%s']
            );
log_error('woocommerce-hooks : DSI VALIDATION: update result=' . print_r($updated, true));
            // Si aucune réservation en attente n'a été trouvée, on insère (cas rare)
            if (!$updated) {
                $wpdb->insert($table, [
                    'product_id' => $product_id,
                    'unit_id' => $unit_id,
                    'user_id' => $user_id,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'expected_date' => $expected_date,
                    'start_hour' => $start_hour,
                    'end_hour' => $end_hour,
                    'returned' => 0,
                    'waiting_validation' => 0,
                    'unique_hash' => $unique_hash
                ], [
                    '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s'
                ]);
log_error('woocommerce-hooks : DSI VALIDATION: inserted new reservation (cas rare)');
                // Supprimer la réservation en attente si elle existe encore (doublon)
                $wpdb->delete($table, [
                    'unique_hash' => $unique_hash,
                    'waiting_validation' => 1
                ]);
            }
        }
    }
}


// Copier le meta dsi_location dans l'order item lors de la création de la commande (hook moderne)
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!empty($values['dsi_location'])) {
        $item->add_meta_data('dsi_location', $values['dsi_location'], true);
    }
}, 10, 4);

// Forcer tous les produits à être achetables pour la location
add_filter('woocommerce_is_purchasable', function($purchasable, $product) {
    return true;
}, 10, 2);

// Forcer la redirection après ajout au panier vers la page courante si 'redirect_to' est présent
add_filter('woocommerce_add_to_cart_redirect', function($url) {
    if (!empty($_REQUEST['redirect_to'])) {
        return esc_url_raw($_REQUEST['redirect_to']);
    }
    return $url;
});

// Forcer le prix à _prix_demi_journee pour les réservations DSI
add_action('woocommerce_before_calculate_totals', 'dsi_location_set_custom_price', 20, 1);
function dsi_location_set_custom_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['dsi_location']['is_dsi_reservation'])) {
            $product_id = $cart_item['product_id'];
            $prix_demi_journee = get_post_meta($product_id, '_prix_demi_journee', true);
            $prix_journee = get_post_meta($product_id, '_prix_journee', true);
            if ($prix_demi_journee !== '' && is_numeric($prix_demi_journee) && floatval($prix_demi_journee) > 0) {
                $cart_item['data']->set_price(floatval($prix_demi_journee));
            } elseif ($prix_journee !== '' && is_numeric($prix_journee) && floatval($prix_journee) > 0) {
                $cart_item['data']->set_price(floatval($prix_journee));
            }
        }
    }
} 

add_action('woocommerce_payment_complete', 'dsi_create_reservations_in_db');
function dsi_create_reservations_in_db($order_id) {
    $order = wc_get_order($order_id);
    global $wpdb;

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $user_id = $order->get_user_id();
        $unit_id = $item->get_meta('dsi_unit_id');
        $start_date = $item->get_meta('dsi_start_date');
        $end_date = $item->get_meta('dsi_end_date');
        $start_hour = $item->get_meta('dsi_start_hour');
        $end_hour = $item->get_meta('dsi_end_hour');
        $expected_date = $end_date;

        if (!$product_id || !$unit_id || !$start_date || !$end_date) continue;

        // Empêcher les doublons
        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}dsi_location_reservations
            WHERE order_id = %d AND product_id = %d AND unit_id = %d
        ", $order_id, $product_id, $unit_id));

        if ($exists > 0) continue;

        $wpdb->insert("{$wpdb->prefix}dsi_location_reservations", [
            'product_id'        => $product_id,
            'unit_id'           => $unit_id,
            'user_id'           => $user_id,
            'start_date'        => $start_date,
            'end_date'          => $end_date,
            'expected_date'     => $expected_date,
            'start_hour'        => $start_hour,
            'end_hour'          => $end_hour,
            'returned'          => 0,
            'waiting_validation'=> 0,
            'unique_hash'       => wp_generate_uuid4(),
        ]);
    }
}
