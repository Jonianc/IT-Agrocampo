(function ($) {
    function initLogoSelector() {
        var frame;

        $('#agp-pv-logo-select').on('click', function (e) {
            e.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: 'Seleccionar logo',
                button: { text: 'Usar logo' },
                library: { type: 'image' },
                multiple: false,
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                if (!attachment) {
                    return;
                }

                $('#agp-pv-logo-attachment-id').val(attachment.id);
                $('#agp-pv-logo-preview')
                    .attr('src', attachment.url)
                    .show();
            });

            frame.open();
        });

        $('#agp-pv-logo-remove').on('click', function (e) {
            e.preventDefault();
            $('#agp-pv-logo-attachment-id').val('');
            $('#agp-pv-logo-preview').attr('src', '').hide();
        });
    }

    $(function () {
        initLogoSelector();
    });
})(jQuery);
