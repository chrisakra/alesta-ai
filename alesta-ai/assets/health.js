/* Health Check Dashboard — Alesta AI */
jQuery(function ($) {
    'use strict';

    /* Bouton Actualiser : recharge la page */
    $('#btn-health-refresh').on('click', function () {
        $(this).prop('disabled', true).text('⏳ Chargement…');
        window.location.reload();
    });
});
