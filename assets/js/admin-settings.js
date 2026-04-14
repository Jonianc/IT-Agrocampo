(function ($) {
    function initMediaSelector(config) {
        var frame;

        $(config.selectButton).on('click', function (e) {
            e.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: config.title,
                button: { text: config.buttonText },
                library: { type: 'image' },
                multiple: false,
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                if (!attachment) {
                    return;
                }

                $(config.input).val(attachment.id);
                $(config.preview)
                    .attr('src', attachment.url)
                    .show();
            });

            frame.open();
        });

        $(config.removeButton).on('click', function (e) {
            e.preventDefault();
            $(config.input).val('');
            $(config.preview).attr('src', '').hide();
        });
    }

    function initSelectors() {
        initMediaSelector({
            selectButton: '#agp-pv-logo-select',
            removeButton: '#agp-pv-logo-remove',
            input: '#agp-pv-logo-attachment-id',
            preview: '#agp-pv-logo-preview',
            title: 'Seleccionar logo',
            buttonText: 'Usar logo',
        });

        initMediaSelector({
            selectButton: '#agp-pv-app-icon-192-select',
            removeButton: '#agp-pv-app-icon-192-remove',
            input: '#agp-pv-app-icon-192-id',
            preview: '#agp-pv-app-icon-192-preview',
            title: 'Seleccionar icono 192x192',
            buttonText: 'Usar icono 192x192',
        });

        initMediaSelector({
            selectButton: '#agp-pv-app-icon-512-select',
            removeButton: '#agp-pv-app-icon-512-remove',
            input: '#agp-pv-app-icon-512-id',
            preview: '#agp-pv-app-icon-512-preview',
            title: 'Seleccionar icono 512x512',
            buttonText: 'Usar icono 512x512',
        });
    }

    function initLubricantsSettingsUi() {
        var $quickTypesWrapper = $('[data-lubricants-quick-types]');
        if (!$quickTypesWrapper.length) {
            return;
        }

        var $list = $quickTypesWrapper.find('[data-lubricants-quick-types-list]');
        var $textarea = $quickTypesWrapper.find('[data-lubricants-quick-types-textarea]');
        var $form = $quickTypesWrapper.closest('form');
        var $usage = $('#agp-pv-lubricants-usage-mode');
        var $fallback = $('#agp-pv-lubricants-fallback-mode');

        function updatePreview() {
            $('[data-lubricants-preview-usage]').text($usage.val() || '');
            $('[data-lubricants-preview-fallback]').text($fallback.val() || '');
            $('[data-lubricants-field-state]').each(function () {
                var fieldKey = $(this).data('lubricants-field-state');
                var value = $(this).val() || 'hidden';
                $('[data-lubricants-preview-field=\"' + fieldKey + '\"]').attr('data-state', value);
            });
        }

        function serializeQuickTypes() {
            var lines = [];
            $list.find('[data-lubricants-quick-type-row]').each(function () {
                var type = $.trim($(this).find('[data-lubricants-quick-type-name]').val() || '');
                var unit = $.trim($(this).find('[data-lubricants-quick-type-unit]').val() || '');
                if (!type) {
                    return;
                }
                lines.push(unit ? (type + '|' + unit) : type);
            });
            $textarea.val(lines.join('\\n'));
        }

        function createRow(typeValue, unitValue) {
            var $row = $('<div class=\"agp-pv-lubricants-quick-types__row\" data-lubricants-quick-type-row>' +
                '<input type=\"text\" class=\"regular-text\" data-lubricants-quick-type-name placeholder=\"Tipo\">' +
                '<input type=\"text\" class=\"small-text\" data-lubricants-quick-type-unit placeholder=\"Unidad\">' +
                '<button type=\"button\" class=\"button button-link-delete\" data-lubricants-quick-type-remove>Quitar</button>' +
                '</div>');
            $row.find('[data-lubricants-quick-type-name]').val(typeValue || '');
            $row.find('[data-lubricants-quick-type-unit]').val(unitValue || '');
            return $row;
        }

        $quickTypesWrapper.on('click', '[data-lubricants-quick-type-add]', function () {
            $list.append(createRow('', ''));
        });

        $quickTypesWrapper.on('click', '[data-lubricants-quick-type-remove]', function () {
            $(this).closest('[data-lubricants-quick-type-row]').remove();
            if (!$list.find('[data-lubricants-quick-type-row]').length) {
                $list.append(createRow('', ''));
            }
            serializeQuickTypes();
        });

        $quickTypesWrapper.on('input', '[data-lubricants-quick-type-name],[data-lubricants-quick-type-unit]', serializeQuickTypes);
        $usage.on('change', updatePreview);
        $fallback.on('change', updatePreview);
        $('[data-lubricants-field-state]').on('change', updatePreview);
        $form.on('submit', serializeQuickTypes);

        serializeQuickTypes();
        updatePreview();
    }

    $(function () {
        initSelectors();
        initLubricantsSettingsUi();
    });
})(jQuery);
