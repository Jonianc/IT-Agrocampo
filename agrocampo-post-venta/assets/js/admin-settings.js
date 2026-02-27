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

    $(function () {
        initSelectors();
    });
})(jQuery);
