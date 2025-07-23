<?php
log_error('FICHIER CHARGE: ' . __FILE__);
// Fusion booking-handler.php + calendar-handler.php

// --- HANDLERS BOOKING ---
add_action('wp_ajax_dsi_submit_reservation', 'dsi_submit_reservation');
add_action('wp_ajax_nopriv_dsi_submit_reservation', 'dsi_submit_reservation');

function dsi_submit_reservation() {
    global $wpdb;
    check_ajax_referer('dsi_calendar');
    $product_id = intval($_POST['product_id']);
    $unit_id = intval($_POST['unit_id']);
    $start_date = sanitize_text_field($_POST['start_date']);
    $end_date = sanitize_text_field($_POST['end_date']);
    $time_start = sanitize_text_field($_POST['time-start']);
    $time_end = sanitize_text_field($_POST['time-end']);
    $user_id = get_current_user_id();
    if (!$product_id || !$unit_id || !$start_date || !$end_date || !$time_start || !$time_end) {
        wp_send_json_error(['message' => 'Champs manquants.']);
    }
    $result = dsi_enregistrer_reservation([
        'product_id' => $product_id,
        'unit_id'    => $unit_id,
        'user_id'    => $user_id,
        'start_date' => $start_date,
        'end_date'   => $end_date,
        'time_start' => $time_start,
        'time_end'   => $time_end
    ]);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success(['message' => $result['message']]);
}

function dsi_enregistrer_reservation($data, $check_only = false) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $start_hour = match ($data['time_start']) {
        'matin' => 9,
        'aprem' => 14,
        default => 9
    };
    $end_hour = match ($data['time_end']) {
        'matin' => 12,
        'aprem' => 18,
        default => 18
    };
    $product_id = intval($data['product_id']);
    $unit_id    = intval($data['unit_id']);
    $user_id    = intval($data['user_id']);
    $start_date = sanitize_text_field($data['start_date']);
    $end_date   = sanitize_text_field($data['end_date']);
    $returned = isset($data['returned']) ? intval($data['returned']) : 0;
    $start_ts = strtotime($start_date);
    $end_ts   = strtotime($end_date);
    $maintenance_table = $wpdb->prefix . 'dsi_location_maintenance';
    $maintenance_query = $wpdb->prepare("
        SELECT * FROM $maintenance_table
        WHERE product_id = %d
        AND unit_id = %d
        AND start_date <= %s
        AND end_date >= %s
        AND completed = 0
    ", $product_id, $unit_id, $end_date, $start_date);
    $maintenances = $wpdb->get_results($maintenance_query);
    if (!empty($maintenances)) {
        foreach ($maintenances as $m) {
            $m_start = strtotime($m->start_date);
            $m_end   = strtotime($m->end_date);
            for ($ts = $start_ts; $ts <= $end_ts; $ts += 86400) {
                if ($ts >= $m_start && $ts <= $m_end) {
                    return new WP_Error('maintenance_conflict', "Le créneau est déjà réservé ou non disponible.");
                }
            }
        }
    }
    for ($ts = $start_ts; $ts <= $end_ts; $ts += 86400) {
        $current_day = date('Y-m-d', $ts);
        $query = $wpdb->prepare("
            SELECT * FROM $table
            WHERE product_id = %d
            AND unit_id = %d
            AND start_date <= %s AND end_date >= %s
            AND returned = 0
            AND waiting_validation IN (0,1)
        ", $product_id, $unit_id, $current_day, $current_day);
        $conflicts = $wpdb->get_results($query);
        foreach ($conflicts as $res) {
            $res_start_hour = intval($res->start_hour);
            $res_end_hour   = intval($res->end_hour);
            if ($res_start_hour > $res_end_hour) {
                if ($current_day == $res->start_date) {
                    $res_end_hour = 18;
                } elseif ($current_day == $res->end_date) {
                    $res_start_hour = 9;
                } else {
                    $res_start_hour = 9;
                    $res_end_hour = 18;
                }
            }
            $overlap = !($end_hour <= $res_start_hour || $start_hour >= $res_end_hour);
            if ($overlap) {
                return new WP_Error('conflict_detected', "Le créneau est déjà réservé ou non disponible.");
            }
        }
    }
    if ($check_only) {
        return ['message' => 'Créneau disponible.'];
    }
    $unique_hash = md5($user_id . '_' . $product_id . '_' . $unit_id . '_' . $start_date . '_' . $end_date . '_' . $start_hour . '_' . $end_hour);
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE unique_hash = %s", $unique_hash));
    if ($exists) {
        return new WP_Error('duplicate_reservation', 'Réservation déjà enregistrée.');
    }
    $inserted = $wpdb->insert(
        $table,
        [
            'product_id' => $product_id,
            'unit_id'    => $unit_id,
            'user_id'    => $user_id,
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'expected_date' => $end_date,
            'start_hour' => $start_hour,
            'end_hour'   => $end_hour,
            'returned'   => 0,
            'waiting_validation' => 1,
            'unique_hash' => $unique_hash
        ],
        ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s']
    );
    if (!$inserted) {
        return new WP_Error('insert_failed', 'Erreur lors de l\'enregistrement.');
    }
    return ['message' => 'Réservation enregistrée avec succès.'];
}

add_filter('woocommerce_add_order_item_meta', function($item_id, $values, $cart_item_key) {
    if (!empty($values['dsi_location'])) {
        wc_add_order_item_meta($item_id, 'dsi_location', $values['dsi_location']);
    }
}, 10, 3);

// --- HANDLERS CALENDAR ---
function dsi_location_get_calendars($product_id) {
    $nombre_unites = (int) get_post_meta($product_id, '_nombre_articles_location', true);
    $calendars = [];
    for ($i = 1; $i <= $nombre_unites; $i++) {
        $calendars[] = [
            'unit_id' => $i,
            'reservations' => dsi_location_get_reservations_for_unit($product_id, $i)
        ];
    }
    return $calendars;
}

function dsi_location_get_reservations_for_unit($product_id, $unit_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $result = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table
        WHERE product_id = %d AND unit_id = %d AND returned = 0 AND waiting_validation IN (0,1)
    ", $product_id, $unit_id), ARRAY_A);
    return $result;
}

add_action('wp_ajax_dsi_get_calendar_events', 'dsi_get_calendar_events');
add_action('wp_ajax_nopriv_dsi_get_calendar_events', 'dsi_get_calendar_events');

function dsi_get_calendar_events() {
    log_error('CALL dsi_get_calendar_events');
    check_ajax_referer('dsi_calendar');
    $product_id = intval($_POST['product_id']);
    $unit_id = intval($_POST['unit_id']);
    $start = sanitize_text_field($_POST['start']);
    $end = sanitize_text_field($_POST['end']);
    $user_id = get_current_user_id();
    if($start > $end){ $fin = $end; $end = $start; $start = $fin; }
    $reservations = dsi_location_get_reservations_for_unit($product_id, $unit_id);
    $maintenances = dsi_location_get_maintenance_periods($product_id, $unit_id);
    $events = [];
    // Réservations validées
    foreach ($reservations as $r) {
        $start_date = new DateTime($r['start_date']);
        $end_date = new DateTime($r['end_date']);
        $current = clone $start_date;
        while ($current <= $end_date) {
            $classe = [];
            $date_str = $current->format('Y-m-d');
            if ($current == $start_date && $current == $end_date) {
                if ($r['start_hour'] == 9 && $r['end_hour'] == 12) {
                    $classe = ['demi', 'matin'];
                } elseif ($r['start_hour'] == 14 && $r['end_hour'] == 18) {
                    $classe = ['demi', 'aprem'];
                } elseif ($r['start_hour'] == 9 && $r['end_hour'] == 18) {
                    $classe = ['jour'];
                }
            } elseif ($current == $start_date) {
                if ($r['start_hour'] == 14) {
                    $classe = ['demi', 'aprem'];
                } elseif ($r['start_hour'] == 9) {
                    $classe = ['jour'];
                }
            } elseif ($current == $end_date) {
                if ($r['end_hour'] == 12) {
                    $classe = ['demi', 'matin'];
                } elseif ($r['end_hour'] == 18) {
                    $classe = ['jour'];
                }
            } else {
                $classe = ['jour'];
            }

            $events[] = [
                'start' => $date_str,
                'end' => (new DateTime($date_str))->modify('+1 day')->format('Y-m-d'),
                'display' => 'background',
                'color' => ($user_id == $r['user_id']) ? '#00a812' : '#fdbaba',
                'className' => $classe,
                'title' => '',
                'pending' => false
            ];
            $current->modify('+1 day');
        }
    }
    // Maintenances
    foreach ($maintenances as $m) {
        $events[] = [
            'start' => $m['start_date'],
            'end' => (new DateTime($m['end_date']))->modify('+1 day')->format('Y-m-d'),
            'display' => 'background',
            'color' => '#77f1f1ff',
            'className' => ['maintenance'],
            'title' => 'Maintenance',
            'pending' => false
        ];
    }
log_error('EVENTS JSON: ' . json_encode($events));
    wp_send_json_success($events);
}

add_action('wp_ajax_dsi_get_user_reservations', 'dsi_get_user_reservations');
add_action('wp_ajax_nopriv_dsi_get_user_reservations', 'dsi_get_user_reservations');

function dsi_get_user_reservations() {
    check_ajax_referer('dsi_calendar');
    $product_id = intval($_POST['product_id']);
    $unit_id = intval($_POST['unit_id']);
    $user_id = get_current_user_id();
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT id, start_date, end_date, start_hour, end_hour
        FROM $table
        WHERE product_id = %d AND unit_id = %d AND user_id = %d AND returned = 0
        ORDER BY start_date ASC
    ", $product_id, $unit_id, $user_id), ARRAY_A);
    foreach ($results as &$r) {
        $r['start_date_fr'] = function_exists('dsi_format_date_fr') ? dsi_format_date_fr($r['start_date']) : $r['start_date'];
        $r['end_date_fr'] = function_exists('dsi_format_date_fr') ? dsi_format_date_fr($r['end_date']) : $r['end_date'];
        $r['time_label'] = dsi_format_time_label($r['start_hour'], $r['end_hour']);
    }
    wp_send_json_success($results);
}

function dsi_format_time_label($start, $end) {
    if ($start == 9 && $end == 12) return 'Matin';
    if ($start == 14 && $end == 18) return 'Après-midi';
    if ($start == 9 && $end == 18) return 'Journée';
    return "$start h - $end h";
}

add_action('wp_ajax_dsi_cancel_reservation', 'dsi_cancel_reservation');
add_action('wp_ajax_nopriv_dsi_cancel_reservation', 'dsi_cancel_reservation');

function dsi_cancel_reservation() {
    check_ajax_referer('dsi_calendar');
    $reservation_id = intval($_POST['reservation_id']);
    $user_id = get_current_user_id();
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    // Suppression directe de la réservation
    $deleted = $wpdb->delete(
        $table,
        ['id' => $reservation_id, 'user_id' => $user_id],
        ['%d', '%d']
    );
    if ($deleted !== false) {
        wp_send_json_success(['message' => 'Réservation supprimée.']);
    } else {
        wp_send_json_error(['message' => 'Erreur lors de la suppression.']);
    }
} 