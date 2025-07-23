<?php
// Vue : Formulaire de réservation, modale et calendrier pour chaque unité
// Variables attendues : $product_id, $nb_units, $today
?>
<div style="clear: both"></div>
<div id="dsi-unit-calendars">
<?php for ($i = 1; $i <= $nb_units; $i++) : ?>
    <div class="dsi-reservation-block" data-unit-id="<?= $i ?>">
        <h4>Disponibilités - Unité #<?= $i ?></h4>
        <div class="calendar-row">
            <form id="form-calendar-<?= $i ?>" class="dsi-reservation-form" method="post" style="margin-bottom:10px">
                <div class="box-resa">
                    <input type="hidden" name="product_id" value="<?= esc_attr($product_id) ?>">
                    <input type="hidden" name="unit_id" value="<?= $i ?>">
                    <div class="box-input">
                        <label>Date de début : </label><input type="date" class="start_date select-date" name="start_date" min="<?= $today ?>" required><br>
                        <div class="radio-btn start-radio-btn">
                            <input type="radio" class="jour" name="time-start" value="jour" checked="checked"/><label for="jour">Journée</label>
                            <input type="radio" class="matin" name="time-start" value="matin" /><label for="matin">Matinée</label>
                            <input type="radio" class="aprem" name="time-start" value="aprem" /><label for="aprem">Après midi</label>
                        </div>
                    </div>
                    <div class="check-">
                        <input type="checkbox" name="multidate" class="multidate"/><label for="multidate">Faire une réservation sur plusieurs jours</label>
                    </div>
                    <div class="box-input end-date-block" style="display:none">
                        <label style="width:170px">Date de fin : </label><input type="date" class="end_date select-date" name="end_date" min="<?= $today ?>"><br>
                        <div class="radio-btn end-radio-btn">
                            <input type="radio" class="jour" name="time-end" value="jour" checked="checked"/><label for="jour">Journée</label>
                            <input type="radio" class="matin" name="time-end" value="matin" /><label for="matin">Matinée</label>
                            <input type="radio" class="aprem" name="time-end" value="aprem" /><label for="aprem">Après midi</label>
                        </div>
                    </div>
                </div>
                <div class="message"></div>
                <button class="btn-resa" type="submit" disabled="true">Réserver</button>
                <button class="btn-view-resa" type="button" data-unit-id="<?= $i ?>">Voir mes réservations</button>
            </form>
            <div class="dsi-reservation-modal" style="display:none;">
                <div class="dsi-reservation-modal-content">
                    <span class="dsi-close-modal" style="float:right; cursor:pointer; font-size: 50px">&times;</span>
                    <h3>Mes réservations</h3>
                    <div class="dsi-reservation-table-wrapper">
                        <table class="widefat striped user-reservations-table">
                            <thead>
                                <tr>
                                    <th>Du</th>
                                    <th>Heure de retrait</th>
                                    <th>Au</th>
                                    <th>Heure de retour</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody class="reservations-list">
                                <tr><td colspan="4">Chargement...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="dsi-calendar" data-product-id="<?= esc_attr($product_id) ?>" data-unit-id="<?= $i ?>" style="margin-bottom:10px;"></div>
        </div>
    </div>
<?php endfor; ?>
</div> 