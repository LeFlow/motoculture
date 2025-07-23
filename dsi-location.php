<?php
/*
* Plugin Name: DSI Location
* Plugin URI:
* Description: Plugin de réservation et de location pour WooCommerce avec gestion de calendriers, acomptes et retours.
* Version: 1.0.0
* Date: 2025-07-07
* Author: DSI
* Author URI
*/

if (!defined('ABSPATH')) exit;

define('DSI_LOCATION_PATH', plugin_dir_path(__FILE__));
define('DSI_PLUGIN_DIR', plugin_dir_path(__FILE__));


// Includes
register_activation_hook(__FILE__, 'dsi_location_create_table');
require_once DSI_LOCATION_PATH . 'includes/utils.php';
require_once DSI_LOCATION_PATH . 'includes/install.php';
require_once DSI_LOCATION_PATH . 'includes/product-meta.php';
require_once DSI_LOCATION_PATH . 'includes/admin-pages.php';
require_once DSI_LOCATION_PATH . 'includes/booking-calendar-handler.php';
require_once DSI_LOCATION_PATH . 'includes/maintenance-handler.php';
require_once DSI_LOCATION_PATH . 'includes/frontend-hooks.php';
require_once DSI_LOCATION_PATH . 'includes/woocommerce-hooks.php';

add_action('wp_enqueue_scripts', function () {
    if (is_product()) {
        wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', [], null, true);
        $js_file = plugin_dir_path(__FILE__) . 'js/dsi-front.js';
        $js_version = file_exists($js_file) ? filemtime($js_file) : '1.0';
        wp_enqueue_script('dsi-front', plugin_dir_url(__FILE__) . 'js/dsi-front.js', [], $js_version, true);
        wp_localize_script('dsi-front', 'DSI_Calendar', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dsi_calendar')
        ]);
        wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css');
        wp_enqueue_style('dsi-calendar-style', plugin_dir_url(__FILE__) . 'css/style-calendar.css', [], '1.0', false );
    }
});

add_action('admin_enqueue_scripts', function ($hook) {

    // log_error('HOOK = ' . $hook);

    if ($hook === 'dsi-location_page_dsi-location-maintenance') {
        wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', [], null, true);
        wp_enqueue_script('dsi-back', plugin_dir_url(__FILE__) . 'js/dsi-back.js', [], false, true);
        wp_localize_script('dsi-back', 'DSI_Maintenance', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dsi_maintenance')
        ]);
        wp_localize_script('dsi-back', 'DSI_Calendar', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dsi_calendar')
        ]);

        wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css');
        wp_enqueue_style('dsi-calendar-style', plugin_dir_url(__FILE__) . 'css/style-admin-location.css', [], '1.0', false );

    }

});


add_action( 'wp_enqueue_scripts', 'dsi_load_plugin_css' );
function dsi_load_plugin_css() {
    wp_enqueue_style( 'main', plugin_dir_url( __FILE__ ) . 'css/dsi-location-main.css' );
}

// === DSI Location : Intégration WooCommerce pour réservation ===
add_filter('woocommerce_add_cart_item_data', 'dsi_location_add_cart_item_data', 10, 3);
function dsi_location_add_cart_item_data($cart_item_data, $product_id, $variation_id) {
    // On récupère les infos de réservation du POST
    $fields = ['unit_id', 'start_date', 'end_date', 'time-start', 'time-end'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $cart_item_data['dsi_location'][$field] = sanitize_text_field($_POST[$field]);
        }
    }
    // expected_date = end_date à la réservation
    if (isset($_POST['end_date'])) {
        $cart_item_data['dsi_location']['expected_date'] = sanitize_text_field($_POST['end_date']);
    }
    // Flag pour identifier une réservation DSI
    if (!empty($cart_item_data['dsi_location'])) {
        $cart_item_data['dsi_location']['is_dsi_reservation'] = true;
    }
    return $cart_item_data;
}