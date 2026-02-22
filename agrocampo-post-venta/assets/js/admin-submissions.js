(function ($) {
    'use strict';

    const cfg = window.agpPvAdminSubmissions || {};
    const $button = $('#agp-pv-regenerate-all-pdf');
    const $progressWrap = $('#agp-pv-regenerate-progress');
    const $progressBar = $('#agp-pv-regenerate-progress-bar');
    const $progressText = $('#agp-pv-regenerate-progress-text');

    if (!$button.length || !cfg.ajaxUrl || !cfg.nonce) {
        return;
    }

    const fmt = function (template, values) {
        let output = String(template || '');
        values.forEach(function (value, index) {
            output = output.replace('%' + (index + 1) + '$d', String(value));
        });
        return output;
    };

    const setRunningState = function (running) {
        $button.prop('disabled', running);
        if (running) {
            $button.text((cfg.labels && cfg.labels.buttonRunning) ? cfg.labels.buttonRunning : 'Regenerando...');
        } else {
            $button.text((cfg.labels && cfg.labels.buttonDefault) ? cfg.labels.buttonDefault : 'Regenerar todos los PDF');
        }
    };

    const runBatch = function (offset, accumSuccess, accumFailed) {
        $.post(cfg.ajaxUrl, {
            action: 'agp_pv_regenerate_all_pdf_batch',
            nonce: cfg.nonce,
            offset: offset,
            limit: cfg.batchSize || 25
        }).done(function (response) {
            if (!response || !response.success || !response.data) {
                throw new Error('Invalid response');
            }

            const data = response.data;
            const processed = Number(data.processed || 0);
            const total = Number(data.total || 0);
            const success = accumSuccess + Number(data.success || 0);
            const failed = accumFailed + Number(data.failed || 0);
            const percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 100;

            $progressBar.val(percent);
            $progressText.text(fmt(cfg.messages.running, [processed, total, success, failed]));

            if (data.done) {
                setRunningState(false);
                $progressText.text(fmt(cfg.messages.done, [processed, success, failed]));
                return;
            }

            runBatch(Number(data.next_offset || 0), success, failed);
        }).fail(function () {
            setRunningState(false);
            $progressText.text(cfg.messages.error || 'Error');
        });
    };

    $button.on('click', function () {
        if (!window.confirm((cfg.labels && cfg.labels.confirm) ? cfg.labels.confirm : '¿Regenerar todos los PDF históricos? Esta acción puede tardar varios minutos.')) {
            return;
        }

        $progressWrap.prop('hidden', false);
        $progressBar.val(0);
        $progressText.text((cfg.messages && cfg.messages.starting) ? cfg.messages.starting : 'Iniciando...');
        setRunningState(true);
        runBatch(0, 0, 0);
    });
})(jQuery);
