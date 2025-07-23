<?php
// Ajout des champs personnalisés produit

add_action('woocommerce_product_options_general_product_data', function () {
    woocommerce_wp_text_input([
        'id' => '_prix_demi_journee',
        'label' => 'Prix demi-journée (€)',
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0']
    ]);

    woocommerce_wp_text_input([
        'id' => '_prix_journee',
        'label' => 'Prix journée (€)',
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0']
    ]);

    woocommerce_wp_text_input([
        'id' => '_prix_weekend',
        'label' => 'Prix week-end (€)',
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0']
    ]);

    woocommerce_wp_text_input([
        'id' => '_montant_caution',
        'label' => 'Montant de la caution (€)',
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0']
    ]);

    woocommerce_wp_text_input([
        'id' => '_nombre_articles_location',
        'label' => 'Nombre d’unités louables',
        'type' => 'number',
        'custom_attributes' => ['step' => '1', 'min' => '1']
    ]);
});

add_action('woocommerce_process_product_meta', function ($post_id) {
    $fields = ['_prix_demi_journee', '_prix_journee', '_prix_weekend', '_montant_caution', '_nombre_articles_location'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, wc_clean($_POST[$field]));
        }
    }
});