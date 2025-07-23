<?php
// Vue : Page admin de gestion de la maintenance
// Variables attendues : $products, $today, $maintenances
?>
<div class="wrap">
    <h1>Maintenance des articles louables</h1>
    <h2>Ajouter une période de maintenance</h2>
    <div class="maintenace-form" style="display: flex">
        <form method="post" id="dsi-maintenance-form" style="width: 33%">
            <table class="form-table">
                <tr>
                    <th><label for="product_id">Produit</label></th>
                    <td>
                        <select name="product_id" required>
                            <option value="">-- Choisir un produit --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= esc_attr($product->ID) ?>"><?= esc_html($product->post_title) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="unit_id">Unité (ex. #1, #2...)</label></th>
                    <td>
                        <input type="number" name="unit_id" required min="1" value="1">
                        <button type="button" id="btn-load-calendar" class="button">Voir</button>
                    </td>
                </tr>
                <tr>
                    <th><label for="start_date">Date de début</label></th>
                    <td><input class="start_date" type="date" name="start_date" min="<?= $today ?>" required></td>
                </tr>
                <tr>
                    <th><label for="end_date">Date de fin</label></th>
                    <td><input class="end_date" type="date" name="end_date" min="<?= $today ?>" required></td>
                </tr>
            </table>
            <?php wp_nonce_field('dsi_calendar', 'dsi_calendar_nonce'); ?>
            <div class="message"></div>
            <?php submit_button('Ajouter la maintenance', 'primary', 'dsi_add_maintenance'); ?>
        </form>
        <div class="dsi-reservation-block">
            <div id="calendar" class="dsi-maintenance-calendar" style="margin-bottom:10px"></div>
        </div>
    </div>
    <hr>
    <h2>Liste des périodes de maintenance</h2>
    <div id="dsi-maintenance-list">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Produit</th>
                <th>Unité</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($maintenances as $m): ?>
                <?php
                    $action = !$m->completed ? '<form method="post" style="display:inline;"><input type="hidden" name="dsi_mark_maintenance_done" value="' . intval($m->id) . '"><button type="submit" class="button">Maintenance términée</button></form>' : '';
                    if($m->completed){
                        $icone = '✔️ Terminée';
                    }elseif($m->end_date < $today){
                        $icone = '❌ Non terminé';
                    }else{
                        $icone = '⏳ En cours';
                    }
                    $dateStart = date_create($m->start_date);
                    $startDate = date_format($dateStart, 'd/m/Y');
                    $dateEnd = date_create($m->end_date);
                    $endDate = date_format($dateEnd, 'd/m/Y');
                    $title = get_the_title($m->product_id);
                ?>
                <tr>
                    <td><?= esc_html($m->id); ?></td>
                    <td><?= $title ? esc_html($title) : '(Produit supprimé)'; ?></td>
                    <td>#<?= esc_html($m->unit_id); ?></td>
                    <td><?= $startDate; ?></td>
                    <td><?= $endDate; ?></td>
                    <td><?= $icone ?></td>
                    <td><?= $action ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<script>
jQuery(document).ready(function($){
    $('#dsi-maintenance-form').on('submit', function(e){
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button[type="submit"]');
        var msg = form.find('.message');
        msg.html('');
        btn.prop('disabled', true);
        var data = {
            action: 'dsi_admin_add_maintenance',
            product_id: form.find('[name="product_id"]').val(),
            unit_id: form.find('[name="unit_id"]').val(),
            start_date: form.find('[name="start_date"]').val(),
            end_date: form.find('[name="end_date"]').val(),
            _ajax_nonce: form.find('[name="dsi_calendar_nonce"]').val()
        };
        $.post(ajaxurl, data, function(response){
            btn.prop('disabled', false);
            if(response.success){
                msg.html('<div class="notice notice-success">'+response.data.message+'</div>');
                // Rafraîchir la liste des maintenances
                $('#dsi-maintenance-list').load(window.location.href + ' #dsi-maintenance-list > *');
                // Rafraîchir le calendrier
                $('#btn-load-calendar').trigger('click');
                form[0].reset();
            }else{
                msg.html('<div class="notice notice-error">'+(response.data && response.data.message ? response.data.message : 'Erreur inconnue')+'</div>');
            }
        });
    });
});
</script> 