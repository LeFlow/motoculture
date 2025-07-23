// Fichier fusionné : dsi-front.js
// Gère le calendrier, la réservation, la modale et les contrôles de dates côté utilisateur

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.dsi-calendar').forEach(function (calendarEl) {
        const parent = calendarEl.closest('.dsi-reservation-block');
        const productId = calendarEl.dataset.productId;
        const unitId = calendarEl.dataset.unitId;
        const form = parent.querySelector('.dsi-reservation-form');
        let isSubmitting = false;

        // Initialisation du calendrier
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            selectable: false,
            selectOverlap: false,
            firstDay: 1,
            locale: 'fr',
            contentHeight: '300px',
            selectAllow: function (info) {
                return (info.start >= getDateWithoutTime(new Date()));
            },
            buttonText: {
                today: 'Aujourd\'hui'
            },
            events: function (info, successCallback, failureCallback) {
                fetch(DSI_Calendar.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'dsi_get_calendar_events',
                        product_id: productId,
                        unit_id: unitId,
                        start: info.startStr,
                        end: info.endStr,
                        _ajax_nonce: DSI_Calendar.nonce
                    })
                })
                .then(res => res.json().catch(() => null))
                .then(data => {
                    console.log('Calendar AJAX response:', data);
                    if (data && data.success) {
                        successCallback(data.data);
                    } else {
                        failureCallback((data && data.data && data.data.message) || 'Erreur serveur');
                    }
                })
                .catch(err => {
                    failureCallback('Erreur serveur');
                    console.error('Calendar AJAX error:', err);
                });
            },
            select: function (info) {
                form.style.display = 'block';
                form.querySelector('input[name="unit_id"]').value = unitId;
                form.querySelector('input[name="start_date"]').value = info.startStr;
                form.querySelector('input[name="end_date"]').value = new Date(info.end).toISOString().split('T')[0];
            },
            eventColor: '#d9534f',
            eventTextColor: 'white'
        });

        calendar.render();
        calendarEl._calendar = calendar;

        // Gestion de l'envoi du formulaire (vérification + soumission)
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (isSubmitting) return;
            isSubmitting = true;
            const formData = new FormData(form);
            const product_id = formData.get('product_id');
            const unit_id = formData.get('unit_id');
            let start_date = formData.get('start_date');
            let end_date = formData.get('end_date');
            let time_start = formData.get('time-start');
            let time_end = formData.get('time-end');
            const multidate = form.querySelector('input[name="multidate"]:checked');
            if (!product_id || !unit_id || !start_date || !time_start) {
                alert('Merci de remplir tous les champs obligatoires.');
                isSubmitting = false;
                return;
            }
            if (multidate && (!end_date || !time_end)) {
                alert('Merci de renseigner la date et la plage de fin.');
                isSubmitting = false;
                return;
            }
            if (!multidate) {
                end_date = start_date;
                time_end = time_start;
            }
            const btn = form.querySelector('.btn-resa');
            if (btn) btn.disabled = true;
            // Vérification disponibilité via AJAX
            const params = new URLSearchParams();
            params.append('action', 'dsi_check_reservation_availability');
            params.append('product_id', product_id);
            params.append('unit_id', unit_id);
            params.append('start_date', start_date);
            params.append('end_date', end_date);
            params.append('time_start', time_start);
            params.append('time_end', time_end);
            params.append('_ajax_nonce', DSI_Calendar.nonce);
            fetch(DSI_Calendar.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(function(response) {
                if (response.success) {
                    // Ajout au panier WooCommerce via AJAX natif
                    if (typeof wc_add_to_cart_params !== 'undefined') {
                        jQuery.post({
                            url: wc_add_to_cart_params.ajax_url,
                            data: {
                                action: 'woocommerce_add_to_cart',
                                product_id: product_id,
                                quantity: 1,
                                unit_id: unit_id,
                                start_date: start_date,
                                end_date: end_date,
                                'time-start': time_start,
                                'time-end': time_end
                            },
                            success: function(response) {
                                // Affichage message succès
                                const messageDiv = form.querySelector('.message');
                                if (messageDiv) {
                                    messageDiv.className = 'message notice notice-success';
                                    messageDiv.innerText = 'Réservation ajoutée au panier !';
                                }
                                // Rafraîchir le mini-panier
                                if (typeof jQuery !== 'undefined' && jQuery('body').hasClass('woocommerce')) {
                                    jQuery(document.body).trigger('added_to_cart');
                                }
                                // Autres actions (calendrier, reset, etc.)
                                calendar.refetchEvents();
                                form.reset();
                                if (btn) btn.disabled = false;
                            }
                        });
                    } else {
                        // Fallback : message d'erreur
                        const messageDiv = form.querySelector('.message');
                        if (messageDiv) {
                            messageDiv.className = 'message notice notice-error';
                            messageDiv.innerText = 'Erreur : WooCommerce AJAX non disponible.';
                        }
                    }
                } else {
                    const messageDiv = form.querySelector('.message');
                    if (messageDiv) {
                        messageDiv.className = 'message notice notice-error';
                        messageDiv.innerText = response.data && response.data.message ? response.data.message : 'Créneau non disponible.';
                    } else {
                        alert(response.data && response.data.message ? response.data.message : 'Créneau non disponible.');
                    }
                    if (btn) btn.disabled = false;
                }
                isSubmitting = false;
            })
            .catch(function(err) {
                const messageDiv = form.querySelector('.message');
                if (messageDiv) {
                    messageDiv.className = 'message notice notice-error';
                    messageDiv.innerText = 'Erreur lors de la vérification de disponibilité.';
                } else {
                    alert('Erreur lors de la vérification de disponibilité.');
                }
                if (btn) btn.disabled = false;
                isSubmitting = false;
            });
        });
    });

    function getDateWithoutTime(dt) {
        dt.setHours(0, 0, 0, 0);
        return dt;
    }

    // Contrôles de dates et UI
    jQuery(function($) {
        $('.start_date, .end_date').on('change', function () {
            const form = $(this).closest('form');
            const startDateInput = form.find('.start_date');
            const endDateInput = form.find('.end_date');
            const startDateVal = startDateInput.val();
            if (startDateVal) {
                const startDate = new Date(startDateVal);
                startDate.setDate(startDate.getDate() + 1);
                const minDate = startDate.toISOString().split('T')[0];
                endDateInput.attr('min', minDate);
                if (endDateInput.val() && endDateInput.val() < minDate) {
                    endDateInput.val('');
                    form.find('.message').addClass('notice notice-info').text('La date de début doit être inférieure à la date de fin !');
                }
            }
            form.find('.btn-resa').attr('disabled', false);
        });
        $('.multidate').on('change', function(){
            const form = $(this).closest('form');
            const endDate = form.find('.end_date');
            if ($(this).is(':checked')) {
                form.find('.end-date-block').css('display', 'block');
            }else{
                form.find('.end-date-block').css('display', 'none');
                form.find(endDate).val('');
            }
        });
        // Modale réservations utilisateur
        $('.btn-view-resa').on('click', function () {
            const form = $(this).closest('form');
            const parent = form.closest('.dsi-reservation-block');
            const modal = parent.find('.dsi-reservation-modal');
            const productId = form.find('input[name="product_id"]').val();
            const unitId = form.find('input[name="unit_id"]').val();
            modal.data('product-id', productId);
            modal.data('unit-id', unitId);
            const tableBody = modal.find('.reservations-list');
            tableBody.html('<tr><td colspan="4">Chargement...</td></tr>');
            $.post(DSI_Calendar.ajax_url, {
                action: 'dsi_get_user_reservations',
                product_id: productId,
                unit_id: unitId,
                _ajax_nonce: DSI_Calendar.nonce
            }, function(response) {
                if (response.success) {
                    const reservations = response.data;
                    if (reservations.length === 0) {
                        tableBody.html('<tr><td colspan="4">Aucune réservation.</td></tr>');
                        return;
                    }
                    tableBody.empty();
                    reservations.forEach(res => {
                        const row = `
                            <tr data-id="${res.id}">
                                <td>${res.start_date_fr || toDate(res.start_date)}</td>
                                <td>${res.start_hour}</td>
                                <td>${res.end_date_fr || toDate(res.end_date)}</td>
                                <td>${res.end_hour}</td>
                                <td>
                                    <button type="button" class="button btn-confirmer validate-cancel-reservation" style="display: none;">Confirmer</button>
                                    <button class="button btn-annuler cancel-reservation">Annuler</button>
                                </td>
                            </tr>`;
                        tableBody.append(row);
                    });
                } else {
                    tableBody.html('<tr><td colspan="4">Erreur de chargement</td></tr>');
                }
            });
            modal.show();
        });
        $(document).on('click', '.dsi-close-modal', function () {
            $(this).closest('.dsi-reservation-modal').hide();
        });
        console.log('init handler validate-cancel-reservation');
        $(document).on('click', '.validate-cancel-reservation', function () {
            console.log('click sur Confirmer', this);
            const row = $(this).closest('tr');
            const modal = row.closest('.dsi-reservation-modal');
            const resId = row.data('id');
            //if (!confirm('Annuler cette réservation ?')) return;
            $.post(DSI_Calendar.ajax_url, {
                action: 'dsi_cancel_reservation',
                reservation_id: resId,
                _ajax_nonce: DSI_Calendar.nonce
            }, function(response) {
                if (response.success) {
                    row.remove();
                    const productId = modal.data('product-id');
                    const unitId = modal.data('unit-id');
                    const block = $(`.dsi-reservation-block[data-unit-id="${unitId}"]`);
                    const calendarEl = block.find('.dsi-calendar').get(0);
                    if (calendarEl && calendarEl._calendar) {
                        calendarEl._calendar.refetchEvents();
                    } else {
                        const fcInstance = FullCalendar.getCalendar(calendarEl);
                        if (fcInstance) fcInstance.refetchEvents();
                    }
                } else {
                    alert('Erreur : ' + (response.data?.message || 'Impossible d\'annuler.'));
                }
            });
        });
        $(document).on('click', '.dsi-reservation-modal', function (e) {
            if (e.target === e.currentTarget) {
                $(this).fadeOut(200);
            }
        });
        $(document).on('keydown', function(e) {
            if (e.key === "Escape") {
                $('.dsi-reservation-modal').fadeOut(500);
            }
        });
        $(document).on('click', '.cancel-reservation', function () {
            $(this).css('display', 'none');
            $(this).closest('td').find('.validate-cancel-reservation').removeAttr('style');
        });
    });
});

// Ajoute la fonction toDate si elle n'existe pas déjà
function toDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('fr-FR');
} 