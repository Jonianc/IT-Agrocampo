(function ($) {
    function clearFieldErrors() {
        $('.agp-pv-error').text('');
        clearErrorSummary();
        $('#agp-pv-form')
            .find('input, select, textarea')
            .removeClass('agp-pv-invalid')
            .removeAttr('aria-invalid')
            .removeAttr('aria-describedby');
    }

    function findFieldForErrorKey(key) {
        var $field = $('[name="' + key + '"]');
        if (!$field.length) {
            $field = $('#agp-pv-' + key.replace(/_/g, '-'));
        }

        if (!$field.length) {
            return $();
        }

        return $field.first();
    }

    function getFieldErrorMessage($field) {
        if (!$field || !$field.length) {
            return '';
        }

        var el = $field.get(0);
        if (el && typeof el.validationMessage === 'string' && el.validationMessage) {
            return el.validationMessage;
        }

        return '';
    }

    function clearErrorSummary() {
        var $summary = $('#agp-pv-error-summary');
        if (!$summary.length) {
            return;
        }

        $summary.attr('hidden', true);
        $summary.find('.agp-pv-error-summary__title').text('');
        $summary.find('.agp-pv-error-summary__list').empty();
    }

    function renderErrorSummary(items, title, shouldFocus) {
        var $summary = $('#agp-pv-error-summary');
        if (!$summary.length) {
            return;
        }

        var $title = $summary.find('.agp-pv-error-summary__title');
        var $list = $summary.find('.agp-pv-error-summary__list');

        $list.empty();

        if (!items || !items.length) {
            clearErrorSummary();
            return;
        }

        $title.text(title || getMessage('errorSummaryTitle', 'Revisa los siguientes campos antes de continuar:'));

        items.forEach(function (item) {
            var $li = $('<li />');
            var $link = $('<a />', { href: '#' + item.id, text: item.message });

            $link.on('click', function (event) {
                event.preventDefault();
                var $target = $('#' + item.id);
                if ($target.length) {
                    $target.trigger('focus');
                }
            });

            $li.append($link);
            $list.append($li);
        });

        $summary.removeAttr('hidden');

        if (shouldFocus) {
            $summary.attr('tabindex', '-1').trigger('focus');
        }
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

        var summaryItems = [];

        Object.keys(errors).forEach(function (key) {
            var $field = findFieldForErrorKey(key);
            if (!$field.length) {
                return;
            }

            var fieldId = $field.attr('id');
            if (!fieldId) {
                return;
            }

            summaryItems.push({
                id: fieldId,
                message: errors[key]
            });
        });

        renderErrorSummary(summaryItems, getMessage('errorSummaryTitle', 'Revisa los siguientes campos antes de continuar:'), true);

        if (firstInvalid && firstInvalid.length) {
            var $firstStep = firstInvalid.closest('.agp-pv-step');
            if ($firstStep.length) {
                var stepNumber = parseInt($firstStep.attr('data-step'), 10);
                var goToStep = $('#agp-pv-form').data('agpPvGoToStep');
                if (stepNumber && typeof goToStep === 'function') {
                    goToStep(stepNumber);
                }
            }

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

            $('#agp-pv-form').trigger('agpPvConditionsChanged');
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
        var signatureSetups = [];

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
                var cssW = Math.round(rect.width);
                var cssH = Math.round(rect.height);

                // Skip while hidden (step not visible) to avoid collapsing to 1x1.
                if (cssW < 10 || cssH < 10) {
                    return;
                }

                var dpr = window.devicePixelRatio || 1;
                var targetW = Math.round(cssW * dpr);
                var targetH = Math.round(cssH * dpr);

                if (canvas.width === targetW && canvas.height === targetH) {
                    return;
                }

                var hadSignature = $wrapper.hasClass('has-signature');
                var previousCanvas = document.createElement('canvas');
                previousCanvas.width = canvas.width;
                previousCanvas.height = canvas.height;
                var previousCtx = previousCanvas.getContext('2d');
                previousCtx.drawImage(canvas, 0, 0);

                canvas.width = targetW;
                canvas.height = targetH;

                // Reset transform then scale so coordinates are in CSS pixels
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.strokeStyle = '#111';

                if (hadSignature && previousCanvas.width > 0 && previousCanvas.height > 0) {
                    ctx.drawImage(previousCanvas, 0, 0, targetW, targetH);
                }
            }

            setupCanvas();
            signatureSetups.push(setupCanvas);

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

        $(window).on('resize', function () {
            signatureSetups.forEach(function (setup) {
                setup();
            });
        });

        $('#agp-pv-form').on('agpPvStepChanged', function (e, step) {
            if (step !== 3) {
                return;
            }

            signatureSetups.forEach(function (setup) {
                setup();
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
        var MIN_SAVING_RATIO = 0.08;

        var COMPRESSION_PROFILES = [
            { maxDimension: 1800, qualityStart: 0.88, qualityMin: 0.62, qualityStep: 0.05 },
            { maxDimension: 1400, qualityStart: 0.78, qualityMin: 0.50, qualityStep: 0.04 }
        ];

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

        function drawToCanvasFromFile(file, maxDimension) {
            return new Promise(function (resolve) {
                var reader = new FileReader();
                reader.onload = function (event) {
                    var img = new Image();
                    img.onload = function () {
                        var width = img.width;
                        var height = img.height;
                        var ratio = Math.min(1, maxDimension / Math.max(width, height));
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

        async function runProfile(canvas, mime, profile, originalBytes) {
            var bestBlob = null;
            var bestBytes = originalBytes;

            for (var quality = profile.qualityStart; quality >= profile.qualityMin; quality -= profile.qualityStep) {
                var blob = await canvasToBlob(canvas, mime, quality);
                if (!blob) {
                    continue;
                }
                if (blob.size < bestBytes) {
                    bestBlob = blob;
                    bestBytes = blob.size;
                }
                if (bestBytes <= (MAX_FILE_BYTES * 0.60)) {
                    break;
                }
            }

            return bestBlob;
        }

        async function compressImageFile(file) {
            if (!isSupportedImage(file)) {
                return { file: file, originalBytes: file.size || 0, compressed: false };
            }

            var originalBytes = file.size || 0;
            var baseName = file.name.replace(/\.[^/.]+$/, '');
            var mime = 'image/jpeg';
            var ext = 'jpg';
            var bestBlob = null;
            var bestBytes = originalBytes;

            for (var p = 0; p < COMPRESSION_PROFILES.length; p++) {
                var profile = COMPRESSION_PROFILES[p];
                var canvas = await drawToCanvasFromFile(file, profile.maxDimension);
                if (!canvas) {
                    continue;
                }
                var blob = await runProfile(canvas, mime, profile, originalBytes);
                if (blob && blob.size < bestBytes) {
                    bestBlob = blob;
                    bestBytes = blob.size;
                }
            }

            if (!bestBlob) {
                return { file: file, originalBytes: originalBytes, compressed: false };
            }

            var savedRatio = originalBytes > 0 ? (1 - (bestBytes / originalBytes)) : 0;
            if (savedRatio < MIN_SAVING_RATIO && bestBytes > (MAX_FILE_BYTES * 0.85)) {
                return { file: file, originalBytes: originalBytes, compressed: false };
            }

            var compressedFile = new File([bestBlob], baseName + '.' + ext, {
                type: mime,
                lastModified: Date.now()
            });

            if ((compressedFile.size || 0) >= originalBytes) {
                return { file: file, originalBytes: originalBytes, compressed: false };
            }

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
                    $error.text(getMessage('photosMaxCount', 'Máximo 10 fotos.'));
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
                        $error.text(getMessage('photoMaxSize', 'Cada foto debe pesar máximo 5 MB.'));
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
                $error.text(getMessage('photosMaxCount', 'Máximo 10 fotos.'));
                $input.get(0).files = dt.files;
                return;
            }

            if (incoming.length > available) {
                $error.text(getMessage('photosMaxCountTrimmed', 'Máximo 10 fotos. El resto fue descartado.'));
                incoming = incoming.slice(0, available);
            }

            var currentTotal = getTotalBytes(dt.files);

            for (var i = 0; i < incoming.length; i++) {
                var originalFile = incoming[i];

                if (!isSupportedImage(originalFile)) {
                    $error.text(formatMessage(getMessage('photoOmittedInvalidFormat', 'Se omitió "%s": formato no permitido.'), [originalFile.name]));
                    continue;
                }

                var result = await compressImageFile(originalFile);
                var processedFile = result.file;

                if ((processedFile.size || 0) > MAX_FILE_BYTES) {
                    $error.text(formatMessage(getMessage('photoOmittedTooLarge', 'Se omitió "%s": supera 5 MB.'), [originalFile.name]));
                    continue;
                }

                if (currentTotal + processedFile.size > MAX_TOTAL_BYTES) {
                    $error.text(getMessage('photosTotalLimit', 'Límite total alcanzado: 20 MB.'));
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

    function getDetalleMinimumConfig() {
        var minChars = 10;
        if (typeof window.agpPvData !== 'undefined' && agpPvData && typeof agpPvData.detalleMinimumChars !== 'undefined') {
            var parsed = parseInt(agpPvData.detalleMinimumChars, 10);
            if (!isNaN(parsed) && parsed > 0) {
                minChars = parsed;
            }
        }

        return {
            minChars: minChars,
            message: formatMessage(getMessage('detalleAtLeastOneMinChars', 'Completa “Trabajos realizados” u “Observaciones” con al menos %d caracteres.'), [minChars])
        };
    }

    function syncDetalleMinimumConstraint(showInlineError) {
        var config = getDetalleMinimumConfig();
        var $trabajos = $('#agp-pv-trabajos');
        var $observaciones = $('#agp-pv-observaciones');

        if (!$trabajos.length || !$observaciones.length) {
            return true;
        }

        var trabajosLen = $.trim(String($trabajos.val() || '')).length;
        var observacionesLen = $.trim(String($observaciones.val() || '')).length;
        var isValid = (trabajosLen >= config.minChars) || (observacionesLen >= config.minChars);

        $trabajos.get(0).setCustomValidity(isValid ? '' : config.message);
        $observaciones.get(0).setCustomValidity(isValid ? '' : config.message);

        var $errorTrabajos = $('.agp-pv-error[data-error-for="trabajos_realizados"]');
        var $errorObservaciones = $('.agp-pv-error[data-error-for="observaciones"]');

        if (isValid) {
            $errorTrabajos.text('');
            $errorObservaciones.text('');
            $trabajos.removeClass('agp-pv-invalid').removeAttr('aria-invalid');
            $observaciones.removeClass('agp-pv-invalid').removeAttr('aria-invalid');
            return true;
        }

        if (showInlineError) {
            $errorTrabajos.text(config.message);
            $errorObservaciones.text(config.message);
            $trabajos.addClass('agp-pv-invalid').attr('aria-invalid', 'true');
            $observaciones.addClass('agp-pv-invalid').attr('aria-invalid', 'true');

            if ($errorTrabajos.length && $errorTrabajos.attr('id')) {
                $trabajos.attr('aria-describedby', $errorTrabajos.attr('id'));
            }
            if ($errorObservaciones.length && $errorObservaciones.attr('id')) {
                $observaciones.attr('aria-describedby', $errorObservaciones.attr('id'));
            }
        }

        return false;
    }


    function initStepper() {
        var $steps = $('.agp-pv-step');
        var $indicators = $('[data-step-indicator]');
        var submittedState = null;
        var currentStep = 1;

        function getStep(step) {
            return $steps.filter('[data-step="' + step + '"]');
        }

        function isStepperDraftField(el) {
            var name = (el && el.name) ? String(el.name) : '';
            if (!name) {
                return false;
            }

            if (
                name === 'nonce'
                || name === 'agp_pv_hp'
                || name === 'firma_cliente'
                || name === 'firma_tecnico'
                || name === 'fotos[]'
            ) {
                return false;
            }

            return true;
        }

        function getApplicableStepFields($scope) {
            return $scope.find('input, select, textarea').filter(function () {
                var el = this;
                var $el = $(el);

                if (!isStepperDraftField(el) || el.disabled) {
                    return false;
                }

                if (el.type === 'hidden' || el.hidden || $el.is('[hidden]')) {
                    return false;
                }

                var $conditionalWrap = $el.closest('[data-condition]');
                if ($conditionalWrap.length && $conditionalWrap.is(':hidden')) {
                    return false;
                }

                return true;
            });
        }

        function getStepDraftState($step) {
            var $fields = getApplicableStepFields($step);
            if (!$fields.length) {
                return 'empty';
            }

            var filledCount = 0;
            $fields.each(function () {
                var el = this;
                var $el = $(el);

                if (el.type === 'checkbox' || el.type === 'radio') {
                    if ($el.is(':checked')) {
                        filledCount += 1;
                    }
                    return;
                }

                if ($.trim(String($el.val() || '')).length > 0) {
                    filledCount += 1;
                }
            });

            if (filledCount <= 0) {
                return 'empty';
            }

            return getRequiredInvalidFields($step).length > 0 ? 'in_progress' : 'complete';
        }

        function updateStepDraftStates() {
            $steps.each(function () {
                var $step = $(this);
                var stepNumber = parseInt($step.attr('data-step'), 10);
                if (!stepNumber) {
                    return;
                }

                var $indicator = $indicators.filter('[data-step-indicator="' + stepNumber + '"]');
                var state = getStepDraftState($step);
                var stateLabel = '';
                var baseLabel = $.trim(String($indicator.text() || ''));

                $indicator.removeClass('is-draft-empty is-draft-in-progress is-draft-complete')
                    .removeAttr('data-step-state');

                if (state === 'complete') {
                    $indicator.addClass('is-draft-complete').attr('data-step-state', 'complete');
                    stateLabel = getMessage('stepStateComplete', 'completo');
                } else if (state === 'in_progress') {
                    $indicator.addClass('is-draft-in-progress').attr('data-step-state', 'in_progress');
                    stateLabel = getMessage('stepStateInProgress', 'en progreso');
                } else {
                    $indicator.addClass('is-draft-empty').attr('data-step-state', 'empty');
                    stateLabel = getMessage('stepStateEmpty', 'vacío');
                }

                $indicator.attr('aria-label', stateLabel ? (baseLabel + ' (' + stateLabel + ')') : baseLabel);
            });
        }

        function getRequiredInvalidFields($scope) {
            return $scope.find('input, select, textarea').filter(function () {
                var el = this;
                var $el = $(el);

                if (!el.required) {
                    return false;
                }

                if (el.disabled) {
                    return false;
                }

                // Keep required fields from inactive steps in the calculation.
                // Exclude only truly hidden controls (type=hidden or hidden attributes)
                // and fields inside conditional wrappers that are currently hidden.
                if (el.type === 'hidden' || el.hidden || $el.is('[hidden]')) {
                    return false;
                }

                var $conditionalWrap = $el.closest('[data-condition]');
                if ($conditionalWrap.length && $conditionalWrap.is(':hidden')) {
                    return false;
                }

                if (typeof el.checkValidity === 'function') {
                    return !el.checkValidity();
                }

                return false;
            });
        }

        function updateStepErrorStates() {
            $steps.each(function () {
                var $step = $(this);
                var stepNumber = parseInt($step.attr('data-step'), 10);
                if (!stepNumber) {
                    return;
                }

                var hasErrors = getRequiredInvalidFields($step).length > 0;
                var $indicator = $indicators.filter('[data-step-indicator="' + stepNumber + '"]');

                $indicator.toggleClass('is-error', hasErrors);

                if (hasErrors) {
                    $indicator.attr('data-has-errors', '1').attr('aria-invalid', 'true');
                } else {
                    $indicator.removeAttr('data-has-errors').removeAttr('aria-invalid');
                }
            });
        }

        function updateIndicators(step) {
            var hasSubmittedState = !!submittedState;

            $indicators.each(function () {
                var $i = $(this);
                var n = parseInt($i.attr('data-step-indicator'), 10);
                $i.removeClass('is-active is-completed is-submitted is-warning');
                $i.removeAttr('aria-current');

                if (n === 5) {
                    $i.attr('aria-label', $.trim(String($i.text() || '')));
                }

                if (n <= 4) {
                    if (hasSubmittedState) {
                        $i.addClass('is-completed');
                    } else if (n < step) {
                        $i.addClass('is-completed');
                    } else if (n === step) {
                        $i.addClass('is-active');
                        $i.attr('aria-current', 'step');
                    }
                }
            });

            if (hasSubmittedState) {
                var $submittedIndicator = $indicators.filter('[data-step-indicator="5"]');
                if ($submittedIndicator.length) {
                    var submittedBaseLabel = $.trim(String($submittedIndicator.text() || ''));
                    var submittedStateLabel = submittedState.type === 'warning'
                        ? getMessage('stepStateSubmittedWarning', 'enviado con advertencia')
                        : getMessage('stepStateSubmitted', 'enviado');

                    $submittedIndicator.addClass('is-submitted');
                    if (submittedState.type === 'warning') {
                        $submittedIndicator.addClass('is-warning');
                    }
                    $submittedIndicator
                        .attr('aria-current', 'step')
                        .attr('aria-label', submittedBaseLabel + ' (' + submittedStateLabel + ')');
                }
            }

            updateStepErrorStates();
            updateStepDraftStates();
        }

        function goTo(step) {
            currentStep = step;
            $steps.removeClass('is-active').attr('hidden', true);
            var $target = getStep(step);
            $target.addClass('is-active').removeAttr('hidden');
            updateIndicators(step);
            $('#agp-pv-form').data('agpPvCurrentStep', step).trigger('agpPvStepChanged', [step]);

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

            if (currentStep === 2 && !syncDetalleMinimumConstraint(true)) {
                valid = false;
                if (!firstInvalid) {
                    firstInvalid = $('#agp-pv-trabajos');
                }
            }

            if (!valid && firstInvalid) {
                var summaryItems = [];

                $step.find('input, select, textarea').each(function () {
                    var el = this;
                    var $el = $(el);

                    if (el.disabled || $el.is(':hidden') || !$el.hasClass('agp-pv-invalid')) {
                        return;
                    }

                    var fieldId = $el.attr('id');
                    if (!fieldId) {
                        return;
                    }

                    summaryItems.push({
                        id: fieldId,
                        message: getFieldErrorMessage($el) || getMessage('statusReviewFieldsBeforeContinue', 'Revisa los campos marcados antes de continuar.')
                    });
                });

                if (currentStep === 2 && !syncDetalleMinimumConstraint(false)) {
                    summaryItems.push({
                        id: 'agp-pv-trabajos',
                        message: getDetalleMinimumConfig().message
                    });
                }

                renderErrorSummary(summaryItems, getMessage('errorSummaryTitle', 'Revisa los siguientes campos antes de continuar:'), true);

                if (typeof firstInvalid.get(0).reportValidity === 'function') {
                    firstInvalid.get(0).reportValidity();
                }
                firstInvalid.trigger('focus');
                $('.agp-pv-status').text(getMessage('statusReviewFieldsBeforeContinue', 'Revisa los campos marcados antes de continuar.'));
            }

            updateStepErrorStates();

            return valid;
        }

        $('#agp-pv-form').on('click', '.agp-pv-next', function () {
            submittedState = null;
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
            submittedState = null;
            clearFieldErrors();
            var step = parseInt($(this).attr('data-prev-step'), 10);
            if (step) {
                goTo(step);
            }
        });

        $('#agp-pv-form').on('input change blur', 'input, select, textarea', function () {
            updateStepErrorStates();
            updateStepDraftStates();
        });

        $('#agp-pv-form').on('agpPvStepChanged agpPvConditionsChanged agpPvDraftStateChanged', function () {
            updateStepErrorStates();
            updateStepDraftStates();
        });

        function setSubmittedState(type) {
            if (type !== 'success' && type !== 'warning') {
                submittedState = null;
            } else {
                submittedState = { type: type };
            }

            updateIndicators(currentStep);
        }

        $('#agp-pv-form')
            .data('agpPvGoToStep', goTo)
            .data('agpPvSetSubmittedState', setSubmittedState);

        goTo(1);
    }


    function initDraftPersistence() {
        var STORAGE_KEY = 'agp_pv_form_draft_v1';
        var DRAFT_TTL_MS = 48 * 60 * 60 * 1000;
        var $form = $('#agp-pv-form');

        if (!$form.length || typeof window.localStorage === 'undefined') {
            return;
        }

        function isDraftField(el) {
            var name = (el && el.name) ? String(el.name) : '';
            if (!name) {
                return false;
            }

            if (
                name === 'nonce'
                || name === 'agp_pv_hp'
                || name === 'firma_cliente'
                || name === 'firma_tecnico'
                || name === 'fotos[]'
            ) {
                return false;
            }

            return true;
        }

        function collectData() {
            var payload = {
                savedAt: Date.now(),
                step: parseInt($form.data('agpPvCurrentStep'), 10) || 1,
                fields: {}
            };

            $form.find('input, select, textarea').each(function () {
                var el = this;
                if (!isDraftField(el) || el.disabled) {
                    return;
                }

                var $el = $(el);
                var name = String(el.name);

                if (el.type === 'checkbox' || el.type === 'radio') {
                    payload.fields[name] = payload.fields[name] || [];
                    if ($el.is(':checked')) {
                        payload.fields[name].push($el.val());
                    }
                    return;
                }

                payload.fields[name] = $el.val();
            });

            return payload;
        }

        function saveDraft() {
            try {
                var payload = collectData();
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
            } catch (e) {
                // ignore localStorage quota/access issues
            }

            $form.trigger('agpPvDraftStateChanged');
        }

        function clearDraft() {
            try {
                window.localStorage.removeItem(STORAGE_KEY);
            } catch (e) {
                // ignore
            }

            $form.trigger('agpPvDraftStateChanged');
        }

        function restoreDraft() {
            var raw = null;
            try {
                raw = window.localStorage.getItem(STORAGE_KEY);
            } catch (e) {
                return;
            }

            if (!raw) {
                return;
            }

            var parsed = null;
            try {
                parsed = JSON.parse(raw);
            } catch (e) {
                clearDraft();
                return;
            }

            if (!parsed || !parsed.savedAt || (Date.now() - parsed.savedAt) > DRAFT_TTL_MS) {
                clearDraft();
                return;
            }

            var fields = parsed.fields || {};
            Object.keys(fields).forEach(function (name) {
                var value = fields[name];
                var $targets = $form.find('[name="' + name + '"]');
                if (!$targets.length) {
                    return;
                }

                $targets.each(function () {
                    var el = this;
                    var $el = $(el);
                    if (el.disabled || !isDraftField(el)) {
                        return;
                    }

                    if (el.type === 'checkbox' || el.type === 'radio') {
                        var list = Array.isArray(value) ? value : [];
                        $el.prop('checked', list.indexOf($el.val()) !== -1);
                        return;
                    }

                    $el.val(value);
                });
            });

            $('#agp-pv-tipo-servicio').trigger('change');
            $('#agp-pv-tipo-mantencion').trigger('change');

            var goToStep = $form.data('agpPvGoToStep');
            var restoredStep = parseInt(parsed.step, 10);
            if (typeof goToStep === 'function' && restoredStep >= 1 && restoredStep <= 4) {
                goToStep(restoredStep);
            }

            $('.agp-pv-status').text(getMessage('draftRecovered', 'Se recuperó un borrador local.'));
            $form.trigger('agpPvDraftStateChanged');
        }

        $form.on('input change', 'input, select, textarea', function (e) {
            if ($form.data('agpPvInternalReset')) {
                return;
            }

            var setSubmittedState = $form.data('agpPvSetSubmittedState');
            var isUserInitiated = e && e.originalEvent;
            if (typeof setSubmittedState === 'function' && isUserInitiated) {
                setSubmittedState(null);
            }

            if (!isDraftField(this)) {
                return;
            }
            saveDraft();
        });

        $form.on('agpPvStepChanged', function (e, step) {
            $form.data('agpPvCurrentStep', step || 1);
            saveDraft();
        });

        $form.data('agpPvClearDraft', clearDraft);

        $form.on('click', '.agp-pv-clear-draft', function () {
            clearDraft();
            $('.agp-pv-status').text(getMessage('draftCleared', 'Borrador local eliminado.'));
        });

        restoreDraft();
    }




    function isFrontendDebugEnabled() {
        return !!(window.agpPvData && agpPvData.debugFrontend);
    }

    function emitFrontendEvent(name, detail) {
        try {
            var payload = detail && typeof detail === 'object' ? detail : {};
            payload.eventVersion = 1;

            var eventName = 'agpPv:' + name;
            if (typeof window.CustomEvent === 'function') {
                window.dispatchEvent(new CustomEvent(eventName, { detail: payload }));
            } else if (document && typeof document.createEvent === 'function') {
                var fallbackEvent = document.createEvent('CustomEvent');
                fallbackEvent.initCustomEvent(eventName, false, false, payload);
                window.dispatchEvent(fallbackEvent);
            }

            if (isFrontendDebugEnabled() && window.console && typeof window.console.debug === 'function') {
                window.console.debug('[agp-pv]', name, payload);
            }
        } catch (e) {
            // ignore observability errors
        }
    }

    function getMessage(key, fallback) {
        if (typeof window.agpPvData !== 'undefined' && agpPvData && agpPvData.messages && Object.prototype.hasOwnProperty.call(agpPvData.messages, key)) {
            return agpPvData.messages[key];
        }

        return fallback;
    }

    function formatMessage(template, values) {
        var text = String(template || '');
        (values || []).forEach(function (val, idx) {
            var token = '%' + (idx + 1) + '$d';
            text = text.replace(token, val);
            if (idx === 0) {
                text = text.replace('%d', val);
            }
            text = text.replace('%s', val);
        });
        return text;
    }

    function setStatusMessage(text, type) {
        var $status = $('.agp-pv-status');
        $status.removeClass('is-success is-warning is-error');

        if (type === 'success') {
            $status.addClass('is-success');
        } else if (type === 'warning') {
            $status.addClass('is-warning');
        } else if (type === 'error') {
            $status.addClass('is-error');
        }

        $status.text(text || '');
    }

    function buildSuccessMessage(data) {
        var statusType = data && data.status_type ? data.status_type : 'success';
        var baseMessage = (data && data.message) || '';

        if (!baseMessage) {
            if (statusType === 'partial_mail') {
                baseMessage = (agpPvData && agpPvData.messages && agpPvData.messages.successMailWarning) || 'Informe guardado correctamente, pero NO se pudo enviar el correo.';
            } else if (statusType === 'partial_pdf') {
                baseMessage = (agpPvData && agpPvData.messages && agpPvData.messages.successPdfWarning) || 'Informe enviado, pero el PDF no pudo adjuntarse.';
            } else {
                baseMessage = (agpPvData && agpPvData.messages && agpPvData.messages.success) || 'Informe enviado.';
            }
        }

        var reportId = data && data.report_id ? data.report_id : (data && data.submission_id ? data.submission_id : null);

        if (reportId) {
            var idPrefix = (agpPvData && agpPvData.messages && agpPvData.messages.reportIdPrefix) || 'ID informe';
            baseMessage += ' (' + idPrefix + ': ' + reportId + ')';
        }

        return {
            text: baseMessage,
            type: statusType === 'success' ? 'success' : 'warning',
            reportId: reportId,
            statusType: statusType
        };
    }


    function initTextLengthGuides() {
        var $form = $('#agp-pv-form');
        if (!$form.length) {
            return;
        }

        function updateCounter($field) {
            var key = $field.data('maxlengthTarget');
            if (!key) {
                return;
            }

            var max = parseInt($field.attr('maxlength'), 10);
            if (!max) {
                return;
            }

            var val = String($field.val() || '');
            if (val.length > max) {
                val = val.slice(0, max);
                $field.val(val);
            }

            var remaining = max - val.length;
            var used = val.length;
            var counterFormat = String($field.data('maxlengthFormat') || 'remaining');
            var $counter = $('[data-maxlength-counter="' + key + '"]');
            if ($counter.length) {
                if (counterFormat === 'used-max') {
                    $counter.text(formatMessage(getMessage('fieldMaxUsedTemplate', '%1$d/%2$d caracteres'), [used, max]));
                } else {
                    $counter.text(formatMessage(getMessage('fieldMaxRemainingTemplate', 'Máximo %1$d caracteres (%2$d restantes)'), [max, remaining]));
                }
                $counter.toggleClass('is-limit-near', remaining <= 10);
            }

            if (remaining <= 0) {
                $field.get(0).setCustomValidity(formatMessage(getMessage('fieldMaxReachedTemplate', 'Has alcanzado el máximo de %d caracteres.'), [max]));
            } else {
                $field.get(0).setCustomValidity('');
            }
        }

        $form.find('[data-maxlength-target]').each(function () {
            updateCounter($(this));
        });

        $form.on('input', '[data-maxlength-target]', function () {
            updateCounter($(this));
        });

        $form.on('agpPvStepChanged', function () {
            $form.find('[data-maxlength-target]').each(function () {
                updateCounter($(this));
            });
        });
    }


    function initAppShortcut() {
        var $container = $('#agp-pv-app-shortcut');
        if (!$container.length) {
            return;
        }

        var $text = $('#agp-pv-app-shortcut-text');
        var $button = $('#agp-pv-app-shortcut-install');
        var deferredPrompt = null;

        function isIos() {
            var ua = window.navigator.userAgent || '';
            var platform = window.navigator.platform || '';
            return /iPad|iPhone|iPod/.test(ua) || (platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
        }

        function isInStandaloneMode() {
            var mediaStandalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
            var navigatorStandalone = typeof window.navigator.standalone !== 'undefined' && !!window.navigator.standalone;
            return !!(mediaStandalone || navigatorStandalone);
        }

        function setShortcutCopy(message, showButton) {
            $text.text(message);
            if (showButton) {
                $button.removeAttr('hidden').text(getMessage('appShortcutInstallButton', 'Agregar acceso directo'));
            } else {
                $button.attr('hidden', true);
            }
            $container.removeAttr('hidden');
        }

        if (isInStandaloneMode()) {
            setShortcutCopy(getMessage('appShortcutInstalled', 'Este formulario ya está abierto como app.'), false);
            return;
        }

        if (isIos()) {
            setShortcutCopy(getMessage('appShortcutIos', 'En iPhone/iPad: toca Compartir y luego “Agregar a pantalla de inicio”.'), false);
            return;
        }

        setShortcutCopy(getMessage('appShortcutManual', 'En tu navegador, abre el menú y elige “Instalar app” o “Agregar a pantalla de inicio”.'), false);

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            deferredPrompt = event;
            setShortcutCopy(getMessage('appShortcutAvailable', 'Instala este formulario como acceso directo para abrirlo como app desde tu dispositivo.'), true);
        });

        $button.on('click', function () {
            if (!deferredPrompt || typeof deferredPrompt.prompt !== 'function') {
                setStatusMessage(getMessage('appShortcutManual', 'En tu navegador, abre el menú y elige “Instalar app” o “Agregar a pantalla de inicio”.'), 'warning');
                return;
            }

            deferredPrompt.prompt();
            if (deferredPrompt.userChoice && typeof deferredPrompt.userChoice.then === 'function') {
                deferredPrompt.userChoice.finally(function () {
                    deferredPrompt = null;
                    $button.attr('hidden', true);
                });
            } else {
                deferredPrompt = null;
                $button.attr('hidden', true);
            }
        });

        window.addEventListener('appinstalled', function () {
            deferredPrompt = null;
            setShortcutCopy(getMessage('appShortcutInstalled', 'Este formulario ya está abierto como app.'), false);
        });
    }

    function initHeavyStepFeatures() {
        var signaturesInitialized = false;
        var photosInitialized = false;
        function maybeInitForStep(step) {
            if (step === 3 && !signaturesInitialized) {
                initSignatures();
                signaturesInitialized = true;
            }

            if (step === 4 && !photosInitialized) {
                initPhotos();
                photosInitialized = true;
            }
        }

        $('#agp-pv-form').on('agpPvStepChanged', function (e, step) {
            maybeInitForStep(parseInt(step, 10) || 1);
        });

        var currentStep = parseInt($('#agp-pv-form').data('agpPvCurrentStep'), 10) || 1;
        maybeInitForStep(currentStep);
    }




    function initServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        if (!window.isSecureContext && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
            return;
        }

        if (!window.agpPvData || !agpPvData.serviceWorker || !agpPvData.serviceWorker.url) {
            return;
        }

        var swUrl = String(agpPvData.serviceWorker.url || '');
        var swScope = String((agpPvData.serviceWorker && agpPvData.serviceWorker.scope) || '/post-venta/');

        navigator.serviceWorker.register(swUrl, { scope: swScope }).catch(function () {
            // noop
        });
    }

    function initConnectivityAndPending() {
        var PENDING_KEY = 'agp_pv_pending_submit_v1';
        var PENDING_TTL_MS = 24 * 60 * 60 * 1000;
        var $form = $('#agp-pv-form');
        var $networkStatus = $('.agp-pv-network-status');
        var $retryButton = $('.agp-pv-retry-pending');

        if ($form.data('agpPvConnectivityBound')) {
            return;
        }
        $form.data('agpPvConnectivityBound', true);

        function hasPendingSubmission() {
            if (typeof window.localStorage === 'undefined') {
                return false;
            }

            var raw = '';
            try {
                raw = String(window.localStorage.getItem(PENDING_KEY) || '');
            } catch (e) {
                return false;
            }

            if (!raw) {
                return false;
            }

            var parsed = null;
            try {
                parsed = JSON.parse(raw);
            } catch (e) {
                try {
                    window.localStorage.removeItem(PENDING_KEY);
                } catch (ignored) {
                    // ignore localStorage issues
                }
                return false;
            }

            var savedAt = parsed && parsed.savedAt ? parseInt(parsed.savedAt, 10) : 0;
            if (!savedAt || (Date.now() - savedAt) > PENDING_TTL_MS) {
                try {
                    window.localStorage.removeItem(PENDING_KEY);
                } catch (ignored) {
                    // ignore localStorage issues
                }
                emitFrontendEvent('pending_expired', {});
                return false;
            }

            return true;
        }

        function savePendingSubmission(reason) {
            if (typeof window.localStorage === 'undefined') {
                return;
            }

            var payload = {
                savedAt: Date.now(),
                reason: String(reason || 'offline'),
                step: parseInt($form.data('agpPvCurrentStep'), 10) || 1
            };

            try {
                window.localStorage.setItem(PENDING_KEY, JSON.stringify(payload));
                emitFrontendEvent('pending_saved', { reason: payload.reason, step: payload.step });
            } catch (e) {
                // ignore localStorage issues
            }
        }

        function clearPendingSubmission() {
            if (typeof window.localStorage === 'undefined') {
                return;
            }

            try {
                window.localStorage.removeItem(PENDING_KEY);
                emitFrontendEvent('pending_cleared', {});
            } catch (e) {
                // ignore localStorage issues
            }
        }

        function refreshRetryVisibility() {
            if (!$retryButton.length) {
                return;
            }

            if (hasPendingSubmission()) {
                $retryButton.removeAttr('hidden');
            } else {
                $retryButton.attr('hidden', true);
            }
        }

        function updateNetworkState() {
            var online = navigator.onLine !== false;

            emitFrontendEvent('network_state', { online: online });

            if ($networkStatus.length) {
                if (online) {
                    $networkStatus
                        .removeClass('is-offline')
                        .text(getMessage('networkOnline', 'Conexión disponible.'));
                } else {
                    $networkStatus
                        .addClass('is-offline')
                        .text(getMessage('networkOffline', 'Sin conexión. Puedes completar el formulario y reintentar el envío cuando vuelva Internet.'));
                }
            }

            if (online && hasPendingSubmission()) {
                setStatusMessage(getMessage('pendingReadyToRetry', 'Hay un envío pendiente listo para reintentar.'), 'warning');
            }
        }

        $form.data('agpPvSavePendingSubmit', savePendingSubmission);
        $form.data('agpPvClearPendingSubmit', clearPendingSubmission);
        $form.data('agpPvHasPendingSubmit', hasPendingSubmission);

        $form.on('click', '.agp-pv-retry-pending', function () {
            if ($form.data('agpPvSubmitInFlight')) {
                return;
            }

            if (navigator.onLine === false) {
                setStatusMessage(getMessage('networkOfflineRetryBlocked', 'Sin conexión. No se puede reintentar todavía.'), 'warning');
                emitFrontendEvent('pending_retry_blocked_offline', {});
                return;
            }

            setStatusMessage(getMessage('pendingRetrying', 'Reintentando envío pendiente...'), 'warning');
            emitFrontendEvent('pending_retry_click', { online: true });
            $form.trigger('submit');
        });

        window.addEventListener('online', function () {
            updateNetworkState();
            refreshRetryVisibility();
        });

        window.addEventListener('offline', function () {
            updateNetworkState();
            refreshRetryVisibility();
        });

        updateNetworkState();
        refreshRetryVisibility();
    }

    function initForm() {
        ensureErrorIds();

        function updateSubmittedStepContent(successState) {
            var $submittedStep = $('[data-step="5"]');
            if (!$submittedStep.length) {
                return;
            }

            var payload = successState && typeof successState === 'object' ? successState : {};
            var title = getMessage('submittedStepTitle', 'Envío completado');
            var cta = getMessage('submittedStepCta', 'Iniciar nuevo envío');
            var fallback = getMessage('submittedStepFallbackMessage', 'Tu informe fue enviado. Puedes iniciar un nuevo envío cuando lo necesites.');
            var type = payload.type === 'warning' ? 'warning' : 'success';
            var message = payload.text || fallback;
            var reportId = payload.reportId;

            var $copy = $submittedStep.find('.agp-pv-copy-report');
            var $meta = $submittedStep.find('#agp-pv-success-meta');
            var $message = $submittedStep.find('#agp-pv-success-message');

            $submittedStep.find('.agp-pv-section-title').text(title);
            $submittedStep.find('.agp-pv-new-report').text(cta);
            $copy.text(getMessage('submittedStepCopyReport', 'Copiar ID informe'));

            $message
                .text(message)
                .toggleClass('is-warning', type === 'warning');

            $meta.text(
                type === 'warning'
                    ? getMessage('submittedStepMetaWarning', 'El informe quedó guardado con advertencias revisables en el mensaje superior.')
                    : getMessage('submittedStepMetaSuccess', 'Guardamos tu informe correctamente.')
            );

            if (reportId) {
                $copy.data('reportId', String(reportId)).removeAttr('hidden');
            } else {
                $copy.removeData('reportId').attr('hidden', true);
            }
        }

        // clear invalid state on user input
        $('#agp-pv-form').on('input change', 'input, select, textarea', function () {
            $(this).removeClass('agp-pv-invalid').removeAttr('aria-invalid');
            var name = $(this).attr('name');
            if (name) {
                $('.agp-pv-error[data-error-for="' + name + '"]').text('');
            }

            if (name === 'trabajos_realizados' || name === 'observaciones') {
                syncDetalleMinimumConstraint(false);
            }
        });

        $('#agp-pv-form').on('click', '.agp-pv-new-report', function () {
            var $form = $('#agp-pv-form');
            var setSubmittedState = $form.data('agpPvSetSubmittedState');
            var goToStep = $form.data('agpPvGoToStep');

            if (typeof setSubmittedState === 'function') {
                setSubmittedState(null);
            }

            $('[data-step="5"]').find('.agp-pv-copy-report').removeData('reportId').attr('hidden', true);
            $('[data-step="5"]').find('#agp-pv-success-meta').text('');

            setStatusMessage('');

            if (typeof goToStep === 'function') {
                goToStep(1);
            }
        });

        $('#agp-pv-form').on('click', '.agp-pv-copy-report', function () {
            var reportId = String($(this).data('reportId') || '');
            if (!reportId) {
                return;
            }

            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(reportId)
                    .then(function () {
                        setStatusMessage(getMessage('submittedStepCopySuccess', 'ID informe copiado al portapapeles.'), 'success');
                    })
                    .catch(function () {
                        setStatusMessage(getMessage('submittedStepCopyUnavailable', 'No se pudo copiar automáticamente. Puedes copiarlo manualmente desde el mensaje.'), 'warning');
                    });
                return;
            }

            setStatusMessage(getMessage('submittedStepCopyUnavailable', 'No se pudo copiar automáticamente. Puedes copiarlo manualmente desde el mensaje.'), 'warning');
        });

        $('#agp-pv-form').on('submit', function (e) {
            e.preventDefault();

            clearFieldErrors();

            emitFrontendEvent('submit_attempt', { online: navigator.onLine !== false });

            if ($('#agp-pv-form').data('agpPvSubmitInFlight')) {
                emitFrontendEvent('submit_skipped_inflight', {});
                return;
            }

            var savePendingSubmit = $('#agp-pv-form').data('agpPvSavePendingSubmit');
            var clearPendingSubmit = $('#agp-pv-form').data('agpPvClearPendingSubmit');

            if (navigator.onLine === false) {
                if (typeof savePendingSubmit === 'function') {
                    savePendingSubmit('offline_before_submit');
                }
                setStatusMessage(getMessage('networkOfflineSubmitBlocked', 'Sin conexión. Guardamos el envío como pendiente para que puedas reintentarlo.'), 'warning');
                emitFrontendEvent('submit_blocked_offline', { reason: 'offline_before_submit' });
                $('.agp-pv-retry-pending').removeAttr('hidden');
                return;
            }

            var goToStep = $('#agp-pv-form').data('agpPvGoToStep');
            if (typeof goToStep === 'function') {
                goToStep(4);
            }

            // Native validation first (works with conditional disable)
            var formEl = this;
            syncDetalleMinimumConstraint(false);
            if (typeof formEl.checkValidity === 'function' && !formEl.checkValidity()) {
                if (typeof formEl.reportValidity === 'function') {
                    formEl.reportValidity();
                }
                highlightInvalidFromNative(formEl);

                var summaryItems = [];
                $(formEl).find(':invalid').each(function () {
                    var $field = $(this);
                    if ($field.is(':hidden') || $field.prop('disabled')) {
                        return;
                    }

                    var fieldId = $field.attr('id');
                    if (!fieldId) {
                        return;
                    }

                    summaryItems.push({
                        id: fieldId,
                        message: getFieldErrorMessage($field) || getMessage('statusReviewFields', 'Revisa los campos marcados.')
                    });
                });

                renderErrorSummary(summaryItems, getMessage('errorSummaryTitle', 'Revisa los siguientes campos antes de continuar:'), true);
                setStatusMessage(getMessage('statusReviewFields', 'Revisa los campos marcados.'), 'error');
                emitFrontendEvent('submit_validation_failed', { invalidCount: summaryItems.length });
                return;
            }

            collectSignatureData();

            var $submit = $('.agp-pv-submit');
            var $retryButton = $('.agp-pv-retry-pending');
            var originalText = $submit.data('original-text') || $submit.text();
            $submit.data('original-text', originalText);
            $submit.prop('disabled', true).text(getMessage('statusSending', 'Enviando...'));
            $retryButton.prop('disabled', true);
            $('#agp-pv-form').data('agpPvSubmitInFlight', true);

            var formData = new FormData(formEl);
            formData.append('action', 'agp_pv_submit');
            formData.append('nonce', agpPvData.nonce);

            setStatusMessage(getMessage('statusSending', 'Enviando...'));

            $.ajax({
                url: agpPvData.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            })
                .done(function (response) {
                    if (response && response.success) {
                        var successState = buildSuccessMessage(response.data || {});

                        if (response.data && response.data.mail_sent === false && response.data.mail_error) {
                            successState.text += ' ' + response.data.mail_error;
                        }

                        setStatusMessage(successState.text, successState.type);

                        emitFrontendEvent('submit_success', { statusType: (response.data && response.data.status_type) || 'success', reportId: (response.data && (response.data.report_id || response.data.submission_id)) || null });

                        $('#agp-pv-form').data('agpPvInternalReset', true);

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

                        var setSubmittedState = $('#agp-pv-form').data('agpPvSetSubmittedState');
                        if (typeof setSubmittedState === 'function') {
                            setSubmittedState(successState.type);
                        }

                        updateSubmittedStepContent(successState);

                        var goToStep = $('#agp-pv-form').data('agpPvGoToStep');
                        if (typeof goToStep === 'function') {
                            goToStep(5);
                        }

                        var clearDraft = $('#agp-pv-form').data('agpPvClearDraft');
                        if (typeof clearDraft === 'function') {
                            clearDraft();
                        }

                        if (typeof clearPendingSubmit === 'function') {
                            clearPendingSubmit();
                        }
                        $('.agp-pv-retry-pending').attr('hidden', true);
                        $('#agp-pv-form').data('agpPvInternalReset', false);
                    } else {
                        // Field errors
                        if (response && response.data && response.data.errors) {
                            showFieldErrors(response.data.errors);
                            setStatusMessage(getMessage('statusReviewFields', 'Revisa los campos marcados.'), 'error');
                            emitFrontendEvent('submit_server_field_errors', { fieldCount: Object.keys(response.data.errors || {}).length });
                        } else if (response && response.data && response.data.message) {
                            setStatusMessage(response.data.message, 'error');
                            emitFrontendEvent('submit_server_error', { hasMessage: true });
                        } else {
                            setStatusMessage(agpPvData.messages.invalid, 'error');
                            emitFrontendEvent('submit_server_error', { hasMessage: false });
                        }
                    }
                })
                .fail(function (xhr) {
                    if (navigator.onLine === false || (xhr && xhr.status === 0)) {
                        if (typeof savePendingSubmit === 'function') {
                            savePendingSubmit('network_error');
                        }
                        $('.agp-pv-retry-pending').removeAttr('hidden');
                        setStatusMessage(getMessage('networkOfflineSubmitBlocked', 'Sin conexión. Guardamos el envío como pendiente para que puedas reintentarlo.'), 'warning');
                        emitFrontendEvent('submit_network_error', { offline: navigator.onLine === false, xhrStatus: xhr && typeof xhr.status !== 'undefined' ? xhr.status : null });
                        return;
                    }

                    setStatusMessage(agpPvData.messages.invalid, 'error');
                    emitFrontendEvent('submit_request_failed', { xhrStatus: xhr && typeof xhr.status !== 'undefined' ? xhr.status : null });
                })
                .always(function () {
                    $submit.prop('disabled', false).text(originalText);
                    $retryButton.prop('disabled', false);
                    $('#agp-pv-form').data('agpPvSubmitInFlight', false);
                    $('#agp-pv-form').data('agpPvInternalReset', false);
                });
        });
    }

    $(function () {
        initConditionalFields();
        initStepper();
        initHeavyStepFeatures();
        initAppShortcut();
        initServiceWorker();
        initDraftPersistence();
        initTextLengthGuides();
        initConnectivityAndPending();
        initForm();
    });
})(jQuery);
