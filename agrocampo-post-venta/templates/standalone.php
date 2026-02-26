<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e( 'Informe Técnico', 'agrocampo-post-venta' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="agp-pv-standalone">
<div class="agp-pv-standalone__wrapper">
    <h1><?php esc_html_e( 'Informe Técnico', 'agrocampo-post-venta' ); ?></h1>

    <form id="agp-pv-form" enctype="multipart/form-data">
        <input type="text" name="agp_pv_hp" class="agp-pv-honeypot" tabindex="-1" autocomplete="off">

        <nav class="agp-pv-stepper" aria-label="<?php esc_attr_e( 'Progreso del formulario', 'agrocampo-post-venta' ); ?>">
            <ol>
                <li class="is-active" data-step-indicator="1"><?php esc_html_e( 'Datos', 'agrocampo-post-venta' ); ?></li>
                <li data-step-indicator="2"><?php esc_html_e( 'Detalle', 'agrocampo-post-venta' ); ?></li>
                <li data-step-indicator="3"><?php esc_html_e( 'Firmas', 'agrocampo-post-venta' ); ?></li>
                <li data-step-indicator="4"><?php esc_html_e( 'Adjuntos', 'agrocampo-post-venta' ); ?></li>
            </ol>
        </nav>

        <div class="agp-pv-error-summary" id="agp-pv-error-summary" role="alert" aria-live="assertive" hidden>
            <p class="agp-pv-error-summary__title"></p>
            <ul class="agp-pv-error-summary__list"></ul>
        </div>

        <div class="agp-pv-step is-active" data-step="1">
            <h2 class="agp-pv-section-title"><?php esc_html_e( 'Datos del servicio', 'agrocampo-post-venta' ); ?></h2>

            <div class="agp-pv-field">
                <label for="agp-pv-tecnico"><?php esc_html_e( 'Técnico', 'agrocampo-post-venta' ); ?> *</label>
                <select id="agp-pv-tecnico" name="tecnico" required>
                    <option value=""><?php esc_html_e( 'Seleccione su nombre', 'agrocampo-post-venta' ); ?></option>
                    <option value="Enrique Rivas Diaz">Enrique Rivas Diaz</option>
                    <option value="Juan Castro Meza">Juan Castro Meza</option>
                    <option value="Bastián Cancino Ortega">Bastián Cancino Ortega</option>
                    <option value="Maximiliano Tapia Briones">Maximiliano Tapia Briones</option>
                    <option value="Luis Zúñiga Medel">Luis Zúñiga Medel</option>
                    <option value="Daniel Rojas Zúñiga">Daniel Rojas Zúñiga</option>
                    <option value="Alejandro Vásquez Gonzales">Alejandro Vásquez Gonzales</option>
                    <option value="Darwin Reveco Vásquez">Darwin Reveco Vásquez</option>
                    <option value="Moisés Acevedo Abaca">Moisés Acevedo Abaca</option>
                    <option value="Jeremy Castillo Díaz">Jeremy Castillo Díaz</option>
                    <option value="Eduardo Espinoza">Eduardo Espinoza</option>
                    <option value="Guillermo Jerez">Guillermo Jerez</option>
                    <option value="Jorge Valdez">Jorge Valdez</option>
                    <option value="Benjamín Castro">Benjamín Castro</option>
                </select>
                <span class="agp-pv-error" data-error-for="tecnico"></span>
            </div>

            <div class="agp-pv-grid">
                <div class="agp-pv-field">
                    <label for="agp-pv-cliente"><?php esc_html_e( 'Cliente', 'agrocampo-post-venta' ); ?> *</label>
                    <input id="agp-pv-cliente" name="cliente" type="text" required maxlength="80" data-maxlength-target="cliente" autocomplete="organization" autocapitalize="words" spellcheck="false">
                    <small class="agp-pv-help agp-pv-charcount" data-maxlength-counter="cliente"></small>
                    <span class="agp-pv-error" data-error-for="cliente"></span>
                </div>
                <div class="agp-pv-field">
                    <label for="agp-pv-email-cliente"><?php esc_html_e( 'Correo Cliente', 'agrocampo-post-venta' ); ?></label>
                    <input id="agp-pv-email-cliente" name="email_cliente" type="email" inputmode="email" autocomplete="email" autocapitalize="off" spellcheck="false">
                </div>
            </div>

            <div class="agp-pv-grid">
                <div class="agp-pv-field">
                    <label for="agp-pv-faena"><?php esc_html_e( 'Faena Lugar', 'agrocampo-post-venta' ); ?></label>
                    <input id="agp-pv-faena" name="faena_lugar" type="text" maxlength="80" data-maxlength-target="faena_lugar" autocomplete="street-address" autocapitalize="sentences">
                    <small class="agp-pv-help agp-pv-charcount" data-maxlength-counter="faena_lugar"></small>
                </div>
                <div class="agp-pv-field">
                    <label for="agp-pv-maquina"><?php esc_html_e( 'Máquina', 'agrocampo-post-venta' ); ?> *</label>
                    <input id="agp-pv-maquina" name="maquina" type="text" required maxlength="50" data-maxlength-target="maquina" autocomplete="off" autocapitalize="characters" spellcheck="false">
                    <small class="agp-pv-help agp-pv-charcount" data-maxlength-counter="maquina"></small>
                    <span class="agp-pv-error" data-error-for="maquina"></span>
                </div>
            </div>

            <div class="agp-pv-grid">
                <div class="agp-pv-field">
                    <label for="agp-pv-modelo"><?php esc_html_e( 'Modelo', 'agrocampo-post-venta' ); ?> *</label>
                    <input id="agp-pv-modelo" name="modelo" type="text" required maxlength="50" data-maxlength-target="modelo" autocomplete="off" autocapitalize="characters" spellcheck="false">
                    <small class="agp-pv-help agp-pv-charcount" data-maxlength-counter="modelo"></small>
                    <span class="agp-pv-error" data-error-for="modelo"></span>
                </div>
                <div class="agp-pv-field">
                    <label for="agp-pv-serie"><?php esc_html_e( 'Serie', 'agrocampo-post-venta' ); ?> *</label>
                    <input id="agp-pv-serie" name="serie" type="text" required maxlength="60" data-maxlength-target="serie" autocomplete="off" autocapitalize="characters" spellcheck="false">
                    <small class="agp-pv-help agp-pv-charcount" data-maxlength-counter="serie"></small>
                    <span class="agp-pv-error" data-error-for="serie"></span>
                </div>
            </div>

            <div class="agp-pv-grid">
                <div class="agp-pv-field">
                    <label for="agp-pv-numero-interno"><?php esc_html_e( 'N° Interno', 'agrocampo-post-venta' ); ?> *</label>
                    <input id="agp-pv-numero-interno" name="numero_interno" type="text" required maxlength="30" data-maxlength-target="numero_interno" inputmode="text" pattern="[0-9A-Za-z\-\/_ ]{1,30}" title="<?php esc_attr_e( 'Use solo letras, números, guion, slash, guion bajo y espacios', 'agrocampo-post-venta' ); ?>" autocomplete="off" autocapitalize="characters" spellcheck="false">
                    <small class="agp-pv-help agp-pv-charcount" data-maxlength-counter="numero_interno"></small>
                    <span class="agp-pv-error" data-error-for="numero_interno"></span>
                </div>
                <div class="agp-pv-field agp-pv-field--date" data-condition="fecha">
                    <label for="agp-pv-fecha"><?php esc_html_e( 'Fecha', 'agrocampo-post-venta' ); ?> *</label>
                    <input id="agp-pv-fecha" name="fecha" type="date" required autocomplete="off">
                    <span class="agp-pv-error" data-error-for="fecha"></span>
                </div>
            </div>

            <div class="agp-pv-grid">
                <div class="agp-pv-field">
                    <label for="agp-pv-horas"><?php esc_html_e( 'Horas', 'agrocampo-post-venta' ); ?> *</label>
                    <input id="agp-pv-horas" name="horas" type="number" inputmode="numeric" min="0" step="1" required max="999999" autocomplete="off">
                    <span class="agp-pv-error" data-error-for="horas"></span>
                </div>
                <div class="agp-pv-field">
                    <label for="agp-pv-tipo-servicio"><?php esc_html_e( 'Tipo de Servicio', 'agrocampo-post-venta' ); ?> *</label>
                    <select id="agp-pv-tipo-servicio" name="tipo_servicio" required>
                        <option value=""><?php esc_html_e( 'Seleccione', 'agrocampo-post-venta' ); ?></option>
                        <option value="one">Factura Cliente</option>
                        <option value="two">Garantia</option>
                        <option value="Interno">Mantención</option>
                        <option value="Visita-de-Cortesía">Visita de Cortesía</option>
                        <option value="Diagnostico-Técnico">Diagnostico Técnico</option>
                        <option value="Entrega-Técnica">Entrega Técnica</option>
                    </select>
                    <span class="agp-pv-error" data-error-for="tipo_servicio"></span>
                </div>
            </div>

            <div class="agp-pv-grid">
                <div class="agp-pv-field" data-condition="tipo-mantencion">
                    <label for="agp-pv-tipo-mantencion"><?php esc_html_e( 'Tipo de Mantención', 'agrocampo-post-venta' ); ?> *</label>
                    <select id="agp-pv-tipo-mantencion" name="tipo_mantencion" required>
                        <option value=""><?php esc_html_e( 'Seleccione', 'agrocampo-post-venta' ); ?></option>
                        <option value="one">100 Horas</option>
                        <option value="two">400 Horas</option>
                        <option value="500-Horas">500 Horas</option>
                        <option value="800-Horas">800 Horas</option>
                        <option value="1000-Horas">1000 Horas</option>
                        <option value="1200">1200 Horas</option>
                        <option value="1600-Horas">1500 Horas</option>
                        <option value="OTRO">OTRO</option>
                    </select>
                    <span class="agp-pv-error" data-error-for="tipo_mantencion"></span>
                </div>
                <div class="agp-pv-field" data-condition="cantidad-horas">
                    <label for="agp-pv-cantidad-horas"><?php esc_html_e( 'CANTIDAD DE HORAS', 'agrocampo-post-venta' ); ?> *</label>
                    <input id="agp-pv-cantidad-horas" name="cantidad_horas" type="number" inputmode="numeric" min="0" step="1" required max="999999" autocomplete="off">
                    <span class="agp-pv-error" data-error-for="cantidad_horas"></span>
                </div>
            </div>

            <div class="agp-pv-grid" data-condition="garantia">
                <div class="agp-pv-field">
                    <label for="agp-pv-fecha-reparacion"><?php esc_html_e( 'Fecha Reparación', 'agrocampo-post-venta' ); ?> *</label>
                    <input id="agp-pv-fecha-reparacion" name="fecha_reparacion" type="date" required autocomplete="off">
                    <span class="agp-pv-error" data-error-for="fecha_reparacion"></span>
                </div>
                <div class="agp-pv-field">
                    <label for="agp-pv-fecha-cierre"><?php esc_html_e( 'Fecha Cierre', 'agrocampo-post-venta' ); ?></label>
                    <input id="agp-pv-fecha-cierre" name="fecha_cierre" type="date" autocomplete="off">
                </div>
            </div>

            <div class="agp-pv-step-actions">
                <button type="button" class="agp-pv-next" data-next-step="2"><?php esc_html_e( 'Siguiente', 'agrocampo-post-venta' ); ?></button>
            </div>
        </div>

        <div class="agp-pv-step" data-step="2" hidden>
            <h2 class="agp-pv-section-title"><?php esc_html_e( 'Detalle de trabajo', 'agrocampo-post-venta' ); ?></h2>

            <div class="agp-pv-field">
                <label for="agp-pv-lubricantes"><?php esc_html_e( 'Lubricantes', 'agrocampo-post-venta' ); ?></label>
                <textarea id="agp-pv-lubricantes" name="lubricantes" rows="3"></textarea>
            </div>

            <div class="agp-pv-field">
                <label for="agp-pv-filtros"><?php esc_html_e( 'Filtros Utilizados', 'agrocampo-post-venta' ); ?></label>
                <textarea id="agp-pv-filtros" name="filtros_utilizados" rows="3"></textarea>
            </div>

            <div class="agp-pv-field">
                <label for="agp-pv-componentes"><?php esc_html_e( 'Componentes Utilizados', 'agrocampo-post-venta' ); ?></label>
                <textarea id="agp-pv-componentes" name="componentes_utilizados" rows="3"></textarea>
            </div>

            <div class="agp-pv-field">
                <label for="agp-pv-trabajos"><?php esc_html_e( 'Trabajos realizados', 'agrocampo-post-venta' ); ?></label>
                <textarea id="agp-pv-trabajos" name="trabajos_realizados" rows="3" maxlength="455"></textarea>
            </div>

            <div class="agp-pv-field">
                <label for="agp-pv-observaciones"><?php esc_html_e( 'Observaciones', 'agrocampo-post-venta' ); ?></label>
                <textarea id="agp-pv-observaciones" name="observaciones" rows="3"></textarea>
            </div>

            <div class="agp-pv-step-actions">
                <button type="button" class="agp-pv-prev" data-prev-step="1"><?php esc_html_e( 'Anterior', 'agrocampo-post-venta' ); ?></button>
                <button type="button" class="agp-pv-next" data-next-step="3"><?php esc_html_e( 'Siguiente', 'agrocampo-post-venta' ); ?></button>
            </div>
        </div>

        <div class="agp-pv-step" data-step="3" hidden>
            <h2 class="agp-pv-section-title"><?php esc_html_e( 'Firmas', 'agrocampo-post-venta' ); ?></h2>

            <div class="agp-pv-field">
                <label><?php esc_html_e( 'Firma Cliente', 'agrocampo-post-venta' ); ?></label>
                <div class="agp-pv-signature" data-signature="cliente">
                    <canvas width="600" height="200"></canvas>
                    <div class="agp-pv-signature-hint"><?php esc_html_e( 'Firme aquí', 'agrocampo-post-venta' ); ?></div>
                    <button type="button" class="agp-pv-signature-clear" data-signature-clear="cliente"><?php esc_html_e( 'Limpiar', 'agrocampo-post-venta' ); ?></button>
                </div>
                <input type="hidden" name="firma_cliente" id="agp-pv-firma-cliente">
            </div>

            <div class="agp-pv-field" data-condition="garantia">
                <label><?php esc_html_e( 'Firma Técnico', 'agrocampo-post-venta' ); ?></label>
                <div class="agp-pv-signature" data-signature="tecnico">
                    <canvas width="600" height="200"></canvas>
                    <div class="agp-pv-signature-hint"><?php esc_html_e( 'Firme aquí', 'agrocampo-post-venta' ); ?></div>
                    <button type="button" class="agp-pv-signature-clear" data-signature-clear="tecnico"><?php esc_html_e( 'Limpiar', 'agrocampo-post-venta' ); ?></button>
                </div>
                <input type="hidden" name="firma_tecnico" id="agp-pv-firma-tecnico">
            </div>

            <div class="agp-pv-step-actions">
                <button type="button" class="agp-pv-prev" data-prev-step="2"><?php esc_html_e( 'Anterior', 'agrocampo-post-venta' ); ?></button>
                <button type="button" class="agp-pv-next" data-next-step="4"><?php esc_html_e( 'Siguiente', 'agrocampo-post-venta' ); ?></button>
            </div>
        </div>

        <div class="agp-pv-step" data-step="4" hidden>
            <h2 class="agp-pv-section-title"><?php esc_html_e( 'Adjuntos y envío', 'agrocampo-post-venta' ); ?></h2>

            <div class="agp-pv-field">
                <label for="agp-pv-fotos"><?php esc_html_e( 'Fotos (máx 10)', 'agrocampo-post-venta' ); ?></label>
                <input id="agp-pv-fotos" name="fotos[]" type="file" multiple accept="image/*">

                <div class="agp-pv-files-meta">
                    <span id="agp-pv-fotos-count" class="agp-pv-files-count">0/10</span>
                    <span id="agp-pv-fotos-size" class="agp-pv-files-size">0 MB</span>
                    <span class="agp-pv-help"><?php esc_html_e( 'Puede seleccionar fotos en varias tandas. Compresión alta activa: se muestra tamaño comprimido antes de enviar (máx. 5 MB por foto y 20 MB total).', 'agrocampo-post-venta' ); ?></span>
                </div>
                <div id="agp-pv-fotos-preview" class="agp-pv-files"></div>
                <span class="agp-pv-error" data-error-for="fotos"></span>
            </div>

            <div class="agp-pv-field">
                <label for="agp-pv-correo-copia"><?php esc_html_e( 'Correo Copia', 'agrocampo-post-venta' ); ?></label>
                <input id="agp-pv-correo-copia" name="correo_copia" type="email" inputmode="email" autocomplete="email" autocapitalize="off" spellcheck="false">
            </div>

            <div class="agp-pv-field agp-pv-actions">
                <div class="agp-pv-step-actions">
                    <button type="button" class="agp-pv-prev" data-prev-step="3"><?php esc_html_e( 'Anterior', 'agrocampo-post-venta' ); ?></button>
                    <button type="submit" class="agp-pv-submit">Enviar mensaje</button>
                </div>
                <button type="button" class="agp-pv-retry-pending" hidden><?php esc_html_e( 'Reintentar envío pendiente', 'agrocampo-post-venta' ); ?></button>
                <button type="button" class="agp-pv-clear-draft"><?php esc_html_e( 'Limpiar borrador local', 'agrocampo-post-venta' ); ?></button>
            </div>
        </div>
    </form>
    <span class="agp-pv-network-status" role="status" aria-live="polite"></span>
    <span class="agp-pv-status agp-pv-status--global" role="status" aria-live="polite"></span>
</div>

<?php wp_footer(); ?>
</body>
</html>
