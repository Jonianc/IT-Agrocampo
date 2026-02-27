<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AGP_PV_Plugin {
    private static ?AGP_PV_Plugin $instance = null;

    private function __construct() {
        add_action( 'init', array( $this, 'maybe_upgrade' ) );
        add_action( 'init', array( $this, 'register_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'register_query_var' ) );
        add_action( 'template_redirect', array( $this, 'render_standalone' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        AGP_PV_Ajax::get_instance();
        AGP_PV_Admin::get_instance();
    }

    public static function get_instance(): AGP_PV_Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void {
        AGP_PV_DB::maybe_upgrade();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    public function maybe_upgrade(): void {
        AGP_PV_DB::maybe_upgrade();
    }

    public function register_rewrite(): void {
        add_rewrite_rule( '^post-venta/?$', 'index.php?agp_pv_standalone=1', 'top' );
    }

    public function register_query_var( array $vars ): array {
        $vars[] = 'agp_pv_standalone';
        return $vars;
    }

    public function is_standalone(): bool {
        return '1' === get_query_var( 'agp_pv_standalone' );
    }

    public function render_standalone(): void {
        if ( ! $this->is_standalone() ) {
            return;
        }

        status_header( 200 );
        nocache_headers();

        $template = AGP_PV_PLUGIN_DIR . 'templates/standalone.php';
        if ( file_exists( $template ) ) {
            include $template;
            exit;
        }
    }

    public function enqueue_assets(): void {
        if ( ! $this->is_standalone() ) {
            return;
        }

        wp_enqueue_style(
            'agp-pv-standalone',
            AGP_PV_PLUGIN_URL . 'assets/css/standalone.css',
            array(),
            AGP_PV_VERSION
        );

        wp_enqueue_script(
            'agp-pv-standalone',
            AGP_PV_PLUGIN_URL . 'assets/js/standalone.js',
            array( 'jquery' ),
            AGP_PV_VERSION,
            true
        );

        wp_localize_script(
            'agp-pv-standalone',
            'agpPvData',
            array(
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'agp_pv_submit' ),
                'debugFrontend' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
                'detalleMinimumChars' => 10,
                'messages' => array(
                    'invalid' => __( 'Error: tu formulario no es válido, ¡por favor, corrige los errores!', 'agrocampo-post-venta' ),
                    'success' => __( 'Informe enviado.', 'agrocampo-post-venta' ),
                    'successMailWarning' => __( 'Informe guardado correctamente, pero NO se pudo enviar el correo.', 'agrocampo-post-venta' ),
                    'successPdfWarning' => __( 'Informe enviado, pero el PDF no pudo adjuntarse.', 'agrocampo-post-venta' ),
                    'reportIdPrefix' => __( 'ID informe', 'agrocampo-post-venta' ),
                    'statusReviewFieldsBeforeContinue' => __( 'Revisa los campos marcados antes de continuar.', 'agrocampo-post-venta' ),
                    'statusReviewFields' => __( 'Revisa los campos marcados.', 'agrocampo-post-venta' ),
                    'errorSummaryTitle' => __( 'Revisa los siguientes campos antes de continuar:', 'agrocampo-post-venta' ),
                    'statusSending' => __( 'Enviando...', 'agrocampo-post-venta' ),
                    'draftRecovered' => __( 'Se recuperó un borrador local.', 'agrocampo-post-venta' ),
                    'draftCleared' => __( 'Borrador local eliminado.', 'agrocampo-post-venta' ),
                    'photosMaxCount' => __( 'Máximo 10 fotos.', 'agrocampo-post-venta' ),
                    'photosMaxCountTrimmed' => __( 'Máximo 10 fotos. El resto fue descartado.', 'agrocampo-post-venta' ),
                    'photoMaxSize' => __( 'Cada foto debe pesar máximo 5 MB.', 'agrocampo-post-venta' ),
                    'photosTotalLimit' => __( 'Límite total alcanzado: 20 MB.', 'agrocampo-post-venta' ),
                    'photoOmittedInvalidFormat' => __( 'Se omitió "%s": formato no permitido.', 'agrocampo-post-venta' ),
                    'photoOmittedTooLarge' => __( 'Se omitió "%s": supera 5 MB.', 'agrocampo-post-venta' ),
                    'fieldMaxRemainingTemplate' => __( 'Máximo %1$d caracteres (%2$d restantes)', 'agrocampo-post-venta' ),
                    'fieldMaxUsedTemplate' => __( '%1$d/%2$d caracteres', 'agrocampo-post-venta' ),
                    'fieldMaxReachedTemplate' => __( 'Has alcanzado el máximo de %d caracteres.', 'agrocampo-post-venta' ),
                    'detalleAtLeastOneMinChars' => __( 'Completa “Trabajos realizados” u “Observaciones” con al menos %d caracteres.', 'agrocampo-post-venta' ),
                    'stepStateEmpty' => __( 'vacío', 'agrocampo-post-venta' ),
                    'stepStateInProgress' => __( 'en progreso', 'agrocampo-post-venta' ),
                    'stepStateComplete' => __( 'completo', 'agrocampo-post-venta' ),
                    'stepStateSubmitted' => __( 'enviado', 'agrocampo-post-venta' ),
                    'stepStateSubmittedWarning' => __( 'enviado con advertencia', 'agrocampo-post-venta' ),
                    'submittedStepTitle' => __( 'Envío completado', 'agrocampo-post-venta' ),
                    'submittedStepCta' => __( 'Iniciar nuevo envío', 'agrocampo-post-venta' ),
                    'submittedStepFallbackMessage' => __( 'Tu informe fue enviado. Puedes iniciar un nuevo envío cuando lo necesites.', 'agrocampo-post-venta' ),
                    'submittedStepMetaSuccess' => __( 'Guardamos tu informe correctamente.', 'agrocampo-post-venta' ),
                    'submittedStepMetaWarning' => __( 'El informe quedó guardado con advertencias revisables en el mensaje superior.', 'agrocampo-post-venta' ),
                    'submittedStepCopyReport' => __( 'Copiar ID informe', 'agrocampo-post-venta' ),
                    'submittedStepCopySuccess' => __( 'ID informe copiado al portapapeles.', 'agrocampo-post-venta' ),
                    'submittedStepCopyUnavailable' => __( 'No se pudo copiar automáticamente. Puedes copiarlo manualmente desde el mensaje.', 'agrocampo-post-venta' ),
                    'appShortcutAvailable' => __( 'Instala este formulario como acceso directo para abrirlo como app desde tu dispositivo.', 'agrocampo-post-venta' ),
                    'appShortcutInstallButton' => __( 'Agregar acceso directo', 'agrocampo-post-venta' ),
                    'appShortcutIos' => __( 'En iPhone/iPad: toca Compartir y luego “Agregar a pantalla de inicio”.', 'agrocampo-post-venta' ),
                    'appShortcutManual' => __( 'En tu navegador, abre el menú y elige “Instalar app” o “Agregar a pantalla de inicio”.', 'agrocampo-post-venta' ),
                    'appShortcutInstalled' => __( 'Este formulario ya está abierto como app.', 'agrocampo-post-venta' ),
                    'networkOnline' => __( 'Conexión disponible.', 'agrocampo-post-venta' ),
                    'networkOffline' => __( 'Sin conexión. Puedes completar el formulario y reintentar el envío cuando vuelva Internet.', 'agrocampo-post-venta' ),
                    'networkOfflineSubmitBlocked' => __( 'Sin conexión. Guardamos el envío como pendiente para que puedas reintentarlo.', 'agrocampo-post-venta' ),
                    'networkOfflineRetryBlocked' => __( 'Sin conexión. No se puede reintentar todavía.', 'agrocampo-post-venta' ),
                    'pendingReadyToRetry' => __( 'Hay un envío pendiente listo para reintentar.', 'agrocampo-post-venta' ),
                    'pendingRetrying' => __( 'Reintentando envío pendiente...', 'agrocampo-post-venta' ),
                ),
            )
        );
    }
}
