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
        var MAX_FILES = 10;
        var MAX_FILE_BYTES = 5 * 1024 * 1024;
        var MAX_TOTAL_BYTES = 20 * 1024 * 1024;
        var MAX_DIMENSION = 2200;
        var QUALITY_START = 0.92;
        var QUALITY_MIN = 0.72;
        var QUALITY_STEP = 0.06;

        var $input = $('#agp-pv-fotos');
        var $preview = $('#agp-pv-fotos-preview');
        var $count = $('#agp-pv-fotos-count');
        var $size = $('#agp-pv-fotos-size');
        var $error = $('.agp-pv-error[data-error-for="fotos"]');

        if (!$input.length) {
            return;
        }

        var dt = new DataTransfer();
        var fileMetaByKey = {};

        function makeFileKey(file) {
            return [file.name, file.size, file.lastModified].join('::');
        }

        function formatBytes(bytes) {
            if (!bytes || bytes <= 0) {
                return '0 MB';
            }
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function getTotalBytes(files) {
            return Array.from(files || []).reduce(function (sum, file) {
                return sum + (file.size || 0);
            }, 0);
        }

        function getOriginalTotalBytes(files) {
            return Array.from(files || []).reduce(function (sum, file) {
                var meta = fileMetaByKey[makeFileKey(file)];
                return sum + (meta ? meta.originalBytes : (file.size || 0));
            }, 0);
        }

        function updateMeta() {
            if ($count.length) {
                $count.text(dt.files.length + '/' + MAX_FILES);
            }
            if ($size.length) {
                var finalBytes = getTotalBytes(dt.files);
                var originalBytes = getOriginalTotalBytes(dt.files);
                if (originalBytes > finalBytes) {
                    var saved = Math.max(0, Math.round((1 - (finalBytes / originalBytes)) * 100));
                    $size.text(formatBytes(finalBytes) + ' comprimido (antes ' + formatBytes(originalBytes) + ', -' + saved + '%)');
                } else {
                    $size.text(formatBytes(finalBytes));
                }
            }
        }

        function isSupportedImage(file) {
            return /^image\//.test(file.type || '');
        }

        function drawToCanvasFromFile(file) {
            return new Promise(function (resolve) {
                var reader = new FileReader();
                reader.onload = function (event) {
                    var img = new Image();
                    img.onload = function () {
                        var width = img.width;
                        var height = img.height;
                        var ratio = Math.min(1, MAX_DIMENSION / Math.max(width, height));
                        var targetW = Math.max(1, Math.round(width * ratio));
                        var targetH = Math.max(1, Math.round(height * ratio));

                        var canvas = document.createElement('canvas');
                        canvas.width = targetW;
                        canvas.height = targetH;

                        var ctx = canvas.getContext('2d');
                        if (!ctx) {
                            resolve(null);
                            return;
                        }

                        ctx.drawImage(img, 0, 0, targetW, targetH);
                        resolve(canvas);
                    };
                    img.onerror = function () {
                        resolve(null);
                    };
                    img.src = event.target.result;
                };
                reader.onerror = function () {
                    resolve(null);
                };
                reader.readAsDataURL(file);
            });
        }

        function canvasToBlob(canvas, mime, quality) {
            return new Promise(function (resolve) {
                canvas.toBlob(function (blob) {
                    resolve(blob || null);
                }, mime, quality);
            });
        }

        async function compressImageFile(file) {
            if (!isSupportedImage(file)) {
                return { file: file, originalBytes: file.size || 0, compressed: false };
            }

            var canvas = await drawToCanvasFromFile(file);
            if (!canvas) {
                return { file: file, originalBytes: file.size || 0, compressed: false };
            }

            var originalBytes = file.size || 0;
            var targetMime = /image\/(jpeg|jpg|webp)/.test(file.type || '') ? file.type : 'image/jpeg';
            var baseName = file.name.replace(/\.[^/.]+$/, '');
            var ext = targetMime === 'image/webp' ? 'webp' : 'jpg';

            var bestBlob = null;
            var bestBytes = originalBytes;

            for (var quality = QUALITY_START; quality >= QUALITY_MIN; quality -= QUALITY_STEP) {
                var blob = await canvasToBlob(canvas, targetMime, quality);
                if (!blob) {
                    continue;
                }

                if (blob.size < bestBytes) {
                    bestBlob = blob;
                    bestBytes = blob.size;
                }

                if (bestBytes <= MAX_FILE_BYTES * 0.75) {
                    break;
                }
            }

            if (!bestBlob) {
                var fallbackBlob = await canvasToBlob(canvas, targetMime, QUALITY_START);
                if (fallbackBlob && fallbackBlob.size < bestBytes) {
                    bestBlob = fallbackBlob;
                    bestBytes = fallbackBlob.size;
                }
            }

            if (!bestBlob || bestBytes >= originalBytes) {
                return { file: file, originalBytes: originalBytes, compressed: false };
            }

            var compressedFile = new File([bestBlob], baseName + '.' + ext, {
                type: targetMime,
                lastModified: Date.now()
            });

            return {
                file: compressedFile,
                originalBytes: originalBytes,
                compressed: true
            };
        }

        if (typeof DataTransfer === 'undefined') {
            function updateMetaFallback() {
                var files = $input.get(0).files || [];
                if ($count.length) {
                    $count.text(files.length + '/' + MAX_FILES);
                }
                if ($size.length) {
                    $size.text(formatBytes(getTotalBytes(files)));
                }
            }

            $input.on('change', function () {
                $error.text('');
                var files = Array.from($input.get(0).files || []);
                if (files.length > MAX_FILES) {
                    $error.text('Máximo 10 fotos.');
                    $input.val('');
                    updateMetaFallback();
                    return;
                }

                for (var i = 0; i < files.length; i++) {
                    if (!isSupportedImage(files[i])) {
                        $error.text('Solo se permiten archivos de imagen.');
                        $input.val('');
                        break;
                    }
                    if ((files[i].size || 0) > MAX_FILE_BYTES) {
                        $error.text('Cada foto debe pesar máximo 5 MB.');
                        $input.val('');
                        break;
                    }
                }

                if (getTotalBytes($input.get(0).files || []) > MAX_TOTAL_BYTES) {
                    $error.text('El total de fotos no puede superar 20 MB.');
                    $input.val('');
                }

                updateMetaFallback();
            });

            updateMetaFallback();
            return;
        }

        function renderPreview() {
            if (!$preview.length) {
                return;
            }
            $preview.empty();

            Array.from(dt.files).forEach(function (file, index) {
                var key = makeFileKey(file);
                var meta = fileMetaByKey[key] || { originalBytes: file.size || 0, compressed: false };
                var savedPercent = meta.originalBytes > 0
                    ? Math.max(0, Math.round((1 - ((file.size || 0) / meta.originalBytes)) * 100))
                    : 0;

                var $card = $('<div class="agp-pv-file"></div>');
                var $img = $('<img alt="">');
                var $footer = $('<div class="agp-pv-file-footer"></div>');
                var $name = $('<div class="agp-pv-file-name"></div>').text(file.name);
                var $meta = $('<div class="agp-pv-file-meta-size"></div>').text(
                    meta.originalBytes > (file.size || 0)
                        ? (formatBytes(file.size || 0) + ' (antes ' + formatBytes(meta.originalBytes) + ', -' + savedPercent + '%)')
                        : formatBytes(file.size || 0)
                );
                var $remove = $('<button type="button" class="agp-pv-file-remove">Quitar</button>');

                $remove.on('click', function () {
                    dt.items.remove(index);
                    delete fileMetaByKey[key];
                    $input.get(0).files = dt.files;
                    $error.text('');
                    renderPreview();
                    updateMeta();
                });

                var reader = new FileReader();
                reader.onload = function (e) {
                    $img.attr('src', e.target.result);
                };
                reader.readAsDataURL(file);

                var $textWrap = $('<div class="agp-pv-file-text"></div>').append($name).append($meta);
                $footer.append($textWrap).append($remove);
                $card.append($img).append($footer);
                $preview.append($card);
            });
        }

        $input.on('change', async function () {
            $error.text('');

            var incoming = Array.from($input.get(0).files || []);
            if (!incoming.length) {
                return;
            }

            var available = MAX_FILES - dt.files.length;
            if (available <= 0) {
                $error.text('Máximo 10 fotos.');
                $input.get(0).files = dt.files;
                return;
            }

            if (incoming.length > available) {
                $error.text('Máximo 10 fotos. El resto fue descartado.');
                incoming = incoming.slice(0, available);
            }

            var currentTotal = getTotalBytes(dt.files);

            for (var i = 0; i < incoming.length; i++) {
                var originalFile = incoming[i];

                if (!isSupportedImage(originalFile)) {
                    $error.text('Se omitió "' + originalFile.name + '": formato no permitido.');
                    continue;
                }

                var result = await compressImageFile(originalFile);
                var processedFile = result.file;

                if ((processedFile.size || 0) > MAX_FILE_BYTES) {
                    $error.text('Se omitió "' + originalFile.name + '": supera 5 MB.');
                    continue;
                }

                if (currentTotal + processedFile.size > MAX_TOTAL_BYTES) {
                    $error.text('Límite total alcanzado: 20 MB.');
                    break;
                }

                dt.items.add(processedFile);
                fileMetaByKey[makeFileKey(processedFile)] = {
                    originalBytes: result.originalBytes,
                    compressed: result.compressed
                };
                currentTotal += processedFile.size;
            }

            $input.get(0).files = dt.files;
            renderPreview();
            updateMeta();
        });

        $input.data('agpPvReset', function () {
            dt = new DataTransfer();
            fileMetaByKey = {};
            $input.get(0).files = dt.files;
            $preview.empty();
            $error.text('');
            updateMeta();
        });

        updateMeta();
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
