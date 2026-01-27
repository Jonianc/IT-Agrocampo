(function ($) {
    function initConditionalFields() {
        var $tipoServicio = $('#agp-pv-tipo-servicio');
        var $tipoMantencion = $('#agp-pv-tipo-mantencion');
        var $fechaField = $('[data-condition="fecha"]');
        var $mantencionField = $('[data-condition="tipo-mantencion"]');
        var $cantidadHorasField = $('[data-condition="cantidad-horas"]');
        var $garantiaFields = $('[data-condition="garantia"]');

        function updateConditions() {
            var tipoServicio = $tipoServicio.val();
            var tipoMantencion = $tipoMantencion.val();

            if (tipoServicio === 'two') {
                $fechaField.hide();
                $garantiaFields.show();
            } else {
                $fechaField.show();
                $garantiaFields.hide();
            }

            if (tipoServicio === 'Interno') {
                $mantencionField.show();
            } else {
                $mantencionField.hide();
                $tipoMantencion.val('');
            }

            if (tipoMantencion === 'OTRO') {
                $cantidadHorasField.show();
            } else {
                $cantidadHorasField.hide();
                $('#agp-pv-cantidad-horas').val('');
            }
        }

        $tipoServicio.on('change', updateConditions);
        $tipoMantencion.on('change', updateConditions);
        updateConditions();
    }

    function initSignatures() {
        $('.agp-pv-signature').each(function () {
            var $wrapper = $(this);
            var canvas = $wrapper.find('canvas')[0];
            var ctx = canvas.getContext('2d');
            var drawing = false;

            function startDraw(e) {
                drawing = true;
                ctx.beginPath();
                ctx.moveTo(getX(e), getY(e));
            }

            function draw(e) {
                if (!drawing) {
                    return;
                }
                ctx.lineTo(getX(e), getY(e));
                ctx.stroke();
            }

            function endDraw() {
                drawing = false;
            }

            function getX(e) {
                var rect = canvas.getBoundingClientRect();
                var clientX = e.touches ? e.touches[0].clientX : e.clientX;
                return clientX - rect.left;
            }

            function getY(e) {
                var rect = canvas.getBoundingClientRect();
                var clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return clientY - rect.top;
            }

            canvas.addEventListener('mousedown', startDraw);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', endDraw);
            canvas.addEventListener('mouseleave', endDraw);
            canvas.addEventListener('touchstart', startDraw);
            canvas.addEventListener('touchmove', function (e) {
                e.preventDefault();
                draw(e);
            });
            canvas.addEventListener('touchend', endDraw);
        });

        $('.agp-pv-signature-clear').on('click', function () {
            var signature = $(this).data('signature-clear');
            var $wrapper = $('.agp-pv-signature[data-signature="' + signature + '"]');
            var canvas = $wrapper.find('canvas')[0];
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        });
    }

    function collectSignatureData() {
        var clienteCanvas = $('.agp-pv-signature[data-signature="cliente"] canvas')[0];
        var tecnicoCanvas = $('.agp-pv-signature[data-signature="tecnico"] canvas')[0];

        if (clienteCanvas) {
            $('#agp-pv-firma-cliente').val(clienteCanvas.toDataURL('image/png'));
        }
        if (tecnicoCanvas) {
            $('#agp-pv-firma-tecnico').val(tecnicoCanvas.toDataURL('image/png'));
        }
    }

    function initForm() {
        $('#agp-pv-form').on('submit', function (e) {
            e.preventDefault();

            $('.agp-pv-error').text('');
            collectSignatureData();

            var formData = new FormData(this);
            formData.append('action', 'agp_pv_submit');
            formData.append('nonce', agpPvData.nonce);

            $('.agp-pv-status').text('Enviando...');

            $.ajax({
                url: agpPvData.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            })
                .done(function (response) {
                    if (response.success) {
                        if (response.data.mail_sent === false) {
                            var mailMessage = response.data.message || 'Informe guardado, pero el correo falló.';
                            if (response.data.mail_error) {
                                mailMessage += ' ' + response.data.mail_error;
                            }
                            $('.agp-pv-status').text(mailMessage);
                            return;
                        }

                        $('.agp-pv-status').text(response.data.message);
                        if (response.data.autoclose) {
                            setTimeout(function () {
                                window.close();
                            }, response.data.autocloseDelay || 5000);
                        }
                        return;
                    }

                    if (response.data && response.data.errors) {
                        var firstMessage = '';
                        Object.keys(response.data.errors).forEach(function (key) {
                            $('.agp-pv-error[data-error-for="' + key + '"]').text(response.data.errors[key]);
                            if (!firstMessage) {
                                firstMessage = response.data.errors[key];
                            }
                        });
                        $('.agp-pv-status').text(firstMessage || agpPvData.messages.invalid);
                        return;
                    }

                    $('.agp-pv-status').text(response.data && response.data.message ? response.data.message : agpPvData.messages.invalid);
                })
                .fail(function () {
                    $('.agp-pv-status').text(agpPvData.messages.invalid);
                });
        });
    }

    $(function () {
        initConditionalFields();
        initSignatures();
        initForm();
    });
})(jQuery);
