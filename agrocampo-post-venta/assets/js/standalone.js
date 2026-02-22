(function ($) {
    function clearFieldErrors() {
        $('.agp-pv-error').text('');
        $('#agp-pv-form')
            .find('input, select, textarea')
            .removeClass('agp-pv-invalid')
            .removeAttr('aria-invalid')
            .removeAttr('aria-describedby');
    }

    function ensureErrorIds() {
        $('.agp-pv-error').each(function () {
            var $el = $(this);
            var key = $el.data('error-for');
            if (!key) {
                return;
            }
            if (!$el.attr('id')) {
                $el.attr('id', 'agp-pv-error-' + key);
            }
        });
    }

    function showFieldErrors(errors) {
        if (!errors) {
            return;
        }

        var firstInvalid = null;

        Object.keys(errors).forEach(function (key) {
            var message = errors[key];

            var $error = $('.agp-pv-error[data-error-for="' + key + '"]');
            if ($error.length) {
                $error.text(message);
            }

            // find matching field by name (preferred), fallback by id convention
            var $field = $('[name="' + key + '"]');
            if (!$field.length) {
                $field = $('#agp-pv-' + key.replace(/_/g, '-'));
            }
            if ($field.length) {
                $field
                    .addClass('agp-pv-invalid')
                    .attr('aria-invalid', 'true');

                if ($error.length && $error.attr('id')) {
                    $field.attr('aria-describedby', $error.attr('id'));
                }

                if (!firstInvalid) {
                    firstInvalid = $field;
                }
            }
        });

        if (firstInvalid && firstInvalid.length) {
            firstInvalid.trigger('focus');
        }
    }

    function setVisibility($wrapper, isVisible) {
        if (!$wrapper || !$wrapper.length) {
            return;
        }

        var $controls = $wrapper.find('input, select, textarea, button');

        if (isVisible) {
            $wrapper.show();
            $controls.prop('disabled', false);
        } else {
            // Clear values and disable so they are not submitted and do not block validity
            $controls.each(function () {
                var $el = $(this);

                // keep submit enabled
                if ($el.is(':submit')) {
                    return;
                }

                if ($el.is('input[type="checkbox"], input[type="radio"]')) {
                    $el.prop('checked', false);
                } else if ($el.is('select')) {
                    $el.val('');
                } else if ($el.is('input, textarea')) {
                    $el.val('');
                }
            });

            $controls.prop('disabled', true);
            $wrapper.hide();
        }
    }

    function clearSignature($signatureWrapper) {
        if (!$signatureWrapper || !$signatureWrapper.length) {
            return;
        }
        var canvas = $signatureWrapper.find('canvas').get(0);
        if (canvas) {
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        $signatureWrapper.removeClass('has-signature');

        // Clear associated hidden input
        var type = $signatureWrapper.data('signature');
        if (type === 'cliente') {
            $('#agp-pv-firma-cliente').val('');
        } else if (type === 'tecnico') {
            $('#agp-pv-firma-tecnico').val('');
        }
    }

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

            // Fecha: required unless Garantia
            setVisibility($fechaField, tipoServicio !== 'two');

            // Garantia block
            setVisibility($garantiaFields, tipoServicio === 'two');
            if (tipoServicio !== 'two') {
                // clear tecnico signature when not in garantia mode
                clearSignature($('.agp-pv-signature[data-signature="tecnico"]'));
            }

            // Tipo mantencion only for Interno
            setVisibility($mantencionField, tipoServicio === 'Interno');
            if (tipoServicio !== 'Interno') {
                $tipoMantencion.val('');
            }

            // Cantidad horas only if OTRO
            setVisibility($cantidadHorasField, tipoMantencion === 'OTRO');
            if (tipoMantencion !== 'OTRO') {
                $('#agp-pv-cantidad-horas').val('');
            }
        }

        $tipoServicio.on('change', function () {
            clearFieldErrors();
            updateConditions();
        });
        $tipoMantencion.on('change', function () {
            clearFieldErrors();
            updateConditions();
        });

        updateConditions();
    }

    function initSignatures() {
        $('.agp-pv-signature').each(function () {
            var $wrapper = $(this);
            var canvas = $wrapper.find('canvas').get(0);
            if (!canvas) {
                return;
            }

            var ctx = canvas.getContext('2d');

            function setupCanvas() {
                // Use the rendered size for crisp signatures on high DPI
                var rect = canvas.getBoundingClientRect();
                var dpr = window.devicePixelRatio || 1;

                // Avoid zero size issues
                var cssW = Math.max(1, Math.round(rect.width));
                var cssH = Math.max(1, Math.round(rect.height));

                canvas.width = Math.round(cssW * dpr);
                canvas.height = Math.round(cssH * dpr);

                // Reset transform then scale so coordinates are in CSS pixels
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.strokeStyle = '#111';
            }

            setupCanvas();

            var drawing = false;

            function getPos(e) {
                var rect = canvas.getBoundingClientRect();
                var clientX = e.touches ? e.touches[0].clientX : e.clientX;
                var clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            }

            function startDraw(e) {
                e.preventDefault();
                drawing = true;
                var pos = getPos(e);
                ctx.beginPath();
                ctx.moveTo(pos.x, pos.y);
            }

            function draw(e) {
                if (!drawing) {
                    return;
                }
                e.preventDefault();
                var pos = getPos(e);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                $wrapper.addClass('has-signature');
            }

            function endDraw(e) {
                if (e) {
                    e.preventDefault();
                }
                drawing = false;
            }

            canvas.addEventListener('mousedown', startDraw);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', endDraw);
            canvas.addEventListener('mouseleave', endDraw);

            canvas.addEventListener('touchstart', startDraw, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', endDraw, { passive: false });

            $wrapper.find('.agp-pv-signature-clear').on('click', function () {
                clearSignature($wrapper);
            });
        });
    }

    function collectSignatureData() {
        var $clienteWrap = $('.agp-pv-signature[data-signature="cliente"]');
        var $tecnicoWrap = $('.agp-pv-signature[data-signature="tecnico"]');

        var clienteCanvas = $clienteWrap.find('canvas').get(0);
        var tecnicoCanvas = $tecnicoWrap.find('canvas').get(0);

        if (clienteCanvas && $clienteWrap.hasClass('has-signature')) {
            $('#agp-pv-firma-cliente').val(clienteCanvas.toDataURL('image/png'));
        } else {
            $('#agp-pv-firma-cliente').val('');
        }

        var tecnicoVisible = tecnicoCanvas && !$tecnicoWrap.closest('[data-condition="garantia"]').is(':hidden');
        if (tecnicoVisible && $tecnicoWrap.hasClass('has-signature')) {
            $('#agp-pv-firma-tecnico').val(tecnicoCanvas.toDataURL('image/png'));
        } else {
            $('#agp-pv-firma-tecnico').val('');
        }
    }

function initPhotos() {
        var $input = $('#agp-pv-fotos');
        var $preview = $('#agp-pv-fotos-preview');
        var $count = $('#agp-pv-fotos-count');
        var $error = $('.agp-pv-error[data-error-for="fotos"]');

        if (!$input.length) {
            return;
        }

        var dt = new DataTransfer();

        function updateCount() {
            if ($count.length) {
                $count.text(dt.files.length + '/10');
            }
        }

        if (typeof DataTransfer === 'undefined') {
            // Fallback: no preview/removal, keep native file input and enforce max 10
            function updateCountFallback() {
                if ($count.length) {
                    var n = ($input.get(0).files || []).length;
                    $count.text(n + '/10');
                }
            }

            $input.on('change', function () {
                $error.text('');
                var n = ($input.get(0).files || []).length;
                if (n > 10) {
                    $error.text('Máximo 10 fotos.');
                    $input.val('');
                }
                updateCountFallback();
            });

            updateCountFallback();
            return;
        }



        function renderPreview() {
            if (!$preview.length) {
                return;
            }
            $preview.empty();

            Array.from(dt.files).forEach(function (file, index) {
                var $card = $('<div class="agp-pv-file"></div>');
                var $img = $('<img alt="">');
                var $footer = $('<div class="agp-pv-file-footer"></div>');
                var $name = $('<div class="agp-pv-file-name"></div>').text(file.name);
                var $remove = $('<button type="button" class="agp-pv-file-remove">Quitar</button>');

                $remove.on('click', function () {
                    dt.items.remove(index);
                    $input.get(0).files = dt.files;
                    $error.text('');
                    renderPreview();
                    updateCount();
                });

                // preview image
                var reader = new FileReader();
                reader.onload = function (e) {
                    $img.attr('src', e.target.result);
                };
                reader.readAsDataURL(file);

                $footer.append($name).append($remove);
                $card.append($img).append($footer);
                $preview.append($card);
            });
        }

        $input.on('change', function () {
            $error.text('');

            var newFiles = Array.from($input.get(0).files || []);
            if (!newFiles.length) {
                return;
            }

            var available = 10 - dt.files.length;
            if (available <= 0) {
                $error.text('Máximo 10 fotos.');
                // keep existing selection
                $input.get(0).files = dt.files;
                return;
            }

            if (newFiles.length > available) {
                $error.text('Máximo 10 fotos. El resto fue descartado.');
            }

            newFiles.slice(0, available).forEach(function (file) {
                dt.items.add(file);
            });

            $input.get(0).files = dt.files;
            renderPreview();
            updateCount();
        });

        // expose reset for form success
        $input.data('agpPvReset', function () {
            dt = new DataTransfer();
            $input.get(0).files = dt.files;
            $preview.empty();
            $error.text('');
            updateCount();
        });

        updateCount();
    }

    function highlightInvalidFromNative(formEl) {
        var first = null;
        $(formEl)
            .find('input, select, textarea')
            .each(function () {
                var el = this;
                var $el = $(el);

                // ignore disabled controls
                if (el.disabled) {
                    return;
                }

                if (typeof el.checkValidity === 'function' && !el.checkValidity()) {
                    $el.addClass('agp-pv-invalid').attr('aria-invalid', 'true');
                    if (!first) {
                        first = $el;
                    }
                } else {
                    $el.removeClass('agp-pv-invalid').removeAttr('aria-invalid');
                }
            });

        if (first) {
            first.trigger('focus');
        }
    }


    function initStepper() {
        var $steps = $('.agp-pv-step');
        var $indicators = $('[data-step-indicator]');
        var currentStep = 1;

        function getStep(step) {
            return $steps.filter('[data-step="' + step + '"]');
        }

        function updateIndicators(step) {
            $indicators.each(function () {
                var $i = $(this);
                var n = parseInt($i.attr('data-step-indicator'), 10);
                $i.removeClass('is-active is-completed');
                if (n < step) {
                    $i.addClass('is-completed');
                } else if (n === step) {
                    $i.addClass('is-active');
                }
            });
        }

        function goTo(step) {
            currentStep = step;
            $steps.removeClass('is-active').attr('hidden', true);
            var $target = getStep(step);
            $target.addClass('is-active').removeAttr('hidden');
            updateIndicators(step);

            var $focus = $target.find('input, select, textarea, button').filter(':visible:not([disabled])').first();
            if ($focus.length) {
                $focus.trigger('focus');
            }

            $('html, body').animate({ scrollTop: $('#agp-pv-form').offset().top - 10 }, 120);
        }

        function validateCurrentStep() {
            var valid = true;
            var firstInvalid = null;
            var $step = getStep(currentStep);

            $step.find('input, select, textarea').each(function () {
                var el = this;
                if (el.disabled || $(el).is(':hidden')) {
                    return;
                }

                if (typeof el.checkValidity === 'function' && !el.checkValidity()) {
                    $(el).addClass('agp-pv-invalid').attr('aria-invalid', 'true');
                    if (!firstInvalid) {
                        firstInvalid = $(el);
                    }
                    valid = false;
                }
            });

            if (!valid && firstInvalid) {
                if (typeof firstInvalid.get(0).reportValidity === 'function') {
                    firstInvalid.get(0).reportValidity();
                }
                firstInvalid.trigger('focus');
                $('.agp-pv-status').text('Revisa los campos marcados antes de continuar.');
            }

            return valid;
        }

        $('#agp-pv-form').on('click', '.agp-pv-next', function () {
            clearFieldErrors();
            if (!validateCurrentStep()) {
                return;
            }
            var step = parseInt($(this).attr('data-next-step'), 10);
            if (step) {
                goTo(step);
            }
        });

        $('#agp-pv-form').on('click', '.agp-pv-prev', function () {
            clearFieldErrors();
            var step = parseInt($(this).attr('data-prev-step'), 10);
            if (step) {
                goTo(step);
            }
        });

        $('#agp-pv-form').data('agpPvGoToStep', goTo);
        goTo(1);
    }

    function initForm() {
        ensureErrorIds();

        // clear invalid state on user input
        $('#agp-pv-form').on('input change', 'input, select, textarea', function () {
            $(this).removeClass('agp-pv-invalid').removeAttr('aria-invalid');
            var name = $(this).attr('name');
            if (name) {
                $('.agp-pv-error[data-error-for="' + name + '"]').text('');
            }
        });

        $('#agp-pv-form').on('submit', function (e) {
            e.preventDefault();

            clearFieldErrors();

            var goToStep = $('#agp-pv-form').data('agpPvGoToStep');
            if (typeof goToStep === 'function') {
                goToStep(4);
            }

            // Native validation first (works with conditional disable)
            var formEl = this;
            if (typeof formEl.checkValidity === 'function' && !formEl.checkValidity()) {
                if (typeof formEl.reportValidity === 'function') {
                    formEl.reportValidity();
                }
                highlightInvalidFromNative(formEl);
                $('.agp-pv-status').text('Revisa los campos marcados.');
                return;
            }

            collectSignatureData();

            var $submit = $('.agp-pv-submit');
            var originalText = $submit.data('original-text') || $submit.text();
            $submit.data('original-text', originalText);
            $submit.prop('disabled', true).text('Enviando...');

            var formData = new FormData(formEl);
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
                    if (response && response.success) {
                        if (response.data && response.data.mail_sent === false) {
                            var mailMessage = response.data.message || 'Informe guardado, pero el correo falló.';
                            if (response.data.mail_error) {
                                mailMessage += ' ' + response.data.mail_error;
                            }
                            $('.agp-pv-status').text(mailMessage);
                        } else {
                            $('.agp-pv-status').text(agpPvData.messages.success);
                        }

                        // Reset UI (keep status)
                        formEl.reset();

                        // Clear signatures and previews
                        clearSignature($('.agp-pv-signature[data-signature="cliente"]'));
                        clearSignature($('.agp-pv-signature[data-signature="tecnico"]'));

                        var resetPhotos = $('#agp-pv-fotos').data('agpPvReset');
                        if (typeof resetPhotos === 'function') {
                            resetPhotos();
                        }

                        // Re-apply conditional logic after reset
                        $('#agp-pv-tipo-servicio').trigger('change');

                        var goToStep = $('#agp-pv-form').data('agpPvGoToStep');
                        if (typeof goToStep === 'function') {
                            goToStep(1);
                        }
                    } else {
                        // Field errors
                        if (response && response.data && response.data.errors) {
                            showFieldErrors(response.data.errors);
                            $('.agp-pv-status').text('Revisa los campos marcados.');
                        } else if (response && response.data && response.data.message) {
                            $('.agp-pv-status').text(response.data.message);
                        } else {
                            $('.agp-pv-status').text(agpPvData.messages.invalid);
                        }
                    }
                })
                .fail(function () {
                    $('.agp-pv-status').text(agpPvData.messages.invalid);
                })
                .always(function () {
                    $submit.prop('disabled', false).text(originalText);
                });
        });
    }

    $(function () {
        initConditionalFields();
        initSignatures();
        initPhotos();
        initStepper();
        initForm();
    });
})(jQuery);
