jQuery(function ($) {

    // Save Anthropic API key + model
    $('#btn-save-settings').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Enregistrement…');
        $.post(AlestaAI.ajax_url, {
            action:  'alesta_save_settings',
            nonce:   AlestaAI.nonce,
            api_key: $('#setting-apikey').val(),
            model:   $('#setting-model').val(),
        }, function (r) {
            showFeedback(r.success ? 'success' : 'error',
                         r.data ? r.data.message : 'Erreur inconnue.');
            $btn.prop('disabled', false).text('Enregistrer');
            if (r.success) updateBadge('claude', true);
        });
    });

    // Test Anthropic connection
    $('#btn-test-api').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Test en cours…');
        $.post(AlestaAI.ajax_url, {action: 'alesta_test_api', nonce: AlestaAI.nonce}, function (r) {
            showFeedback(r.success ? 'success' : 'error',
                         r.success ? '✓ Connexion réussie à Claude !' : '✗ ' + (r.data ? r.data.message : 'Erreur'));
            $btn.prop('disabled', false).text('Tester la connexion');
            updateBadge('claude', r.success);
        });
    });

    function updateBadge(service, ok) {
        var $b = $('#badge-' + service);
        if (!$b.length) return;
        if (ok) {
            $b.css({background:'#d1fae5',color:'#065f46',borderColor:'#6ee7b7'});
            $b.html('&#10003; Claude connecte');
        } else {
            $b.css({background:'#fee2e2',color:'#991b1b',borderColor:'#fca5a5'});
            $b.html('&#10007; Claude non configure');
        }
    }

    function showFeedback(type, msg) {
        $('#settings-feedback')
            .show()
            .attr('class', 'alesta-feedback alesta-feedback--' + type)
            .text(msg);
    }
});
