<?php
// Vue : Page admin de gestion des retours
// Variables attendues : $results, $product_list, $product_filter, $total, $per_page, $page
?>
<div class="wrap"><h1>Produits loués - Gestion des retours</h1>
<div class="tablenav top">
    <form method="get" action="" style="margin-bottom: 1em">
        <input type="hidden" name="page" value="dsi-location-returns" />
        <label for="product_id">Filtrer par produit : </label>
        <select name="product_id" id="product_id" onchange="this.form.submit()">
            <option value="0">Tous les produits</option>
            <?php foreach ($product_list as $prod): ?>
                <option value="<?= esc_attr($prod->product_id) ?>" <?= selected($product_filter, $prod->product_id, false) ?>><?= esc_html($prod->post_title) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php dsi_render_admin_pagination($total, $per_page, $page, $product_filter); ?>
</div>
<table class="widefat striped">
    <thead><tr>
        <th>ID</th><th>Produit</th><th>Unité</th><th>Client</th><th>Du</th><th>Au</th><th>Statut</th><th>Action</th>
    </tr></thead><tbody>
    <?php if ($results): ?>
        <?php $today = current_time('Y-m-d'); foreach ($results as $row):
            if ($row->returned) {
                $status = '✔️ Retourné';
            } elseif ($row->end_date < $today) {
                $status = '❌ Non retourné';
            } else {
                $status = '⏳ En cours';
            }
            $user = get_userdata($row->user_id);
            $client = $user ? esc_html($user->display_name) : 'Client ID: ' . intval($row->user_id);
        ?>
        <tr>
            <td><?= intval($row->id) ?></td>
            <td><?= esc_html($row->post_title) ?></td>
            <td>#<?= intval($row->unit_id) ?></td>
            <td><?= $client ?></td>
            <td><?= date('d/m/Y', strtotime($row->start_date)) . ' - ' . esc_html($row->start_hour) . 'h' ?></td>
            <td><?= date('d/m/Y', strtotime($row->end_date)) . ' - ' . esc_html($row->end_hour) . 'h' ?></td>
            <td><?= $status ?></td>
            <td>
                <?php if (!$row->returned): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="dsi_mark_returned" value="<?= intval($row->id) ?>"/>
                        <button type="submit" class="button">Marquer comme retourné</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="8">Aucun résultat trouvé.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<div class="tab-nav-bottom">
    <?php dsi_render_admin_pagination($total, $per_page, $page, $product_filter); ?>
</div>
</div> 