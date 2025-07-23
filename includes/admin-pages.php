<?php
// Fusion settings-page.php + returns-page.php

// --- MENU & PAGES ADMIN ---
add_action('admin_menu', function () {
    add_menu_page(
        'DSI Location',
        'DSI Location',
        'manage_options',
        'dsi-location-settings',
        'dsi_location_settings_page');
    add_submenu_page(
        'dsi-location-settings',
        'Maintenance',
        'Maintenance',
        'manage_woocommerce',
        'dsi-location-maintenance',
        'dsi_location_admin_maintenance_page'
    );
    add_submenu_page(
        'dsi-location-settings',
        'Retours',
        'Retours',
        'manage_options',
        'dsi-location-returns',
        'dsi_location_returns_page'
    );
});


// --- PAGE RÉGLAGES ---
function dsi_location_settings_page() {
    ?>
    <div class="wrap">
        <h1>Paramètres d'annulation DSI Location</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('dsi_location_settings');
            do_settings_sections('dsi-location-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', function () {
    register_setting('dsi_location_settings', 'dsi_location_cancellation_days');
    add_settings_section('dsi_location_main_section', 'Délais d\'annulation par catégorie', null, 'dsi-location-settings');
    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    foreach ($categories as $cat) {
        if ($cat->name === 'Non classé')
            continue;
        add_settings_field("cat_{$cat->term_id}", $cat->name, function () use ($cat) {
            $options = get_option('dsi_location_cancellation_days', []);
            $value = $options[$cat->term_id] ?? '';
            echo "<input type='number' name='dsi_location_cancellation_days[{$cat->term_id}]' value='" . esc_attr($value) . "' min='0' /> jours";
        }, 'dsi-location-settings', 'dsi_location_main_section');
    }
    add_settings_field('start_hour_am', 'Horaire debut matinée', function(){
        echo '<input type="number" name="start_hour_am" value="" min="0" />h';
    }, 'dsi-location-settings', 'dsi_location_main_section');


    add_settings_field('end_hour_am', 'Horaire fin matinée', function(){
        echo '<input type="number" name="end_hour_am" value="" min="0" />h';
    }, 'dsi-location-settings', 'dsi_location_main_section');

        add_settings_field('start_hour_pm', 'Horaire debut après-midi', function(){
        echo '<input type="number" name="start_hour_pm" value="" min="0" />h';
    }, 'dsi-location-settings', 'dsi_location_main_section');

        add_settings_field('end_hour_pm', 'Horaire fin après-midi', function(){
        echo '<input type="number" name="end_hour_pm" value="" min="0" />h';
    }, 'dsi-location-settings', 'dsi_location_main_section');





});

// --- PAGE MAINTENANCE ---
function dsi_location_admin_maintenance_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_maintenance';
    $today = current_time('Y-m-d');
    if (isset($_POST['dsi_add_maintenance'])) {
        $product_id = intval($_POST['product_id']);
        $unit_id = intval($_POST['unit_id']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        if ($product_id && $unit_id && $start_date && $end_date) {
            if (function_exists('dsi_location_is_in_maintenance') && dsi_location_is_in_maintenance($product_id, $unit_id, $start_date, $end_date)) {
                $type = 'error';
                $message = 'Chevauchement avec une maintenance existante !';
                include dirname(__DIR__) . '/views/partials/notice.php';
            } elseif (function_exists('dsi_location_is_in_reservation') && dsi_location_is_in_reservation($product_id, $unit_id, $start_date, $end_date)) {
                $type = 'error';
                $message = 'Chevauchement avec une réservation existante !';
                include dirname(__DIR__) . '/views/partials/notice.php';
            } else {
                dsi_location_set_maintenance($product_id, $unit_id, $start_date, $end_date);
                $type = 'success';
                $message = 'Période de maintenance ajoutée.';
                include dirname(__DIR__) . '/views/partials/notice.php';
            }
        }
    }
    $maintenances = $wpdb->get_results("SELECT * FROM $table ORDER BY start_date DESC");
    $products = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
    include dirname(__DIR__) . '/views/admin-maintenance.php';
}

add_action('admin_init', function () {
    if (isset($_POST['dsi_mark_maintenance_done'])) {
        global $wpdb;
        $maintenance_id = intval($_POST['dsi_mark_maintenance_done']);
        $table = $wpdb->prefix . 'dsi_location_maintenance';
        $wpdb->update($table, ['completed' => 1], ['id' => $maintenance_id]);
        $type = 'success';
        $message = 'Maintenance marquée comme terminée.';
        include dirname(__DIR__) . '/views/partials/notice.php';
        wp_redirect(admin_url('admin.php?page=dsi-location-maintenance'));
        exit;
    }
});

// --- PAGE RETOURS ---
function dsi_location_returns_page() {
    global $wpdb;
    $per_page = 20;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page - 1) * $per_page;
    $product_filter = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    $where = '';
    $params = [];
    if ($product_filter) {
        $where = 'WHERE r.product_id = %d';
        $params[] = $product_filter;
    }
    if ($where) {
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}dsi_location_reservations r $where",
            ...$params
        ));
    } else {
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dsi_location_reservations r");
    }
    $params_with_limits = $params;
    $params_with_limits[] = $per_page;
    $params_with_limits[] = $offset;
    $results = $where
        ? $wpdb->get_results($wpdb->prepare("
            SELECT r.*, p.post_title
            FROM {$wpdb->prefix}dsi_location_reservations r
            JOIN {$wpdb->prefix}posts p ON r.product_id = p.ID
            $where
            ORDER BY r.end_date DESC
            LIMIT %d OFFSET %d
        ", ...$params_with_limits))
        : $wpdb->get_results($wpdb->prepare("
            SELECT r.*, p.post_title
            FROM {$wpdb->prefix}dsi_location_reservations r
            JOIN {$wpdb->prefix}posts p ON r.product_id = p.ID
            ORDER BY r.end_date DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
    $product_list = $wpdb->get_results("
        SELECT DISTINCT r.product_id, p.post_title
        FROM {$wpdb->prefix}dsi_location_reservations r
        JOIN {$wpdb->prefix}posts p ON r.product_id = p.ID
        ORDER BY p.post_title ASC
    ");
    include dirname(__DIR__) . '/views/admin-returns.php';
}

add_action('admin_init', function () {
    if (isset($_POST['dsi_mark_returned'])) {
        global $wpdb;
        $id = intval($_POST['dsi_mark_returned']);
        $table = $wpdb->prefix . "dsi_location_reservations";
        $wpdb->update($table, ['returned' => 1], ['id' => $id]);
        $type = 'success';
        $message = 'Produit marqué comme retourné.';
        include dirname(__DIR__) . '/views/partials/notice.php';
        wp_redirect(admin_url('admin.php?page=dsi-location-returns'));
        exit;
    }
});

add_action('wp_ajax_dsi_admin_add_maintenance', 'dsi_admin_add_maintenance');
function dsi_admin_add_maintenance() {
    check_ajax_referer('dsi_calendar');
    $product_id = intval($_POST['product_id'] ?? 0);
    $unit_id = intval($_POST['unit_id'] ?? 0);
    $start_date = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date = sanitize_text_field($_POST['end_date'] ?? '');
    if (!$product_id || !$unit_id || !$start_date || !$end_date) {
        wp_send_json_error(['message' => 'Champs manquants.']);
    }
    if (function_exists('dsi_location_is_in_maintenance') && dsi_location_is_in_maintenance($product_id, $unit_id, $start_date, $end_date)) {
        wp_send_json_error(['message' => 'Chevauchement avec une maintenance existante !']);
    }
    $result = dsi_location_set_maintenance($product_id, $unit_id, $start_date, $end_date);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success(['message' => 'Période de maintenance ajoutée.']);
}

// --- HELPERS ---
function dsi_render_admin_pagination($total_items, $per_page, $current_page, $product_filter = 0) {
    $total_pages = ceil($total_items / $per_page);
    if ($total_pages <= 1) return;
    $base_url = admin_url('admin.php?page=dsi-location-returns');
    if ($product_filter) {
        $base_url = add_query_arg('product_id', $product_filter, $base_url);
    }
    $prev_page = max(1, $current_page - 1);
    $next_page = min($total_pages, $current_page + 1);
    echo '<div class="tablenav-pages">';
    echo '<span class="displaying-num">' . esc_html($total_items) . ' élément' . ($total_items > 1 ? 's' : '') . '</span>';
    echo '<span class="pagination-links">';
    if ($current_page > 1) {
        echo '<a class="first-page button" href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '"><span class="screen-reader-text">Première page</span><span aria-hidden="true">&laquo;</span></a>';
    } else {
        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
    }
    if ($current_page > 1) {
        echo '<a class="prev-page button" href="' . esc_url(add_query_arg('paged', $prev_page, $base_url)) . '"><span class="screen-reader-text">Page précédente</span><span aria-hidden="true">&lsaquo;</span></a>';
    } else {
        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
    }
    echo '<span class="paging-input">';
    echo '<label for="current-page-selector" class="screen-reader-text">Page actuelle</label>';
    echo '<input class="current-page" id="current-page-selector" type="text" name="paged" value="' . esc_attr($current_page) . '" size="1" aria-describedby="table-paging">';
    echo '<span class="tablenav-paging-text"> sur <span class="total-pages">' . esc_html($total_pages) . '</span></span>';
    echo '</span>';
    if ($current_page < $total_pages) {
        echo '<a class="next-page button" href="' . esc_url(add_query_arg('paged', $next_page, $base_url)) . '"><span class="screen-reader-text">Page suivante</span><span aria-hidden="true">&rsaquo;</span></a>';
    } else {
        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
    }
    if ($current_page < $total_pages) {
        echo '<a class="last-page button" href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '"><span class="screen-reader-text">Dernière page</span><span aria-hidden="true">&raquo;</span></a>';
    } else {
        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
    }
    echo '</span>';
    echo '</div>';
} 