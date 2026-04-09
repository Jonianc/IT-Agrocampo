<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AGP_PV_Admin {
    private static ?AGP_PV_Admin $instance = null;

    public static function get_instance(): AGP_PV_Admin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_agp_pv_resend_email', array( $this, 'handle_resend_email' ) );
        add_action( 'admin_post_agp_pv_regenerate_pdf', array( $this, 'handle_regenerate_pdf' ) );
        add_action( 'admin_post_agp_pv_mark_reviewed', array( $this, 'handle_mark_reviewed' ) );
        add_action( 'admin_post_agp_pv_send_test_email', array( $this, 'handle_send_test_email' ) );
        add_action( 'admin_post_agp_pv_send_overdue_test_email', array( $this, 'handle_send_overdue_test_email' ) );
        add_action( 'admin_post_agp_pv_send_summary_now', array( $this, 'handle_send_summary_now' ) );
        add_action( 'admin_post_agp_pv_save_recipients', array( $this, 'handle_save_recipients' ) );
        add_action( 'admin_post_agp_pv_save_notifications_settings', array( $this, 'handle_save_notifications_settings' ) );
        add_action( 'admin_post_agp_pv_save_technicians', array( $this, 'handle_save_technicians' ) );
        add_action( 'admin_post_agp_pv_save_logo', array( $this, 'handle_save_logo' ) );
        add_action( 'admin_post_agp_pv_import_legacy_csv', array( $this, 'handle_import_legacy_csv' ) );
        add_action( 'admin_post_agp_pv_export_legacy_csv', array( $this, 'handle_export_legacy_csv' ) );
        add_action( 'admin_post_agp_pv_export_observations_report', array( $this, 'handle_export_observations_report' ) );
        add_action( 'admin_post_agp_pv_delete_all_submissions', array( $this, 'handle_delete_all_submissions' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_view_pdf_request' ) );
        add_action( 'wp_ajax_agp_pv_regenerate_all_pdf_batch', array( $this, 'handle_regenerate_all_pdf_batch' ) );
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'Informe Técnico', 'agrocampo-post-venta' ),
            __( 'Informe Técnico', 'agrocampo-post-venta' ),
            'manage_options',
            'agp-pv-submissions',
            array( $this, 'render_list_page' ),
            'dashicons-forms'
        );

        add_submenu_page(
            'agp-pv-submissions',
            __( 'Informes con observaciones', 'agrocampo-post-venta' ),
            __( 'Con observaciones', 'agrocampo-post-venta' ),
            'manage_options',
            'agp-pv-observations',
            array( $this, 'render_observations_page' )
        );

        add_submenu_page(
            'agp-pv-submissions',
            __( 'Ajustes', 'agrocampo-post-venta' ),
            __( 'Ajustes', 'agrocampo-post-venta' ),
            'manage_options',
            'agp-pv-settings',
            array( $this, 'render_settings_page' )
        );
    }

    private function get_manager_page_from_request(): string {
        $page = isset( $_REQUEST['manager_page'] ) ? sanitize_key( wp_unslash( $_REQUEST['manager_page'] ) ) : '';
        if ( '' === $page && isset( $_REQUEST['page'] ) ) {
            $page = sanitize_key( wp_unslash( $_REQUEST['page'] ) );
        }

        if ( in_array( $page, array( 'agp-pv-submissions', 'agp-pv-observations' ), true ) ) {
            return $page;
        }

        return 'agp-pv-submissions';
    }

    private function get_safe_redirect_url( string $fallback_url ): string {
        $redirect_to = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( (string) $_REQUEST['redirect_to'] ) : '';
        if ( '' === $redirect_to ) {
            return $fallback_url;
        }

        $redirect_to = esc_url_raw( $redirect_to );
        if ( '' === $redirect_to ) {
            return $fallback_url;
        }

        $validated = wp_validate_redirect( $redirect_to, '' );

        return '' !== $validated ? $validated : $fallback_url;
    }

    private function action_redirect_url( string $manager_page, string $notice, string $notice_message = '' ): string {
        $fallback_url = admin_url( 'admin.php?page=' . $manager_page );
        $base_url     = $this->get_safe_redirect_url( $fallback_url );

        $args = array(
            'agp_pv_notice' => $notice,
        );

        if ( '' !== $notice_message ) {
            $args['agp_pv_notice_message'] = $notice_message;
        }

        return add_query_arg( $args, $base_url );
    }

    public function render_list_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $table = new AGP_PV_Submissions_Table(
            array(
                'manager_page' => 'agp-pv-submissions',
            )
        );
        $table->prepare_items();

        echo '<div class="wrap agp-pv-admin">';
        echo '<h1>' . esc_html__( 'Gestor de informes técnicos', 'agrocampo-post-venta' ) . '</h1>';
        echo '<p class="description">' . esc_html__( 'Busca, filtra y ejecuta acciones sobre los informes enviados.', 'agrocampo-post-venta' ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( AGP_PV_Plugin::reports_standalone_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Abrir gestor frontend de informes', 'agrocampo-post-venta' ) . '</a></p>';
        echo '<p><a class="button button-secondary" href="' . esc_url( AGP_PV_Plugin::observations_standalone_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Abrir gestor frontend de observaciones', 'agrocampo-post-venta' ) . '</a></p>';

        $this->render_admin_notice();

        echo '<section class="agp-pv-bulk-pdf card">';
        echo '<h2>' . esc_html__( 'Regeneración masiva de PDF', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Regenera los PDF de todos los informes históricos en lotes para evitar timeouts.', 'agrocampo-post-venta' ) . '</p>';
        echo '<p><button type="button" class="button button-primary" id="agp-pv-regenerate-all-pdf">' . esc_html__( 'Regenerar todos los PDF', 'agrocampo-post-venta' ) . '</button></p>';
        echo '<div id="agp-pv-regenerate-progress" class="agp-pv-regenerate-progress" hidden>';
        echo '<progress id="agp-pv-regenerate-progress-bar" max="100" value="0"></progress>';
        echo '<p id="agp-pv-regenerate-progress-text" aria-live="polite"></p>';
        echo '</div>';
        echo '</section>';

        echo '<form method="get" class="agp-pv-filters">';
        echo '<input type="hidden" name="page" value="agp-pv-submissions">';
        $table->render_filters();
        $table->search_box( __( 'Buscar en informes', 'agrocampo-post-venta' ), 'agp-pv-search' );
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    public function render_observations_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $table = new AGP_PV_Submissions_Table(
            array(
                'manager_page' => 'agp-pv-observations',
                'observations_only' => true,
            )
        );
        $table->prepare_items();

        echo '<div class="wrap agp-pv-admin agp-pv-admin-observations">';
        echo '<section class="agp-pv-admin-hero agp-pv-admin-hero--reduced">';
        echo '<div class="agp-pv-admin-hero__content">';
        echo '<span class="agp-pv-admin-hero__eyebrow">' . esc_html__( 'Respaldo admin', 'agrocampo-post-venta' ) . '</span>';
        echo '<h1>' . esc_html__( 'Observaciones · panel interno de respaldo', 'agrocampo-post-venta' ) . '</h1>';
        echo '<p class="description">' . esc_html__( 'Usa esta vista para soporte, control y exportación. Para la operación diaria, prioriza el gestor visual sin theme.', 'agrocampo-post-venta' ) . '</p>';
        echo '<p class="description">' . sprintf( esc_html__( 'Rango global activo: últimos %d días según fecha de creación.', 'agrocampo-post-venta' ), AGP_PV_Plugin::get_observations_report_window_days() ) . '</p>';
        echo '</div>';
        echo '<div class="agp-pv-admin-hero__actions">';
        echo '<a class="button button-secondary agp-pv-button-open-standalone" href="' . esc_url( AGP_PV_Plugin::observations_standalone_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Abrir gestor visual principal', 'agrocampo-post-venta' ) . '</a>';
        echo '<p class="description agp-pv-admin-hero__hint">' . esc_html__( 'Deja este admin como fallback técnico cuando necesites revisar o exportar desde backend.', 'agrocampo-post-venta' ) . '</p>';
        echo '</div>';
        echo '</section>';

        echo '<section class="agp-pv-admin-scope" aria-label="' . esc_attr__( 'Uso recomendado del módulo', 'agrocampo-post-venta' ) . '">';
        echo '<article class="agp-pv-admin-scope__item is-recommended">';
        echo '<span class="agp-pv-admin-scope__eyebrow">' . esc_html__( 'Ruta recomendada', 'agrocampo-post-venta' ) . '</span>';
        echo '<h2>' . esc_html__( 'Gestión diaria desde el frontend visual', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Filtrar, revisar y trabajar observaciones día a día desde la vista standalone.', 'agrocampo-post-venta' ) . '</p>';
        echo '</article>';
        echo '<article class="agp-pv-admin-scope__item">';
        echo '<span class="agp-pv-admin-scope__eyebrow">' . esc_html__( 'Este admin', 'agrocampo-post-venta' ) . '</span>';
        echo '<h2>' . esc_html__( 'Soporte, fallback y reporte', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Mantén aquí las tareas internas de respaldo, exportación y revisión técnica.', 'agrocampo-post-venta' ) . '</p>';
        echo '</article>';
        echo '</section>';

        $this->render_admin_notice();

        echo '<form method="get" class="agp-pv-filters agp-pv-filters--observations">';
        echo '<input type="hidden" name="page" value="agp-pv-observations">';
        $table->render_filters();
        $table->search_box( __( 'Buscar en informes', 'agrocampo-post-venta' ), 'agp-pv-observations-search' );
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $logo_id = absint( get_option( 'agp_pv_logo_attachment_id', 0 ) );
        $logo_width = (float) get_option( 'agp_pv_logo_width_mm', 38 );
        $logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';
        $app_icon_192_id = absint( get_option( 'agp_pv_app_icon_192_attachment_id', 0 ) );
        $app_icon_512_id = absint( get_option( 'agp_pv_app_icon_512_attachment_id', 0 ) );
        $app_icon_192_url = $app_icon_192_id ? wp_get_attachment_url( $app_icon_192_id ) : '';
        $app_icon_512_url = $app_icon_512_id ? wp_get_attachment_url( $app_icon_512_id ) : '';
        $pdf_header_title = (string) get_option( 'agp_pv_pdf_header_title', __( 'INFORME TÉCNICO', 'agrocampo-post-venta' ) );
        $pdf_footer_text = (string) get_option( 'agp_pv_pdf_footer_text', __( 'Talca • Linares • Parral   |   +56 9 9748 5650', 'agrocampo-post-venta' ) );
        $pdf_it_label_template = (string) get_option( 'agp_pv_pdf_it_label_template', __( 'IT: %d', 'agrocampo-post-venta' ) );
        $machine_status_position = AGP_PV_Plugin::get_machine_status_field_position();
        $recipients = AGP_PV_Email::get_configured_recipients();
        $recipients_value = implode( ', ', $recipients );
        $technicians_value = implode( "\n", AGP_PV_Plugin::get_technicians() );

        echo '<div class="wrap agp-pv-admin">';
        echo '<h1>' . esc_html__( 'Ajustes Informe Técnico', 'agrocampo-post-venta' ) . '</h1>';
        echo '<p class="description">' . esc_html__( 'Configura envío de correos, logo PDF y accesos rápidos.', 'agrocampo-post-venta' ) . '</p>';

        $this->render_admin_notice();

        echo '<div class="agp-pv-settings-grid">';

        echo '<section class="card agp-pv-card">';
        echo '<h2>' . esc_html__( 'Acceso rápido', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Abre el formulario público de Informe Técnico en una nueva pestaña.', 'agrocampo-post-venta' ) . '</p>';
        echo '<p><a class="button button-primary" target="_blank" rel="noopener noreferrer" href="' . esc_url( home_url( '/post-venta/' ) ) . '">' . esc_html__( 'Abrir Informe Técnico', 'agrocampo-post-venta' ) . '</a></p>';
        echo '</section>';

        echo '<section class="card agp-pv-card">';
        echo '<h2>' . esc_html__( 'Branding del PDF', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Configura logo y textos de cabecera/pie del PDF generado.', 'agrocampo-post-venta' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="agp_pv_save_logo">';
        wp_nonce_field( 'agp_pv_save_logo' );
        echo '<input type="hidden" name="agp_pv_logo_attachment_id" id="agp-pv-logo-attachment-id" value="' . esc_attr( (string) $logo_id ) . '">';
        echo '<p><img id="agp-pv-logo-preview" src="' . esc_url( $logo_url ) . '" style="max-width:200px;display:' . ( $logo_url ? 'block' : 'none' ) . ';" alt=""></p>';
        echo '<p>';
        echo '<button type="button" class="button button-primary" id="agp-pv-logo-select">' . esc_html__( 'Seleccionar logo', 'agrocampo-post-venta' ) . '</button> ';
        echo '<button type="button" class="button button-secondary" id="agp-pv-logo-remove">' . esc_html__( 'Quitar logo', 'agrocampo-post-venta' ) . '</button>';
        echo '</p>';
        echo '<p><label for="agp-pv-logo-width"><strong>' . esc_html__( 'Ancho máximo (mm)', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<input type="number" step="0.1" min="10" max="80" id="agp-pv-logo-width" name="agp_pv_logo_width_mm" value="' . esc_attr( (string) $logo_width ) . '"></p>';

        echo '<p><label for="agp-pv-pdf-header-title"><strong>' . esc_html__( 'Título cabecera PDF', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<input type="text" class="regular-text" maxlength="70" id="agp-pv-pdf-header-title" name="agp_pv_pdf_header_title" value="' . esc_attr( $pdf_header_title ) . '"></p>';

        echo '<p><label for="agp-pv-pdf-it-label"><strong>' . esc_html__( 'Plantilla etiqueta IT', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<input type="text" class="regular-text" maxlength="40" id="agp-pv-pdf-it-label" name="agp_pv_pdf_it_label_template" value="' . esc_attr( $pdf_it_label_template ) . '"><br>';
        echo '<span class="description">' . esc_html__( 'Usa %d para el número de informe (ej: IT: %d).', 'agrocampo-post-venta' ) . '</span></p>';

        echo '<p><label for="agp-pv-pdf-footer-text"><strong>' . esc_html__( 'Texto pie de página PDF', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<input type="text" class="large-text" maxlength="110" id="agp-pv-pdf-footer-text" name="agp_pv_pdf_footer_text" value="' . esc_attr( $pdf_footer_text ) . '"></p>';

        echo '<h3>' . esc_html__( 'Paso Detalle', 'agrocampo-post-venta' ) . '</h3>';
        echo '<p class="description">' . esc_html__( 'Define dónde se muestra el bloque Estado de la máquina dentro del paso Detalle.', 'agrocampo-post-venta' ) . '</p>';
        echo '<p><label for="agp-pv-machine-status-position"><strong>' . esc_html__( 'Ubicación del bloque Estado de la máquina', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<select id="agp-pv-machine-status-position" name="agp_pv_machine_status_position">';
        echo '<option value="top" ' . selected( $machine_status_position, 'top', false ) . '>' . esc_html__( 'Arriba del paso Detalle', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="before_observaciones" ' . selected( $machine_status_position, 'before_observaciones', false ) . '>' . esc_html__( 'Antes de Observaciones', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="bottom" ' . selected( $machine_status_position, 'bottom', false ) . '>' . esc_html__( 'Al final del paso Detalle', 'agrocampo-post-venta' ) . '</option>';
        echo '</select></p>';

        echo '<h3>' . esc_html__( 'Iconos app (PWA)', 'agrocampo-post-venta' ) . '</h3>';
        echo '<p class="description">' . esc_html__( 'Configura iconos desde la biblioteca de medios para el manifiesto de instalación.', 'agrocampo-post-venta' ) . '</p>';

        echo '<input type="hidden" name="agp_pv_app_icon_192_attachment_id" id="agp-pv-app-icon-192-id" value="' . esc_attr( (string) $app_icon_192_id ) . '">';
        echo '<p><strong>' . esc_html__( 'Icono 192x192', 'agrocampo-post-venta' ) . '</strong></p>';
        echo '<p><img id="agp-pv-app-icon-192-preview" src="' . esc_url( $app_icon_192_url ) . '" style="max-width:96px;display:' . ( $app_icon_192_url ? 'block' : 'none' ) . ';" alt=""></p>';
        echo '<p>';
        echo '<button type="button" class="button button-secondary" id="agp-pv-app-icon-192-select">' . esc_html__( 'Seleccionar icono 192x192', 'agrocampo-post-venta' ) . '</button> ';
        echo '<button type="button" class="button button-secondary" id="agp-pv-app-icon-192-remove">' . esc_html__( 'Quitar icono 192x192', 'agrocampo-post-venta' ) . '</button>';
        echo '</p>';

        echo '<input type="hidden" name="agp_pv_app_icon_512_attachment_id" id="agp-pv-app-icon-512-id" value="' . esc_attr( (string) $app_icon_512_id ) . '">';
        echo '<p><strong>' . esc_html__( 'Icono 512x512', 'agrocampo-post-venta' ) . '</strong></p>';
        echo '<p><img id="agp-pv-app-icon-512-preview" src="' . esc_url( $app_icon_512_url ) . '" style="max-width:128px;display:' . ( $app_icon_512_url ? 'block' : 'none' ) . ';" alt=""></p>';
        echo '<p>';
        echo '<button type="button" class="button button-secondary" id="agp-pv-app-icon-512-select">' . esc_html__( 'Seleccionar icono 512x512', 'agrocampo-post-venta' ) . '</button> ';
        echo '<button type="button" class="button button-secondary" id="agp-pv-app-icon-512-remove">' . esc_html__( 'Quitar icono 512x512', 'agrocampo-post-venta' ) . '</button>';
        echo '</p>';
        echo '<p class="description">' . esc_html__( 'Tip: usa PNG/JPG desde medios. Esto evita mantener binarios en el repositorio del plugin.', 'agrocampo-post-venta' ) . '</p>';

        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Guardar branding PDF', 'agrocampo-post-venta' ) . '</button></p>';
        echo '</form>';
        echo '</section>';

        echo '<section class="card agp-pv-card">';
        echo '<h2>' . esc_html__( 'Destinatarios de correo', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Define los correos que recibirán el informe. Sepáralos por coma.', 'agrocampo-post-venta' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="agp_pv_save_recipients">';
        wp_nonce_field( 'agp_pv_save_recipients' );
        echo '<textarea name="agp_pv_recipients" rows="4" class="large-text">' . esc_textarea( $recipients_value ) . '</textarea>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Guardar destinatarios', 'agrocampo-post-venta' ) . '</button></p>';
        echo '</form>';

        echo '</section>';

        $notification_settings = AGP_PV_Email::get_notification_settings();
        $report_window_days = AGP_PV_Plugin::get_observations_report_window_days();
        $notification_tokens = AGP_PV_Email::get_notification_template_tokens();

        echo '<section class="card agp-pv-card agp-pv-notifications-card">';
        echo '<h2>' . esc_html__( 'Notificaciones e informes con observaciones', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Configura avisos automáticos solo para admin y el rango global de informes con observaciones.', 'agrocampo-post-venta' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="agp_pv_save_notifications_settings">';
        wp_nonce_field( 'agp_pv_save_notifications_settings' );

        echo '<div class="agp-pv-settings-stack">';
        echo '<p><label for="agp-pv-notify-admin-email"><strong>' . esc_html__( 'Correo admin para notificaciones', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<input type="email" class="regular-text" id="agp-pv-notify-admin-email" name="agp_pv_notifications_admin_email" value="' . esc_attr( (string) $notification_settings['admin_email'] ) . '" placeholder="admin@dominio.cl"></p>';

        echo '<div class="agp-pv-checkbox-grid">';
        echo '<label><input type="checkbox" name="agp_pv_notifications_enable_overdue" value="1" ' . checked( ! empty( $notification_settings['enable_overdue'] ), true, false ) . '> ' . esc_html__( 'Avisar por observaciones vencidas', 'agrocampo-post-venta' ) . '</label>';
        echo '<label><input type="checkbox" name="agp_pv_notifications_enable_daily_summary" value="1" ' . checked( ! empty( $notification_settings['enable_daily_summary'] ), true, false ) . '> ' . esc_html__( 'Enviar resumen diario', 'agrocampo-post-venta' ) . '</label>';
        echo '<label><input type="checkbox" name="agp_pv_notifications_enable_weekly_summary" value="1" ' . checked( ! empty( $notification_settings['enable_weekly_summary'] ), true, false ) . '> ' . esc_html__( 'Enviar resumen semanal', 'agrocampo-post-venta' ) . '</label>';
        echo '</div>';

        echo '<p><label for="agp-pv-notify-overdue-days"><strong>' . esc_html__( 'Considerar vencida desde X días', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<input type="number" min="1" max="3650" id="agp-pv-notify-overdue-days" name="agp_pv_notifications_overdue_days" value="' . esc_attr( (string) $notification_settings['overdue_days'] ) . '"><br>';
        echo '<span class="description">' . esc_html__( 'Como hoy no existe una fecha límite dedicada, el plugin considera vencida una observación pendiente cuando supera X días desde su creación.', 'agrocampo-post-venta' ) . '</span></p>';

        echo '<p><label for="agp-pv-observations-report-window"><strong>' . esc_html__( 'Mostrar informes con observaciones de los últimos X días', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<input type="number" min="1" max="3650" id="agp-pv-observations-report-window" name="agp_pv_observations_report_window_days" value="' . esc_attr( (string) $report_window_days ) . '"><br>';
        echo '<span class="description">' . esc_html__( 'Se aplica al gestor admin, la vista standalone de observaciones y la exportación desde la vista filtrada.', 'agrocampo-post-venta' ) . '</span></p>';

        echo '<p class="description">' . esc_html__( 'Horarios automáticos: resumen diario a las 09:00 y resumen semanal los lunes a las 09:00 mediante WP-Cron.', 'agrocampo-post-venta' ) . '</p>';

        echo '<p><label for="agp-pv-overdue-subject"><strong>' . esc_html__( 'Asunto correo vencimiento', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<input type="text" class="large-text" id="agp-pv-overdue-subject" name="agp_pv_notifications_overdue_subject" value="' . esc_attr( (string) $notification_settings['overdue_subject'] ) . '"></p>';

        echo '<p><label for="agp-pv-overdue-body"><strong>' . esc_html__( 'Plantilla correo vencimiento', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<textarea class="large-text code" rows="8" id="agp-pv-overdue-body" name="agp_pv_notifications_overdue_body">' . esc_textarea( (string) $notification_settings['overdue_body'] ) . '</textarea><br>';
        echo '<span class="description">' . esc_html__( 'Variables disponibles: ', 'agrocampo-post-venta' ) . esc_html( implode( ', ', $notification_tokens['overdue'] ) ) . '</span></p>';

        echo '<p><label for="agp-pv-summary-subject"><strong>' . esc_html__( 'Asunto resumen automático', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<input type="text" class="large-text" id="agp-pv-summary-subject" name="agp_pv_notifications_summary_subject" value="' . esc_attr( (string) $notification_settings['summary_subject'] ) . '"></p>';

        echo '<p><label for="agp-pv-summary-body"><strong>' . esc_html__( 'Plantilla resumen automático', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<textarea class="large-text code" rows="8" id="agp-pv-summary-body" name="agp_pv_notifications_summary_body">' . esc_textarea( (string) $notification_settings['summary_body'] ) . '</textarea><br>';
        echo '<span class="description">' . esc_html__( 'Variables disponibles: ', 'agrocampo-post-venta' ) . esc_html( implode( ', ', $notification_tokens['summary'] ) ) . '</span></p>';

        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Guardar notificaciones e informes', 'agrocampo-post-venta' ) . '</button></p>';
        echo '</div>';
        echo '</form>';

        echo '<div class="agp-pv-notification-tools">';
        echo '<div class="agp-pv-inline-form">';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="agp_pv_send_overdue_test_email">';
        wp_nonce_field( 'agp_pv_send_overdue_test_email' );
        echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Probar correo de vencimiento', 'agrocampo-post-venta' ) . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<div class="agp-pv-inline-form">';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="agp_pv_send_summary_now">';
        wp_nonce_field( 'agp_pv_send_summary_now' );
        echo '<label for="agp-pv-summary-period-now" class="screen-reader-text">' . esc_html__( 'Tipo de resumen', 'agrocampo-post-venta' ) . '</label>';
        echo '<select id="agp-pv-summary-period-now" name="summary_period">';
        echo '<option value="daily">' . esc_html__( 'Diario', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="weekly">' . esc_html__( 'Semanal', 'agrocampo-post-venta' ) . '</option>';
        echo '</select>';
        echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Enviar resumen ahora', 'agrocampo-post-venta' ) . '</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '<p class="description">' . esc_html__( 'Estas acciones manuales envían correos inmediatos al admin configurado, sin esperar al próximo cron.', 'agrocampo-post-venta' ) . '</p>';
        echo '</section>';

        echo '<section class="card agp-pv-card">';
        echo '<h2>' . esc_html__( 'Técnicos', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Gestiona los técnicos disponibles en el selector del formulario (uno por línea).', 'agrocampo-post-venta' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="agp_pv_save_technicians">';
        wp_nonce_field( 'agp_pv_save_technicians' );
        echo '<textarea name="agp_pv_technicians" rows="8" class="large-text code" placeholder="Nombre Apellido">' . esc_textarea( $technicians_value ) . '</textarea>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Guardar técnicos', 'agrocampo-post-venta' ) . '</button></p>';
        echo '</form>';
        echo '</section>';

        echo '<section class="card agp-pv-card">';
        echo '<h2>' . esc_html__( 'Prueba de correo', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Si tu hosting no tiene habilitada la función mail(), configura SMTP con un plugin como WP Mail SMTP.', 'agrocampo-post-venta' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="agp_pv_send_test_email">';
        wp_nonce_field( 'agp_pv_send_test_email' );
        echo '<p><label for="agp-pv-test-email"><strong>' . esc_html__( 'Enviar correo de prueba a', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<input type="email" id="agp-pv-test-email" name="test_email" class="regular-text" required></p>';
        echo '<p><button type="submit" class="button button-secondary">' . esc_html__( 'Enviar correo de prueba', 'agrocampo-post-venta' ) . '</button></p>';
        echo '</form>';
        echo '</section>';

        echo '<section class="card agp-pv-card">';
        echo '<h2>' . esc_html__( 'Importador de informes', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Importa informes históricos exportados desde Forminator del plugin anterior (CSV).', 'agrocampo-post-venta' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="agp_pv_import_legacy_csv">';
        wp_nonce_field( 'agp_pv_import_legacy_csv' );
        echo '<p><label for="agp-pv-legacy-csv"><strong>' . esc_html__( 'Archivo CSV', 'agrocampo-post-venta' ) . '</strong></label><br>';
        echo '<input type="file" id="agp-pv-legacy-csv" name="agp_pv_legacy_csv" accept=".csv,text/csv" required></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Importar informes', 'agrocampo-post-venta' ) . '</button></p>';
        echo '</form>';
        echo '<p><a class="button button-secondary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=agp_pv_export_legacy_csv' ), 'agp_pv_export_legacy_csv' ) ) . '">' . esc_html__( 'Exportar informes (CSV compatible)', 'agrocampo-post-venta' ) . '</a></p>';
        echo '</section>';


        echo '<section class="card agp-pv-card">';
        echo '<h2>' . esc_html__( 'Zona peligrosa', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Elimina permanentemente todos los informes almacenados en este plugin.', 'agrocampo-post-venta' ) . '</p>';
        echo "<form method=\"post\" action=\"" . esc_url( admin_url( 'admin-post.php' ) ) . "\" onsubmit=\"return confirm('" . esc_js( __( '¿Estás seguro? Esta acción eliminará todos los informes y no se puede deshacer.', 'agrocampo-post-venta' ) ) . "');\">";
        echo '<input type="hidden" name="action" value="agp_pv_delete_all_submissions">';
        wp_nonce_field( 'agp_pv_delete_all_submissions' );
        echo '<p><button type="submit" class="button button-secondary">' . esc_html__( 'Eliminar todos los informes', 'agrocampo-post-venta' ) . '</button></p>';
        echo '</form>';
        echo '</section>';

        echo '</div>';
        echo '</div>';
    }

    private function render_admin_notice(): void {
        if ( ! isset( $_GET['agp_pv_notice'] ) ) {
            return;
        }

        $notice = sanitize_key( wp_unslash( $_GET['agp_pv_notice'] ) );
        $message = '';
        $class = 'notice-success';

        if ( 'mail_failed' === $notice || 'test_failed' === $notice || 'pdf_failed' === $notice ) {
            $message = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? '' ) );
            $class = 'notice-error';
        }

        if ( 'mail_sent' === $notice ) {
            $message = __( 'Correo reenviado.', 'agrocampo-post-venta' );
        }
        if ( 'pdf_regenerated' === $notice ) {
            $message = __( 'PDF regenerado correctamente.', 'agrocampo-post-venta' );
        }
        if ( 'test_sent' === $notice ) {
            $message = __( 'Correo de prueba enviado.', 'agrocampo-post-venta' );
        }
        if ( 'recipients_saved' === $notice ) {
            $message = __( 'Destinatarios actualizados.', 'agrocampo-post-venta' );
        }
        if ( 'technicians_saved' === $notice ) {
            $message = __( 'Técnicos actualizados.', 'agrocampo-post-venta' );
        }
        if ( 'notifications_saved' === $notice ) {
            $message = __( 'Notificaciones e informes actualizados.', 'agrocampo-post-venta' );
        }
        if ( 'summary_sent' === $notice ) {
            $period = sanitize_key( wp_unslash( $_GET['agp_pv_notice_period'] ?? 'daily' ) );
            $message = 'weekly' === $period ? __( 'Resumen semanal enviado manualmente.', 'agrocampo-post-venta' ) : __( 'Resumen diario enviado manualmente.', 'agrocampo-post-venta' );
        }
        if ( 'summary_failed' === $notice ) {
            $message = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? '' ) );
            $class = 'notice-error';
        }
        if ( 'overdue_test_sent' === $notice ) {
            $mode = sanitize_key( wp_unslash( $_GET['agp_pv_notice_mode'] ?? 'sample' ) );
            $message = 'real' === $mode ? __( 'Correo de vencimiento de prueba enviado usando una observación real.', 'agrocampo-post-venta' ) : __( 'Correo de vencimiento de prueba enviado con contenido de ejemplo.', 'agrocampo-post-venta' );
        }
        if ( 'overdue_test_failed' === $notice ) {
            $message = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? '' ) );
            $class = 'notice-error';
        }
        if ( 'logo_saved' === $notice ) {
            $message = __( 'Logo actualizado.', 'agrocampo-post-venta' );
        }
        if ( 'bulk_deleted' === $notice ) {
            $count = isset( $_GET['agp_pv_notice_count'] ) ? absint( $_GET['agp_pv_notice_count'] ) : 0;
            /* translators: %d: deleted items count */
            $message = sprintf( __( 'Se eliminaron %d informes.', 'agrocampo-post-venta' ), $count );
        }
        if ( 'bulk_resend' === $notice ) {
            $count = isset( $_GET['agp_pv_notice_count'] ) ? absint( $_GET['agp_pv_notice_count'] ) : 0;
            /* translators: %d: processed items count */
            $message = sprintf( __( 'Reenvío ejecutado en %d informes.', 'agrocampo-post-venta' ), $count );
        }
        if ( 'bulk_regenerated' === $notice ) {
            $count = isset( $_GET['agp_pv_notice_count'] ) ? absint( $_GET['agp_pv_notice_count'] ) : 0;
            /* translators: %d: processed items count */
            $message = sprintf( __( 'PDF regenerado en %d informes.', 'agrocampo-post-venta' ), $count );
        }
        if ( 'delete_all_completed' === $notice ) {
            $count = isset( $_GET['agp_pv_notice_count'] ) ? absint( $_GET['agp_pv_notice_count'] ) : 0;
            /* translators: %d: deleted items count */
            $message = sprintf( __( 'Se eliminaron todos los informes. Total eliminado: %d.', 'agrocampo-post-venta' ), $count );
        }
        if ( 'delete_all_failed' === $notice ) {
            $message = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? '' ) );
            $class = 'notice-error';
        }
        if ( 'import_invalid_file' === $notice || 'import_failed' === $notice ) {
            $message = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? '' ) );
            $class = 'notice-error';
        }
        if ( 'import_completed' === $notice ) {
            $imported = isset( $_GET['agp_pv_imported'] ) ? absint( $_GET['agp_pv_imported'] ) : 0;
            $skipped = isset( $_GET['agp_pv_skipped'] ) ? absint( $_GET['agp_pv_skipped'] ) : 0;
            $failed = isset( $_GET['agp_pv_failed'] ) ? absint( $_GET['agp_pv_failed'] ) : 0;
            /* translators: 1: imported rows, 2: skipped rows, 3: failed rows */
            $message = sprintf( __( 'Importación completada. Importados: %1$d. Omitidos: %2$d. Fallidos: %3$d.', 'agrocampo-post-venta' ), $imported, $skipped, $failed );

            if ( $failed > 0 ) {
                $class = 'notice-warning';
            }
        }

        if ( 'review_marked' === $notice ) {
            $message = __( 'Informe marcado como revisado.', 'agrocampo-post-venta' );
        }
        if ( 'review_failed' === $notice ) {
            $message = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? '' ) );
            $class = 'notice-error';
        }
        if ( 'observation_appended' === $notice ) {
            $message = __( 'Observación agregada correctamente al informe revisado.', 'agrocampo-post-venta' );
        }
        if ( 'observation_append_failed' === $notice ) {
            $message = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? __( 'No se pudo agregar la observación.', 'agrocampo-post-venta' ) ) );
            $class   = 'notice-error';
        }
        if ( 'export_observations_failed' === $notice ) {
            $message = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? __( 'No se pudo generar el reporte XLSX.', 'agrocampo-post-venta' ) ) );
            $class   = 'notice-error';
        }

        if ( $message ) {
            echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>' . esc_html( $message ) . '</strong></p></div>';
        }
    }




    public function handle_export_observations_report(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';
        if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'agp_pv_export_observations_report' ) ) {
            $this->redirect_observations_export_error( __( 'El enlace de exportación expiró o no es válido.', 'agrocampo-post-venta' ) );
        }

        $source = $this->get_observations_export_source();

        if ( 'standalone' === $source ) {
            $filters = $this->get_standalone_observations_export_filters_from_request();
            $items   = $this->get_standalone_observations_export_items( $filters );
        } else {
            $table = new AGP_PV_Submissions_Table(
                array(
                    'manager_page'      => 'agp-pv-observations',
                    'observations_only' => true,
                )
            );

            global $wpdb;

            $filters    = $table->get_filters_from_request();
            $where_data = $table->build_where_clause( $filters );
            $order_by   = $table->get_order_by();
            $order      = $table->get_order_direction();
            $db_table   = AGP_PV_DB::table_name();

            $items_sql = "SELECT * FROM {$db_table} {$where_data['sql']} ORDER BY {$order_by} {$order}";
            $items     = empty( $where_data['values'] )
                ? $wpdb->get_results( $items_sql, ARRAY_A )
                : $wpdb->get_results( $wpdb->prepare( $items_sql, $where_data['values'] ), ARRAY_A );
        }

        if ( ! is_array( $items ) ) {
            $this->redirect_observations_export_error( __( 'No se pudo leer la vista filtrada para exportar.', 'agrocampo-post-venta' ) );
        }

        $generated_at = wp_date( 'd/m/Y H:i' );
        $filename     = 'observaciones-' . wp_date( 'Y-m-d-H-i' ) . '.xlsx';
        $temp_file    = $this->create_temp_file_path( 'agp-pv-observaciones-', '.xlsx' );

        if ( is_wp_error( $temp_file ) ) {
            $this->redirect_observations_export_error( $temp_file->get_error_message() );
        }

        $write_result = $this->write_observations_report_xlsx( $temp_file, $items, $filters, $generated_at );
        if ( is_wp_error( $write_result ) ) {
            if ( file_exists( $temp_file ) ) {
                @unlink( $temp_file );
            }
            $this->redirect_observations_export_error( $write_result->get_error_message() );
        }

        while ( ob_get_level() ) {
            ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'Content-Transfer-Encoding: binary' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' . (string) filesize( $temp_file ) );
        readfile( $temp_file );
        @unlink( $temp_file );
        exit;
    }

    private function get_observations_export_source(): string {
        $source = isset( $_GET['export_source'] ) ? sanitize_key( wp_unslash( (string) $_GET['export_source'] ) ) : '';

        return in_array( $source, array( 'admin', 'standalone' ), true ) ? $source : 'admin';
    }

    private function get_standalone_observations_export_filters_from_request(): array {
        $sanitize_date = static function ( $value ): string {
            $value = is_string( $value ) ? trim( $value ) : '';

            if ( '' === $value || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
                return '';
            }

            $date = DateTimeImmutable::createFromFormat( 'Y-m-d', $value );

            return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
        };

        $default_days = AGP_PV_Plugin::get_observations_report_window_days();
        $days         = isset( $_GET['days'] ) ? absint( wp_unslash( $_GET['days'] ) ) : $default_days;
        if ( $days < 1 ) {
            $days = $default_days;
        }

        return array(
            'search'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'review_status' => isset( $_GET['review_status'] ) ? AGP_PV_Plugin::normalize_observation_review_filter( (string) wp_unslash( $_GET['review_status'] ) ) : '',
            'days'          => min( 3650, $days ),
            'from'          => isset( $_GET['from'] ) ? $sanitize_date( wp_unslash( $_GET['from'] ) ) : '',
            'to'            => isset( $_GET['to'] ) ? $sanitize_date( wp_unslash( $_GET['to'] ) ) : '',
            'export_source' => 'standalone',
        );
    }

    private function get_standalone_observations_export_items( array $filters ): ?array {
        global $wpdb;

        $table_name     = AGP_PV_DB::table_name();
        $search         = (string) ( $filters['search'] ?? '' );
        $review_status  = (string) ( $filters['review_status'] ?? '' );
        $days           = max( 1, min( 3650, (int) ( $filters['days'] ?? AGP_PV_Plugin::get_observations_report_window_days() ) ) );
        $date_from      = (string) ( $filters['from'] ?? '' );
        $date_to        = (string) ( $filters['to'] ?? '' );
        $range_active   = '' !== $date_from || '' !== $date_to;
        $overdue_cutoff = AGP_PV_Plugin::get_observations_overdue_cutoff_mysql();

        $build_period_clauses = static function ( string $column ) use ( $range_active, $date_from, $date_to, $days ): array {
            if ( $range_active ) {
                $clauses = array();
                $values  = array();

                if ( '' !== $date_from ) {
                    $clauses[] = "{$column} >= %s";
                    $values[]  = $date_from . ' 00:00:00';
                }

                if ( '' !== $date_to ) {
                    $clauses[] = "{$column} <= %s";
                    $values[]  = $date_to . ' 23:59:59';
                }

                return array( $clauses, $values );
            }

            return array(
                array( "{$column} >= %s" ),
                array( current_datetime()->modify( '-' . $days . ' days' )->format( 'Y-m-d H:i:s' ) ),
            );
        };

        list( $created_period_clauses, $created_period_values )   = $build_period_clauses( 'created_at' );
        list( $reviewed_period_clauses, $reviewed_period_values ) = $build_period_clauses( 'reviewed_at' );

        $created_period_sql  = implode( ' AND ', $created_period_clauses );
        $reviewed_period_sql = implode( ' AND ', $reviewed_period_clauses );

        $pending_sql        = AGP_PV_Plugin::effective_pending_observation_sql();
        $strict_pending_sql = AGP_PV_Plugin::strict_pending_observation_sql();
        $reviewed_sql       = AGP_PV_Plugin::reviewed_observation_sql();

        $all_period_sql      = "(({$pending_sql}) AND ({$created_period_sql})) OR (({$reviewed_sql}) AND ({$reviewed_period_sql}))";
        $pending_period_sql  = "({$pending_sql}) AND ({$created_period_sql})";
        $strict_period_sql   = "({$strict_pending_sql}) AND ({$created_period_sql})";
        $reviewed_filter_sql = "({$reviewed_sql}) AND ({$reviewed_period_sql})";
        $overdue_period_sql  = "({$pending_period_sql}) AND created_at <= %s";

        $common_clauses = AGP_PV_Plugin::observation_presence_sql_clauses();
        $common_values  = array();

        if ( '' !== $search ) {
            $like             = '%' . $wpdb->esc_like( $search ) . '%';
            $common_clauses[] = '(tecnico LIKE %s OR cliente LIKE %s OR observaciones LIKE %s OR serie LIKE %s)';
            array_push( $common_values, $like, $like, $like, $like );
        }

        $filtered_clauses = $common_clauses;
        $filtered_values  = $common_values;

        if ( 'not_reviewed' === $review_status ) {
            $filtered_clauses[] = $pending_period_sql;
            $filtered_values    = array_merge( $filtered_values, $created_period_values );
        } elseif ( 'strict_pending_review' === $review_status ) {
            $filtered_clauses[] = $strict_period_sql;
            $filtered_values    = array_merge( $filtered_values, $created_period_values );
        } elseif ( 'reviewed' === $review_status ) {
            $filtered_clauses[] = $reviewed_filter_sql;
            $filtered_values    = array_merge( $filtered_values, $reviewed_period_values );
        } elseif ( 'overdue_pending' === $review_status ) {
            $filtered_clauses[] = $overdue_period_sql;
            $filtered_values    = array_merge( $filtered_values, $created_period_values, array( $overdue_cutoff ) );
        } else {
            $filtered_clauses[] = $all_period_sql;
            $filtered_values    = array_merge( $filtered_values, $created_period_values, $reviewed_period_values );
        }

        $filtered_where = 'WHERE ' . implode( ' AND ', $filtered_clauses );
        $sql            = "SELECT * FROM {$table_name} {$filtered_where} ORDER BY (legacy_id > 0) DESC, legacy_id DESC, id DESC";

        return $wpdb->get_results( $wpdb->prepare( $sql, $filtered_values ), ARRAY_A );
    }

    private function redirect_observations_export_error( string $message ): void {
        $query_args = array(
            'agp_pv_notice'         => 'export_observations_failed',
            'agp_pv_notice_message' => $message,
        );

        if ( 'standalone' === $this->get_observations_export_source() ) {
            $passthrough = array( 's', 'review_status', 'days', 'from', 'to', 'pg' );

            foreach ( $passthrough as $key ) {
                if ( ! isset( $_GET[ $key ] ) ) {
                    continue;
                }

                $value = wp_unslash( (string) $_GET[ $key ] );
                if ( '' === $value ) {
                    continue;
                }

                $query_args[ $key ] = $value;
            }

            wp_safe_redirect( add_query_arg( $query_args, AGP_PV_Plugin::observations_standalone_url() ) );
            exit;
        }

        $query_args['page'] = 'agp-pv-observations';

        $passthrough = array( 's', 'mail_status', 'pdf_status', 'review_status', 'email', 'date_from', 'date_to', 'orderby', 'order' );
        foreach ( $passthrough as $key ) {
            if ( ! isset( $_GET[ $key ] ) ) {
                continue;
            }

            $value = wp_unslash( (string) $_GET[ $key ] );
            if ( '' === $value ) {
                continue;
            }

            $query_args[ $key ] = $value;
        }

        wp_safe_redirect( add_query_arg( $query_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function write_observations_report_xlsx( string $file_path, array $items, array $filters, string $generated_at ): true|WP_Error {
        $headers = array(
            __( 'ID', 'agrocampo-post-venta' ),
            __( 'Fecha', 'agrocampo-post-venta' ),
            __( 'Técnico', 'agrocampo-post-venta' ),
            __( 'Cliente', 'agrocampo-post-venta' ),
            __( 'Correo cliente', 'agrocampo-post-venta' ),
            __( 'Serie', 'agrocampo-post-venta' ),
            __( 'Observación', 'agrocampo-post-venta' ),
            __( 'Estado revisión', 'agrocampo-post-venta' ),
            __( 'Vencida', 'agrocampo-post-venta' ),
            __( 'Revisado por', 'agrocampo-post-venta' ),
            __( 'Fecha revisión', 'agrocampo-post-venta' ),
            __( 'Estado PDF', 'agrocampo-post-venta' ),
            __( 'Estado correo', 'agrocampo-post-venta' ),
        );

        $rows = array(
            array(
                array( 'value' => __( 'Reporte', 'agrocampo-post-venta' ), 'style' => 1 ),
                array( 'value' => __( 'Observaciones filtradas', 'agrocampo-post-venta' ), 'style' => 2 ),
            ),
            array(
                array( 'value' => __( 'Fecha exportación', 'agrocampo-post-venta' ), 'style' => 1 ),
                array( 'value' => $generated_at, 'style' => 2 ),
            ),
            array(
                array( 'value' => __( 'Filtros aplicados', 'agrocampo-post-venta' ), 'style' => 1 ),
                array( 'value' => $this->get_observations_export_filters_summary( $filters ), 'style' => 6 ),
            ),
            array(
                array( 'value' => __( 'Total registros', 'agrocampo-post-venta' ), 'style' => 1 ),
                array( 'value' => (string) count( $items ), 'style' => 2 ),
            ),
            array(),
        );

        $header_row_index = count( $rows ) + 1;
        $rows[] = array_map(
            static function ( string $header ): array {
                return array(
                    'value' => $header,
                    'style' => 3,
                );
            },
            $headers
        );

        foreach ( $items as $item ) {
            $rows[] = array(
                array( 'value' => (string) AGP_PV_DB::get_visible_report_id( $item ), 'style' => 4 ),
                array( 'value' => $this->format_export_datetime( (string) ( $item['created_at'] ?? '' ) ), 'style' => 4 ),
                array( 'value' => (string) ( $item['tecnico'] ?? '' ), 'style' => 4 ),
                array( 'value' => (string) ( $item['cliente'] ?? '' ), 'style' => 4 ),
                array( 'value' => (string) ( $item['email_cliente'] ?? '' ), 'style' => 4 ),
                array( 'value' => (string) ( $item['serie'] ?? '' ), 'style' => 4 ),
                array( 'value' => AGP_PV_Plugin::normalize_observation_text( (string) ( $item['observaciones'] ?? '' ) ), 'style' => 5 ),
                array( 'value' => $this->format_export_status( (string) ( $item['review_status'] ?? '' ), $item, 'review' ), 'style' => 4 ),
                array( 'value' => AGP_PV_Plugin::is_overdue_observation_row( $item ) ? __( 'Sí', 'agrocampo-post-venta' ) : __( 'No', 'agrocampo-post-venta' ), 'style' => 4 ),
                array( 'value' => $this->get_export_reviewed_by_label( $item ), 'style' => 4 ),
                array( 'value' => $this->format_export_datetime( (string) ( $item['reviewed_at'] ?? '' ) ), 'style' => 4 ),
                array( 'value' => $this->format_export_status( (string) ( $item['pdf_status'] ?? '' ), $item, 'pdf' ), 'style' => 4 ),
                array( 'value' => $this->format_export_status( (string) ( $item['mail_status'] ?? '' ), $item, 'mail' ), 'style' => 4 ),
            );
        }

        $sheet_xml = $this->build_xlsx_worksheet_xml(
            $rows,
            array( 12, 18, 20, 24, 28, 16, 70, 20, 12, 18, 18, 14, 16 ),
            $header_row_index,
            count( $headers )
        );

        $files = array(
            '[Content_Types].xml'             => $this->get_xlsx_content_types_xml(),
            '_rels/.rels'                     => $this->get_xlsx_root_rels_xml(),
            'docProps/app.xml'                => $this->get_xlsx_app_props_xml(),
            'docProps/core.xml'               => $this->get_xlsx_core_props_xml(),
            'xl/workbook.xml'                 => $this->get_xlsx_workbook_xml(),
            'xl/_rels/workbook.xml.rels'      => $this->get_xlsx_workbook_rels_xml(),
            'xl/styles.xml'                   => $this->get_xlsx_styles_xml(),
            'xl/worksheets/sheet1.xml'        => $sheet_xml,
        );

        return $this->create_zip_package( $file_path, $files );
    }

    private function get_observations_export_filters_summary( array $filters ): string {
        $parts  = array();
        $source = (string) ( $filters['export_source'] ?? 'admin' );

        if ( '' !== (string) ( $filters['search'] ?? '' ) ) {
            $parts[] = sprintf( __( 'Búsqueda: %s', 'agrocampo-post-venta' ), (string) $filters['search'] );
        }

        if ( '' !== (string) ( $filters['review_status'] ?? '' ) ) {
            $parts[] = sprintf( __( 'Revisión: %s', 'agrocampo-post-venta' ), $this->format_export_status( (string) $filters['review_status'], array( 'observaciones' => '1' ), 'review_filter' ) );
        }

        if ( 'standalone' === $source ) {
            $from = (string) ( $filters['from'] ?? '' );
            $to   = (string) ( $filters['to'] ?? '' );

            if ( '' !== $from && '' !== $to ) {
                $parts[] = sprintf( __( 'Período: %1$s a %2$s', 'agrocampo-post-venta' ), $this->format_export_date_only( $from ), $this->format_export_date_only( $to ) );
            } elseif ( '' !== $from ) {
                $parts[] = sprintf( __( 'Desde: %s', 'agrocampo-post-venta' ), $this->format_export_date_only( $from ) );
            } elseif ( '' !== $to ) {
                $parts[] = sprintf( __( 'Hasta: %s', 'agrocampo-post-venta' ), $this->format_export_date_only( $to ) );
            } else {
                $parts[] = sprintf( __( 'Período: últimos %d días', 'agrocampo-post-venta' ), max( 1, (int) ( $filters['days'] ?? AGP_PV_Plugin::get_observations_report_window_days() ) ) );
            }
        } else {
            if ( '' !== (string) ( $filters['mail_status'] ?? '' ) ) {
                $parts[] = sprintf( __( 'Correo: %s', 'agrocampo-post-venta' ), $this->format_export_status( (string) $filters['mail_status'], array(), 'mail' ) );
            }

            if ( '' !== (string) ( $filters['pdf_status'] ?? '' ) ) {
                $parts[] = sprintf( __( 'PDF: %s', 'agrocampo-post-venta' ), $this->format_export_status( (string) $filters['pdf_status'], array(), 'pdf' ) );
            }

            if ( '' !== (string) ( $filters['email'] ?? '' ) ) {
                $parts[] = sprintf( __( 'Correo contiene: %s', 'agrocampo-post-venta' ), (string) $filters['email'] );
            }

            $date_from = (string) ( $filters['date_from'] ?? '' );
            $date_to   = (string) ( $filters['date_to'] ?? '' );
            if ( '' !== $date_from && '' !== $date_to ) {
                $parts[] = sprintf( __( 'Período: %1$s a %2$s', 'agrocampo-post-venta' ), $this->format_export_date_only( $date_from ), $this->format_export_date_only( $date_to ) );
            } elseif ( '' !== $date_from ) {
                $parts[] = sprintf( __( 'Desde: %s', 'agrocampo-post-venta' ), $this->format_export_date_only( $date_from ) );
            } elseif ( '' !== $date_to ) {
                $parts[] = sprintf( __( 'Hasta: %s', 'agrocampo-post-venta' ), $this->format_export_date_only( $date_to ) );
            }

            $parts[] = sprintf( __( 'Ventana global: últimos %d días', 'agrocampo-post-venta' ), AGP_PV_Plugin::get_observations_report_window_days() );
        }

        return empty( $parts ) ? __( 'Sin filtros adicionales.', 'agrocampo-post-venta' ) : implode( ' · ', $parts );
    }

    private function get_export_reviewed_by_label( array $item ): string {
        $user_id = isset( $item['reviewed_by'] ) ? absint( $item['reviewed_by'] ) : 0;
        if ( $user_id <= 0 ) {
            return '—';
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return sprintf( '#%d', $user_id );
        }

        return (string) $user->display_name;
    }

    private function format_export_status( string $status, array $context = array(), string $type = '' ): string {
        if ( 'mail' === $type || 'pdf' === $type ) {
            if ( 'sent' === $status || 'ready' === $status ) {
                return __( 'Listo', 'agrocampo-post-venta' );
            }
            if ( 'failed' === $status ) {
                return __( 'Falló', 'agrocampo-post-venta' );
            }
            if ( 'pending' === $status ) {
                return __( 'Pendiente', 'agrocampo-post-venta' );
            }
        }

        if ( 'review_filter' === $type ) {
            if ( 'not_reviewed' === $status ) {
                return __( 'No revisado', 'agrocampo-post-venta' );
            }
            if ( 'strict_pending_review' === $status || 'pending_review' === $status ) {
                return __( 'Pendiente de revisión', 'agrocampo-post-venta' );
            }
            if ( 'reviewed' === $status ) {
                return __( 'Revisado', 'agrocampo-post-venta' );
            }
            if ( 'overdue_pending' === $status ) {
                return __( 'Pendiente vencida', 'agrocampo-post-venta' );
            }
        }

        if ( AGP_PV_Plugin::is_reviewed_observation_status( $status ) ) {
            return __( 'Revisado', 'agrocampo-post-venta' );
        }

        if ( ! empty( $context ) && AGP_PV_Plugin::has_meaningful_observation_text( (string) ( $context['observaciones'] ?? '' ) ) ) {
            if ( AGP_PV_Plugin::is_overdue_observation_row( $context ) ) {
                return __( 'Pendiente vencida', 'agrocampo-post-venta' );
            }

            return __( 'No revisado', 'agrocampo-post-venta' );
        }

        if ( 'pending_review' === $status || 'strict_pending_review' === $status ) {
            return __( 'Pendiente de revisión', 'agrocampo-post-venta' );
        }
        if ( 'not_required' === $status ) {
            return __( 'No requiere revisión', 'agrocampo-post-venta' );
        }

        return '' !== $status ? $status : '—';
    }

    private function format_export_datetime( string $value ): string {
        if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
            return '—';
        }

        $timestamp = strtotime( $value );
        if ( false === $timestamp ) {
            return $value;
        }

        return wp_date( 'd/m/Y H:i', $timestamp );
    }

    private function format_export_date_only( string $value ): string {
        if ( '' === $value ) {
            return '—';
        }

        $timestamp = strtotime( $value );
        if ( false === $timestamp ) {
            return $value;
        }

        return wp_date( 'd/m/Y', $timestamp );
    }

    private function create_temp_file_path( string $prefix, string $extension ): string|WP_Error {
        $base_dir = function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir();
        if ( ! is_string( $base_dir ) || '' === $base_dir ) {
            $base_dir = sys_get_temp_dir();
        }

        $temp_path = tempnam( $base_dir, $prefix );
        if ( false === $temp_path ) {
            return new WP_Error( 'agp_pv_export_tempnam_failed', __( 'No se pudo preparar un archivo temporal para la exportación.', 'agrocampo-post-venta' ) );
        }

        if ( '' !== $extension ) {
            $target_path = $temp_path . $extension;
            if ( ! @rename( $temp_path, $target_path ) ) {
                @unlink( $temp_path );
                return new WP_Error( 'agp_pv_export_tempfile_failed', __( 'No se pudo crear el archivo temporal del reporte.', 'agrocampo-post-venta' ) );
            }

            return $target_path;
        }

        return $temp_path;
    }

    private function create_zip_package( string $file_path, array $files ): true|WP_Error {
        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
                return new WP_Error( 'agp_pv_export_zip_open_failed', __( 'No se pudo abrir el paquete XLSX para escritura.', 'agrocampo-post-venta' ) );
            }

            foreach ( $files as $relative_path => $contents ) {
                $zip->addFromString( $relative_path, $contents );
            }

            $zip->close();
            return true;
        }

        if ( ! class_exists( 'PclZip' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
        }

        if ( ! class_exists( 'PclZip' ) ) {
            return new WP_Error( 'agp_pv_export_zip_missing', __( 'No se encontró un motor ZIP disponible para generar el XLSX.', 'agrocampo-post-venta' ) );
        }

        $temp_root = trailingslashit( dirname( $file_path ) ) . uniqid( 'agp-pv-xlsx-', true );
        if ( ! wp_mkdir_p( $temp_root ) ) {
            return new WP_Error( 'agp_pv_export_tempdir_failed', __( 'No se pudo preparar la carpeta temporal del XLSX.', 'agrocampo-post-venta' ) );
        }

        foreach ( $files as $relative_path => $contents ) {
            $target = $temp_root . '/' . $relative_path;
            $dir    = dirname( $target );
            if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
                $this->delete_directory_recursive( $temp_root );
                return new WP_Error( 'agp_pv_export_tempdir_write_failed', __( 'No se pudo construir la estructura temporal del XLSX.', 'agrocampo-post-venta' ) );
            }

            if ( false === file_put_contents( $target, $contents ) ) {
                $this->delete_directory_recursive( $temp_root );
                return new WP_Error( 'agp_pv_export_tempfile_write_failed', __( 'No se pudo escribir uno de los archivos internos del XLSX.', 'agrocampo-post-venta' ) );
            }
        }

        $archive = new PclZip( $file_path );
        $result  = $archive->create( $temp_root, PCLZIP_OPT_REMOVE_PATH, $temp_root );
        $this->delete_directory_recursive( $temp_root );

        if ( 0 === $result ) {
            return new WP_Error( 'agp_pv_export_pclzip_failed', (string) $archive->errorInfo( true ) );
        }

        return true;
    }

    private function delete_directory_recursive( string $dir ): void {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $items = scandir( $dir );
        if ( false === $items ) {
            return;
        }

        foreach ( $items as $item ) {
            if ( '.' === $item || '..' === $item ) {
                continue;
            }

            $path = $dir . '/' . $item;
            if ( is_dir( $path ) ) {
                $this->delete_directory_recursive( $path );
            } else {
                @unlink( $path );
            }
        }

        @rmdir( $dir );
    }

    private function build_xlsx_worksheet_xml( array $rows, array $column_widths, int $header_row_index, int $column_count ): string {
        $cols_xml      = '';
        $current_col   = 1;
        foreach ( $column_widths as $width ) {
            $cols_xml .= '<col min="' . $current_col . '" max="' . $current_col . '" width="' . $width . '" customWidth="1"/>';
            $current_col++;
        }

        $sheet_rows_xml = '';
        $row_index      = 1;
        foreach ( $rows as $row ) {
            if ( empty( $row ) ) {
                $sheet_rows_xml .= '<row r="' . $row_index . '"></row>';
                $row_index++;
                continue;
            }

            $cells_xml  = '';
            $col_index  = 1;
            foreach ( $row as $cell ) {
                $value       = isset( $cell['value'] ) ? (string) $cell['value'] : '';
                $style_index = isset( $cell['style'] ) ? (int) $cell['style'] : 0;
                $cell_ref    = $this->xlsx_column_letter( $col_index ) . $row_index;
                $cells_xml  .= '<c r="' . $cell_ref . '" t="inlineStr" s="' . $style_index . '"><is><t xml:space="preserve">' . $this->xlsx_escape( $value ) . '</t></is></c>';
                $col_index++;
            }

            $sheet_rows_xml .= '<row r="' . $row_index . '">' . $cells_xml . '</row>';
            $row_index++;
        }

        $last_row     = max( 1, count( $rows ) );
        $last_column  = $this->xlsx_column_letter( max( 1, $column_count ) );
        $auto_filter = 'A' . $header_row_index . ':' . $last_column . max( $header_row_index, $last_row );
        $dimension   = 'A1:' . $last_column . $last_row;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="' . $dimension . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $header_row_index . '" topLeftCell="A' . ( $header_row_index + 1 ) . '" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A' . ( $header_row_index + 1 ) . '" sqref="A' . ( $header_row_index + 1 ) . '"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols>' . $cols_xml . '</cols>'
            . '<sheetData>' . $sheet_rows_xml . '</sheetData>'
            . '<autoFilter ref="' . $auto_filter . '"/>'
            . '</worksheet>';
    }

    private function xlsx_column_letter( int $column_index ): string {
        $letter = '';
        while ( $column_index > 0 ) {
            $modulo       = ( $column_index - 1 ) % 26;
            $letter       = chr( 65 + $modulo ) . $letter;
            $column_index = (int) floor( ( $column_index - 1 ) / 26 );
        }

        return $letter;
    }

    private function xlsx_escape( string $value ): string {
        $value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value );
        return htmlspecialchars( $value ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8' );
    }

    private function get_xlsx_content_types_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function get_xlsx_root_rels_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function get_xlsx_app_props_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Agrocampo Post Venta</Application>'
            . '<DocSecurity>0</DocSecurity>'
            . '<ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Reporte</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company>Agrocampo</Company>'
            . '<LinksUpToDate>false</LinksUpToDate>'
            . '<SharedDoc>false</SharedDoc>'
            . '<HyperlinksChanged>false</HyperlinksChanged>'
            . '<AppVersion>1.0</AppVersion>'
            . '</Properties>';
    }

    private function get_xlsx_core_props_xml(): string {
        $created = gmdate( 'Y-m-d\TH:i:s\Z' );

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Observaciones filtradas</dc:title>'
            . '<dc:creator>Agrocampo Post Venta</dc:creator>'
            . '<cp:lastModifiedBy>Agrocampo Post Venta</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function get_xlsx_workbook_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function get_xlsx_workbook_rels_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function get_xlsx_styles_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF203040"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F6FA"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF48637E"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD8E0EA"/></left><right style="thin"><color rgb="FFD8E0EA"/></right><top style="thin"><color rgb="FFD8E0EA"/></top><bottom style="thin"><color rgb="FFD8E0EA"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '<dxfs count="0"/>'
            . '<tableStyles count="0" defaultTableStyle="TableStyleMedium9" defaultPivotStyle="PivotStyleLight16"/>'
            . '</styleSheet>';
    }

    private function verify_submission_action_nonce( string $action, int $submission_id ): bool {
        if ( $submission_id <= 0 ) {
            return false;
        }

        $nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['_wpnonce'] ) ) : '';
        if ( '' === $nonce ) {
            return false;
        }

        // Backward-compatible: accept legacy action nonce while links rotate to id-scoped nonce.
        return (bool) wp_verify_nonce( $nonce, $action . '_' . $submission_id ) || (bool) wp_verify_nonce( $nonce, $action );
    }

    private function enforce_post_for_ajax_json(): void {
        $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : '';
        if ( 'POST' !== $method ) {
            wp_send_json_error( array( 'message' => __( 'Método HTTP no permitido.', 'agrocampo-post-venta' ) ), 405 );
        }
    }

    public function handle_resend_email(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $submission_id = isset( $_GET['submission_id'] ) ? absint( $_GET['submission_id'] ) : 0;
        $manager_page = $this->get_manager_page_from_request();
        if ( ! $submission_id ) {
            wp_safe_redirect( $this->action_redirect_url( $manager_page, 'mail_failed', __( 'Informe inválido.', 'agrocampo-post-venta' ) ) );
            exit;
        }

        if ( ! $this->verify_submission_action_nonce( 'agp_pv_resend_email', $submission_id ) ) {
            wp_die( esc_html__( 'Enlace inválido o expirado.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
        }
        if ( $submission_id ) {
            $result = AGP_PV_Email::send_submission_email( $submission_id );
            if ( empty( $result['mail_sent'] ) ) {
                $message = $result['mail_error'] ?? __( 'No se pudo enviar el correo.', 'agrocampo-post-venta' );
                wp_safe_redirect( $this->action_redirect_url( $manager_page, 'mail_failed', $message ) );
                exit;
            }
        }

        wp_safe_redirect( $this->action_redirect_url( $manager_page, 'mail_sent' ) );
        exit;
    }

    public function handle_regenerate_pdf(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $submission_id = isset( $_GET['submission_id'] ) ? absint( $_GET['submission_id'] ) : 0;
        $manager_page = $this->get_manager_page_from_request();

        if ( ! $submission_id ) {
            wp_safe_redirect( $this->action_redirect_url( $manager_page, 'pdf_failed', __( 'Informe inválido.', 'agrocampo-post-venta' ) ) );
            exit;
        }

        if ( ! $this->verify_submission_action_nonce( 'agp_pv_regenerate_pdf', $submission_id ) ) {
            wp_die( esc_html__( 'Enlace inválido o expirado.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
        }

        $result = AGP_PV_PDF::regenerate_pdf_attachment( $submission_id );
        if ( empty( $result['ok'] ) ) {
            wp_safe_redirect( $this->action_redirect_url( $manager_page, 'pdf_failed', (string) ( $result['message'] ?? '' ) ) );
            exit;
        }

        wp_safe_redirect( $this->action_redirect_url( $manager_page, 'pdf_regenerated' ) );
        exit;
    }

    public function handle_mark_reviewed(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $submission_id = isset( $_GET['submission_id'] ) ? absint( $_GET['submission_id'] ) : 0;
        $manager_page = $this->get_manager_page_from_request();

        if ( ! $submission_id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . $manager_page ) );
            exit;
        }

        if ( ! $this->verify_submission_action_nonce( 'agp_pv_mark_reviewed', $submission_id ) ) {
            wp_die( esc_html__( 'Enlace inválido o expirado.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
        }

        global $wpdb;
        $reviewed_by = get_current_user_id();
        $reviewed_at = current_time( 'mysql' );
        $updated = $wpdb->update(
            AGP_PV_DB::table_name(),
            array(
                'review_status' => 'reviewed',
                'reviewed_by' => $reviewed_by,
                'reviewed_at' => $reviewed_at,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $submission_id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $updated ) {
            wp_safe_redirect(
                admin_url( 'admin.php?page=' . $manager_page . '&agp_pv_notice=review_failed&agp_pv_notice_message=' . rawurlencode( __( 'No se pudo actualizar el estado de revisión.', 'agrocampo-post-venta' ) ) )
            );
            exit;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=' . $manager_page . '&agp_pv_notice=review_marked' ) );
        exit;
    }

    public function handle_append_observation(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $submission_id = isset( $_POST['submission_id'] ) ? absint( wp_unslash( $_POST['submission_id'] ) ) : 0;
        $manager_page  = $this->get_manager_page_from_request();

        if ( ! $submission_id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . $manager_page ) );
            exit;
        }

        if ( ! $this->verify_submission_action_nonce( 'agp_pv_append_observation', $submission_id ) ) {
            wp_die( esc_html__( 'Enlace inválido o expirado.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
        }

        $submission = AGP_PV_Email::get_submission( $submission_id );
        $new_note   = AGP_PV_Plugin::normalize_observation_text( sanitize_textarea_field( wp_unslash( $_POST['observation_note'] ?? '' ) ) );

        if ( ! $submission || '' === $new_note ) {
            wp_safe_redirect(
                admin_url( 'admin.php?page=' . $manager_page . '&agp_pv_notice=observation_append_failed&agp_pv_notice_message=' . rawurlencode( __( 'Debes ingresar una observación válida.', 'agrocampo-post-venta' ) ) )
            );
            exit;
        }

        $user      = wp_get_current_user();
        $user_name = $user instanceof WP_User ? (string) $user->display_name : '';
        $combined  = AGP_PV_Plugin::append_observation_note( (string) ( $submission['observaciones'] ?? '' ), $new_note, $user_name );
        $status    = (string) ( $submission['review_status'] ?? '' );

        global $wpdb;
        $updated = $wpdb->update(
            AGP_PV_DB::table_name(),
            array(
                'observaciones' => $combined,
                'review_status' => AGP_PV_Plugin::is_reviewed_observation_status( $status ) ? 'reviewed' : AGP_PV_Plugin::resolve_review_status_for_observation( $combined, $status ),
                'updated_at'    => current_time( 'mysql' ),
            ),
            array( 'id' => $submission_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $updated ) {
            wp_safe_redirect(
                admin_url( 'admin.php?page=' . $manager_page . '&agp_pv_notice=observation_append_failed&agp_pv_notice_message=' . rawurlencode( __( 'No se pudo guardar la observación.', 'agrocampo-post-venta' ) ) )
            );
            exit;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=' . $manager_page . '&agp_pv_notice=observation_appended&submission_id=' . $submission_id ) );
        exit;
    }

    private function deny_pdf_preview( string $message, string $title, int $status = 403 ): void {
        $this->log_pdf_preview_event(
            'deny',
            array(
                'status' => $status,
                'message' => $message,
            )
        );

        wp_die( esc_html( $message ), esc_html( $title ), array( 'response' => $status ) );
    }

    private function log_pdf_preview_event( string $event, array $context = array() ): void {
        if ( ! ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || (bool) get_option( 'agp_pv_debug' ) ) ) {
            return;
        }

        $payload = wp_json_encode(
            array_merge(
                array(
                    'event' => $event,
                    'scope' => 'pdf_preview',
                    'ts' => gmdate( 'c' ),
                ),
                $context
            )
        );

        if ( $payload ) {
            error_log( '[agp_pv_admin] ' . $payload ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    public function handle_view_pdf_request(): void {
        if ( ! is_admin() ) {
            return;
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( ! in_array( $page, array( 'agp-pv-submissions', 'agp-pv-observations' ), true ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            $this->deny_pdf_preview( __( 'No autorizado.', 'agrocampo-post-venta' ), __( 'Acceso denegado', 'agrocampo-post-venta' ), 403 );
        }

        $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : '';
        if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
            $this->deny_pdf_preview( __( 'Método HTTP no permitido.', 'agrocampo-post-venta' ), __( 'Solicitud inválida', 'agrocampo-post-venta' ), 405 );
        }

        $view_id = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;
        if ( ! $view_id ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'agp_pv_view_pdf_' . $view_id ) ) {
            $this->deny_pdf_preview( __( 'Enlace de PDF inválido o expirado.', 'agrocampo-post-venta' ), __( 'Acceso denegado', 'agrocampo-post-venta' ), 403 );
        }

        $submission = AGP_PV_Email::get_submission( $view_id );
        if ( ! $submission ) {
            $this->deny_pdf_preview( __( 'No se encontró el informe solicitado.', 'agrocampo-post-venta' ), __( 'Informe no encontrado', 'agrocampo-post-venta' ), 404 );
        }

        $pdf_result = AGP_PV_PDF::ensure_pdf_attachment( $view_id, $submission );
        if ( empty( $pdf_result['status'] ) || 'ready' !== $pdf_result['status'] ) {
            $this->deny_pdf_preview( (string) ( $pdf_result['message'] ?? __( 'No fue posible generar el PDF.', 'agrocampo-post-venta' ) ), __( 'Error al generar PDF', 'agrocampo-post-venta' ), 500 );
        }

        $pdf_path = isset( $pdf_result['path'] ) ? (string) $pdf_result['path'] : '';
        $path_validation = AGP_PV_PDF::validate_pdf_attachment_path( $pdf_path );
        if ( empty( $path_validation['valid'] ) ) {
            $this->deny_pdf_preview( (string) ( $path_validation['message'] ?? __( 'No se encontró un PDF válido para previsualizar.', 'agrocampo-post-venta' ) ), __( 'PDF no disponible', 'agrocampo-post-venta' ), 404 );
        }

        $size = (int) @filesize( $pdf_path );
        if ( $size <= 0 ) {
            $this->deny_pdf_preview( __( 'No fue posible calcular el tamaño del PDF.', 'agrocampo-post-venta' ), __( 'Error de lectura', 'agrocampo-post-venta' ), 500 );
        }

        $report_id = AGP_PV_DB::get_visible_report_id( $submission );
        $filename = 'informe-tecnico-' . ( $report_id > 0 ? $report_id : $view_id ) . '.pdf';

        while ( ob_get_level() ) {
            ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        header( 'Accept-Ranges: none' );
        header( 'Content-Length: ' . (string) $size );

        if ( 'HEAD' === $method ) {
            exit;
        }

        $read = readfile( $pdf_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        if ( false === $read ) {
            $this->log_pdf_preview_event(
                'stream_failed',
                array(
                    'submission_id' => $view_id,
                    'path' => $pdf_path,
                )
            );
        }

        exit;
    }


    public function handle_regenerate_all_pdf_batch(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'No autorizado.', 'agrocampo-post-venta' ) ), 403 );
        }

        $this->enforce_post_for_ajax_json();

        check_ajax_referer( 'agp_pv_regenerate_all_pdf', 'nonce' );

        global $wpdb;
        $table = AGP_PV_DB::table_name();

        $offset = isset( $_POST['offset'] ) ? max( 0, absint( wp_unslash( $_POST['offset'] ) ) ) : 0;
        $limit = isset( $_POST['limit'] ) ? max( 10, min( 100, absint( wp_unslash( $_POST['limit'] ) ) ) ) : 25;
        $batch_min = 10;
        $batch_max = 100;
        $time_budget_ms = 4200;

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( 0 === $total ) {
            wp_send_json_success(
                array(
                    'processed' => 0,
                    'total' => 0,
                    'success' => 0,
                    'failed' => 0,
                    'done' => true,
                    'next_offset' => 0,
                    'next_limit' => $limit,
                    'elapsed_ms' => 0,
                )
            );
        }

        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d", $limit, $offset ) );

        $success = 0;
        $failed = 0;
        $processed_in_batch = 0;
        $batch_start = (int) round( microtime( true ) * 1000 );

        foreach ( $ids as $id ) {
            $result = AGP_PV_PDF::regenerate_pdf_attachment( (int) $id );
            if ( ! empty( $result['ok'] ) ) {
                $success++;
            } else {
                $failed++;
            }

            $processed_in_batch++;
            $elapsed_now = (int) round( microtime( true ) * 1000 ) - $batch_start;
            if ( $elapsed_now >= $time_budget_ms ) {
                break;
            }
        }

        $processed = min( $total, $offset + $processed_in_batch );
        $next_offset = $offset + $processed_in_batch;
        $elapsed_ms = max( 1, (int) round( microtime( true ) * 1000 ) - $batch_start );

        $next_limit = $limit;
        if ( $elapsed_ms > 4500 ) {
            $next_limit = max( $batch_min, $limit - 10 );
        } elseif ( $elapsed_ms < 1800 && $processed_in_batch >= $limit ) {
            $next_limit = min( $batch_max, $limit + 10 );
        }

        wp_send_json_success(
            array(
                'processed' => $processed,
                'total' => $total,
                'success' => $success,
                'failed' => $failed,
                'done' => $next_offset >= $total || $processed_in_batch <= 0,
                'next_offset' => $next_offset,
                'next_limit' => $next_limit,
                'elapsed_ms' => $elapsed_ms,
            )
        );
    }

    public function handle_send_overdue_test_email(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_send_overdue_test_email' );

        $result = AGP_PV_Email::send_overdue_test_email();

        if ( ! empty( $result['sent'] ) ) {
            $mode = ! empty( $result['mode'] ) ? sanitize_key( (string) $result['mode'] ) : 'sample';
            wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=overdue_test_sent&agp_pv_notice_mode=' . rawurlencode( $mode ) ) );
            exit;
        }

        $message = $result['message'] ?? __( 'No se pudo enviar el correo de vencimiento de prueba.', 'agrocampo-post-venta' );
        wp_safe_redirect(
            admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=overdue_test_failed&agp_pv_notice_message=' . rawurlencode( $message ) )
        );
        exit;
    }

    public function handle_send_summary_now(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_send_summary_now' );

        $period = isset( $_POST['summary_period'] ) ? sanitize_key( wp_unslash( $_POST['summary_period'] ) ) : 'daily';
        if ( ! in_array( $period, array( 'daily', 'weekly' ), true ) ) {
            $period = 'daily';
        }

        $result = AGP_PV_Email::send_period_summary( $period, true );

        if ( ! empty( $result['sent'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=summary_sent&agp_pv_notice_period=' . rawurlencode( $period ) ) );
            exit;
        }

        $message = $result['message'] ?? __( 'No se pudo enviar el resumen manual.', 'agrocampo-post-venta' );
        wp_safe_redirect(
            admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=summary_failed&agp_pv_notice_message=' . rawurlencode( $message ) )
        );
        exit;
    }

    public function handle_send_test_email(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_send_test_email' );

        $email = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
        $result = AGP_PV_Email::send_test_email( $email );

        if ( ! empty( $result['sent'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=test_sent' ) );
            exit;
        }

        $message = $result['message'] ?? __( 'No se pudo enviar el correo de prueba.', 'agrocampo-post-venta' );
        wp_safe_redirect(
            admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=test_failed&agp_pv_notice_message=' . rawurlencode( $message ) )
        );
        exit;
    }


    public function handle_save_technicians(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_save_technicians' );

        $raw = isset( $_POST['agp_pv_technicians'] ) ? sanitize_textarea_field( wp_unslash( $_POST['agp_pv_technicians'] ) ) : '';
        $items = AGP_PV_Plugin::normalize_technicians( $raw );

        if ( empty( $items ) ) {
            delete_option( 'agp_pv_technicians' );
        } else {
            update_option( 'agp_pv_technicians', $items );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=technicians_saved' ) );
        exit;
    }

    public function handle_save_recipients(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_save_recipients' );

        $raw = isset( $_POST['agp_pv_recipients'] ) ? sanitize_textarea_field( wp_unslash( $_POST['agp_pv_recipients'] ) ) : '';
        AGP_PV_Email::update_recipients_option( $raw );

        wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=recipients_saved' ) );
        exit;
    }

    public function handle_save_notifications_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_save_notifications_settings' );

        AGP_PV_Email::update_notification_settings(
            array(
                'admin_email' => (string) wp_unslash( $_POST['agp_pv_notifications_admin_email'] ?? '' ),
                'enable_overdue' => ! empty( $_POST['agp_pv_notifications_enable_overdue'] ) ? 1 : 0,
                'enable_daily_summary' => ! empty( $_POST['agp_pv_notifications_enable_daily_summary'] ) ? 1 : 0,
                'enable_weekly_summary' => ! empty( $_POST['agp_pv_notifications_enable_weekly_summary'] ) ? 1 : 0,
                'overdue_days' => absint( $_POST['agp_pv_notifications_overdue_days'] ?? 7 ),
                'overdue_subject' => (string) wp_unslash( $_POST['agp_pv_notifications_overdue_subject'] ?? '' ),
                'summary_subject' => (string) wp_unslash( $_POST['agp_pv_notifications_summary_subject'] ?? '' ),
                'overdue_body' => (string) wp_unslash( $_POST['agp_pv_notifications_overdue_body'] ?? '' ),
                'summary_body' => (string) wp_unslash( $_POST['agp_pv_notifications_summary_body'] ?? '' ),
            )
        );

        $report_window_days = absint( $_POST['agp_pv_observations_report_window_days'] ?? 30 );
        if ( $report_window_days < 1 ) {
            $report_window_days = 30;
        }
        update_option( 'agp_pv_observations_report_window_days', min( 3650, $report_window_days ) );

        AGP_PV_Plugin::schedule_notification_events();

        wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=notifications_saved' ) );
        exit;
    }

    public function handle_save_logo(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_save_logo' );

        $logo_id = isset( $_POST['agp_pv_logo_attachment_id'] ) ? absint( $_POST['agp_pv_logo_attachment_id'] ) : 0;
        $width = isset( $_POST['agp_pv_logo_width_mm'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['agp_pv_logo_width_mm'] ) ) : 38.0;

        $header_title = isset( $_POST['agp_pv_pdf_header_title'] ) ? sanitize_text_field( wp_unslash( $_POST['agp_pv_pdf_header_title'] ) ) : __( 'INFORME TÉCNICO', 'agrocampo-post-venta' );
        $footer_text = isset( $_POST['agp_pv_pdf_footer_text'] ) ? sanitize_text_field( wp_unslash( $_POST['agp_pv_pdf_footer_text'] ) ) : __( 'Talca • Linares • Parral   |   +56 9 9748 5650', 'agrocampo-post-venta' );
        $it_label_template = isset( $_POST['agp_pv_pdf_it_label_template'] ) ? sanitize_text_field( wp_unslash( $_POST['agp_pv_pdf_it_label_template'] ) ) : __( 'IT: %d', 'agrocampo-post-venta' );
        $app_icon_192_id = isset( $_POST['agp_pv_app_icon_192_attachment_id'] ) ? absint( $_POST['agp_pv_app_icon_192_attachment_id'] ) : 0;
        $app_icon_512_id = isset( $_POST['agp_pv_app_icon_512_attachment_id'] ) ? absint( $_POST['agp_pv_app_icon_512_attachment_id'] ) : 0;

        if ( '' === $it_label_template || false === strpos( $it_label_template, '%' ) ) {
            $it_label_template = __( 'IT: %d', 'agrocampo-post-venta' );
        }

        if ( function_exists( 'mb_substr' ) ) {
            $header_title = mb_substr( $header_title, 0, 70 );
            $footer_text = mb_substr( $footer_text, 0, 110 );
            $it_label_template = mb_substr( $it_label_template, 0, 40 );
        } else {
            $header_title = substr( $header_title, 0, 70 );
            $footer_text = substr( $footer_text, 0, 110 );
            $it_label_template = substr( $it_label_template, 0, 40 );
        }

        update_option( 'agp_pv_logo_attachment_id', $logo_id );
        update_option( 'agp_pv_logo_width_mm', $width > 0 ? $width : 38.0 );
        update_option( 'agp_pv_pdf_header_title', $header_title );
        update_option( 'agp_pv_pdf_footer_text', $footer_text );
        update_option( 'agp_pv_pdf_it_label_template', $it_label_template );
        update_option( 'agp_pv_machine_status_position', $machine_status_position );
        update_option( 'agp_pv_app_icon_192_attachment_id', $app_icon_192_id );
        update_option( 'agp_pv_app_icon_512_attachment_id', $app_icon_512_id );

        wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=logo_saved' ) );
        exit;
    }

    public function handle_delete_all_submissions(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_delete_all_submissions' );

        global $wpdb;
        $table = AGP_PV_DB::table_name();

        $rows = $wpdb->get_results( "SELECT pdf_attachment_id FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $deleted = 0;

        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $pdf_id = isset( $row['pdf_attachment_id'] ) ? (int) $row['pdf_attachment_id'] : 0;
                if ( $pdf_id > 0 ) {
                    wp_delete_attachment( $pdf_id, true );
                }
                $deleted++;
            }
        }

        $result = $wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ( false === $result ) {
            $message = ! empty( $wpdb->last_error ) ? sanitize_text_field( (string) $wpdb->last_error ) : __( 'No se pudieron eliminar los informes.', 'agrocampo-post-venta' );
            wp_safe_redirect(
                admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=delete_all_failed&agp_pv_notice_message=' . rawurlencode( $message ) )
            );
            exit;
        }

        wp_safe_redirect(
            admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=delete_all_completed&agp_pv_notice_count=' . $deleted )
        );
        exit;
    }

    public function handle_import_legacy_csv(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_import_legacy_csv' );

        if ( empty( $_FILES['agp_pv_legacy_csv']['tmp_name'] ) ) {
            wp_safe_redirect(
                admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=import_invalid_file&agp_pv_notice_message=' . rawurlencode( __( 'Debes seleccionar un archivo CSV válido.', 'agrocampo-post-venta' ) ) )
            );
            exit;
        }

        $file_path = (string) $_FILES['agp_pv_legacy_csv']['tmp_name'];
        $result = $this->import_legacy_csv_file( $file_path );

        if ( ! $result['ok'] ) {
            wp_safe_redirect(
                admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=import_failed&agp_pv_notice_message=' . rawurlencode( $result['message'] ) )
            );
            exit;
        }

        wp_safe_redirect(
            admin_url(
                'admin.php?page=agp-pv-settings&agp_pv_notice=import_completed&agp_pv_imported=' .
                absint( $result['imported'] ) .
                '&agp_pv_skipped=' .
                absint( $result['skipped'] ) .
                '&agp_pv_failed=' .
                absint( $result['failed'] )
            )
        );
        exit;
    }

    public function handle_export_legacy_csv(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_export_legacy_csv' );

        global $wpdb;
        $table = AGP_PV_DB::table_name();
        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if ( empty( $rows ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-settings&agp_pv_notice=import_failed&agp_pv_notice_message=' . rawurlencode( __( 'No hay informes para exportar.', 'agrocampo-post-venta' ) ) ) );
            exit;
        }

        $this->stream_legacy_compatible_csv( $rows, 'informes-tecnicos-compatibles-importador' );
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function stream_legacy_compatible_csv( array $rows, string $file_prefix ): void {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $file_prefix ) . '-' . gmdate( 'Ymd-His' ) . '.csv' );

        $output = fopen( 'php://output', 'w' );
        if ( false === $output ) {
            return;
        }

        $headers = $this->legacy_export_headers();
        fputcsv( $output, $headers );

        foreach ( $rows as $row ) {
            $legacy_row = $this->map_submission_to_legacy_export_row( $row );
            $ordered = array();
            foreach ( $headers as $header ) {
                $ordered[] = $this->escape_csv_formula_injection( (string) ( $legacy_row[ $header ] ?? '' ) );
            }
            fputcsv( $output, $ordered );
        }

        fclose( $output );
        exit;
    }

    /**
     * @return string[]
     */
    private function legacy_export_headers(): array {
        return array(
            'legacy_id',
            'Técnico',
            'Cliente',
            'Correo cliente',
            'Address - Faena Lugar',
            'Máquina',
            'Modelo',
            'Serie',
            'N° Interno',
            'Fecha',
            'Horas',
            'Tipo de servicio',
            'Tipo de mantención',
            'Cantidad de horas',
            'Estado de la máquina',
            'Fecha a contactar',
            'Fecha reparación',
            'Fecha cierre',
            'Lubricantes',
            'Filtros utilizados',
            'Componentes utilizados',
            'Trabajos realizados',
            'Observaciones',
            'Correo copia',
            'Hora de envío',
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,string>
     */
    private function map_submission_to_legacy_export_row( array $row ): array {
        $legacy_id = isset( $row['legacy_id'] ) ? absint( (string) $row['legacy_id'] ) : 0;
        if ( $legacy_id <= 0 ) {
            $legacy_id = isset( $row['id'] ) ? absint( (string) $row['id'] ) : 0;
        }

        $tipo_servicio_label = trim( (string) ( $row['tipo_servicio_label'] ?? '' ) );
        if ( '' === $tipo_servicio_label ) {
            $tipo_servicio_label = $this->map_stored_tipo_servicio_to_legacy_label( (string) ( $row['tipo_servicio'] ?? '' ) );
        }

        $tipo_mantencion_label = trim( (string) ( $row['tipo_mantencion_label'] ?? '' ) );
        if ( '' === $tipo_mantencion_label ) {
            $tipo_mantencion_label = $this->map_stored_tipo_mantencion_to_legacy_label( (string) ( $row['tipo_mantencion'] ?? '' ) );
        }

        return array(
            'legacy_id' => (string) $legacy_id,
            'Técnico' => sanitize_text_field( (string) ( $row['tecnico'] ?? '' ) ),
            'Cliente' => sanitize_text_field( (string) ( $row['cliente'] ?? '' ) ),
            'Correo cliente' => sanitize_email( (string) ( $row['email_cliente'] ?? '' ) ),
            'Address - Faena Lugar' => sanitize_text_field( (string) ( $row['faena_lugar'] ?? '' ) ),
            'Máquina' => sanitize_text_field( (string) ( $row['maquina'] ?? '' ) ),
            'Modelo' => sanitize_text_field( (string) ( $row['modelo'] ?? '' ) ),
            'Serie' => sanitize_text_field( (string) ( $row['serie'] ?? '' ) ),
            'N° Interno' => sanitize_text_field( (string) ( $row['numero_interno'] ?? '' ) ),
            'Fecha' => sanitize_text_field( (string) ( $row['fecha'] ?? '' ) ),
            'Horas' => sanitize_text_field( (string) ( $row['horas'] ?? '' ) ),
            'Tipo de servicio' => sanitize_text_field( $tipo_servicio_label ),
            'Tipo de mantención' => sanitize_text_field( $tipo_mantencion_label ),
            'Cantidad de horas' => sanitize_text_field( (string) ( $row['cantidad_horas'] ?? '' ) ),
            'Estado de la máquina' => sanitize_text_field( AGP_PV_Plugin::get_machine_status_label( (string) ( $row['estado_maquina'] ?? '' ) ) ),
            'Fecha a contactar' => sanitize_text_field( (string) ( $row['fecha_contacto'] ?? '' ) ),
            'Fecha reparación' => sanitize_text_field( (string) ( $row['fecha_reparacion'] ?? '' ) ),
            'Fecha cierre' => sanitize_text_field( (string) ( $row['fecha_cierre'] ?? '' ) ),
            'Lubricantes' => AGP_PV_Plugin::resolve_lubricants_text_from_submission( $row ),
            'Filtros utilizados' => sanitize_textarea_field( (string) ( $row['filtros_utilizados'] ?? '' ) ),
            'Componentes utilizados' => sanitize_textarea_field( (string) ( $row['componentes_utilizados'] ?? '' ) ),
            'Trabajos realizados' => sanitize_textarea_field( (string) ( $row['trabajos_realizados'] ?? '' ) ),
            'Observaciones' => sanitize_textarea_field( (string) ( $row['observaciones'] ?? '' ) ),
            'Correo copia' => sanitize_email( (string) ( $row['correo_copia'] ?? '' ) ),
            'Hora de envío' => sanitize_text_field( (string) ( $row['created_at'] ?? '' ) ),
        );
    }

    private function map_stored_tipo_servicio_to_legacy_label( string $value ): string {
        $map = array(
            'one' => __( 'Factura Cliente', 'agrocampo-post-venta' ),
            'two' => __( 'Garantía', 'agrocampo-post-venta' ),
            'Interno' => __( 'Mantención', 'agrocampo-post-venta' ),
            'Visita-de-Cortesía' => __( 'Visita de Cortesía', 'agrocampo-post-venta' ),
            'Diagnostico-Técnico' => __( 'Diagnóstico Técnico', 'agrocampo-post-venta' ),
            'Entrega-Técnica' => __( 'Entrega Técnica', 'agrocampo-post-venta' ),
        );

        return $map[ $value ] ?? sanitize_text_field( $value );
    }

    private function map_stored_tipo_mantencion_to_legacy_label( string $value ): string {
        $map = array(
            'one' => __( '100 Horas', 'agrocampo-post-venta' ),
            'two' => __( '400 Horas', 'agrocampo-post-venta' ),
            '500-Horas' => __( '500 Horas', 'agrocampo-post-venta' ),
            '800-Horas' => __( '800 Horas', 'agrocampo-post-venta' ),
            '1000-Horas' => __( '1000 Horas', 'agrocampo-post-venta' ),
            '1200' => __( '1200 Horas', 'agrocampo-post-venta' ),
            '1600-Horas' => __( '1500 Horas', 'agrocampo-post-venta' ),
            'OTRO' => __( 'OTRO', 'agrocampo-post-venta' ),
        );

        return $map[ $value ] ?? sanitize_text_field( $value );
    }

    private function escape_csv_formula_injection( string $value ): string {
        if ( '' === $value ) {
            return $value;
        }

        $first = substr( $value, 0, 1 );
        if ( in_array( $first, array( '=', '+', '-', '@' ), true ) ) {
            return "'" . $value;
        }

        return $value;
    }

    private function import_legacy_csv_file( string $file_path ): array {
        $handle = fopen( $file_path, 'r' );
        if ( false === $handle ) {
            return array(
                'ok' => false,
                'message' => __( 'No fue posible abrir el archivo CSV.', 'agrocampo-post-venta' ),
                'imported' => 0,
                'skipped' => 0,
                'failed' => 0,
            );
        }

        global $wpdb;

        $headers = array();
        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $table = AGP_PV_DB::table_name();
        $line_number = 0;
        $first_error = '';

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $line_number++;

            if ( empty( $row ) || ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) ) {
                continue;
            }

            if ( empty( $headers ) ) {
                $headers = $this->normalize_import_headers( $row );

                if ( ! $this->has_legacy_required_headers( $headers ) ) {
                    fclose( $handle );
                    return array(
                        'ok' => false,
                        'message' => __( 'El CSV no contiene las columnas mínimas requeridas (Técnico, Cliente y Máquina).', 'agrocampo-post-venta' ),
                        'imported' => 0,
                        'skipped' => 0,
                        'failed' => 0,
                    );
                }

                continue;
            }

            $normalized_row = array();
            foreach ( $headers as $index => $header ) {
                if ( '' === $header ) {
                    continue;
                }

                $normalized_row[ $header ] = isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
            }

            $submission = $this->map_legacy_row_to_submission( $normalized_row );
            if ( '' === $submission['tecnico'] && '' === $submission['cliente'] && '' === $submission['maquina'] ) {
                $skipped++;
                continue;
            }

            $inserted = $wpdb->insert( $table, $submission, $this->submission_insert_formats() );
            if ( false === $inserted ) {
                $failed++;

                if ( '' === $first_error ) {
                    $db_error = ! empty( $wpdb->last_error ) ? sanitize_text_field( (string) $wpdb->last_error ) : __( 'Error desconocido de base de datos.', 'agrocampo-post-venta' );
                    $first_error = sprintf( __( 'Línea %1$d: %2$s', 'agrocampo-post-venta' ), $line_number, $db_error );
                }

                continue;
            }

            $imported++;
        }

        fclose( $handle );

        if ( 0 === $imported && $failed > 0 ) {
            return array(
                'ok' => false,
                'message' => sprintf( __( 'No se pudo importar ninguna fila. %s', 'agrocampo-post-venta' ), $first_error ),
                'imported' => 0,
                'skipped' => $skipped,
                'failed' => $failed,
            );
        }

        return array(
            'ok' => true,
            'message' => '',
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
        );
    }

    private function normalize_import_headers( array $raw_headers ): array {
        $headers = array();

        foreach ( $raw_headers as $header ) {
            $value = wp_strip_all_tags( (string) $header );
            $value = trim( $value, "\xEF\xBB\xBF\" \t\n\r\0\x0B" );
            $headers[] = mb_strtolower( remove_accents( $value ) );
        }

        return $headers;
    }

    private function has_legacy_required_headers( array $headers ): bool {
        $required = array( 'tecnico', 'cliente', 'maquina' );

        foreach ( $required as $header ) {
            if ( ! in_array( $header, $headers, true ) ) {
                return false;
            }
        }

        return true;
    }

    private function map_legacy_row_to_submission( array $row ): array {
        $tipo_servicio_label = $this->legacy_value( $row, 'tipo de servicio' );
        $tipo_mantencion_label = $this->legacy_value( $row, 'tipo de mantencion' );
        $created_at = $this->parse_legacy_datetime( $this->legacy_value( $row, 'hora de envio' ) );
        $observaciones = AGP_PV_Plugin::normalize_observation_text( sanitize_textarea_field( $this->legacy_value( $row, 'observaciones' ) ) );
        $review_status = AGP_PV_Plugin::resolve_review_status_for_observation( $observaciones );

        return array(
            'legacy_id' => $this->extract_legacy_id( $row ),
            'tecnico' => sanitize_text_field( $this->legacy_value( $row, 'tecnico' ) ),
            'cliente' => sanitize_text_field( $this->legacy_value( $row, 'cliente' ) ),
            'email_cliente' => sanitize_email( $this->legacy_value( $row, 'correo cliente' ) ),
            'faena_lugar' => sanitize_text_field( $this->legacy_value( $row, 'address - faena lugar' ) ),
            'maquina' => sanitize_text_field( $this->legacy_value( $row, 'maquina' ) ),
            'modelo' => sanitize_text_field( $this->legacy_value( $row, 'modelo' ) ),
            'serie' => sanitize_text_field( $this->legacy_value( $row, 'serie' ) ),
            'numero_interno' => sanitize_text_field( $this->legacy_value( $row, 'n° interno' ) ),
            'fecha' => sanitize_text_field( $this->legacy_value( $row, 'fecha' ) ),
            'horas' => sanitize_text_field( $this->legacy_value( $row, 'horas' ) ),
            'tipo_servicio' => $this->map_legacy_tipo_servicio_key( $tipo_servicio_label ),
            'tipo_servicio_label' => sanitize_text_field( $tipo_servicio_label ),
            'tipo_mantencion' => $this->map_legacy_tipo_mantencion_key( $tipo_mantencion_label ),
            'tipo_mantencion_label' => sanitize_text_field( $tipo_mantencion_label ),
            'cantidad_horas' => sanitize_text_field( $this->legacy_value( $row, 'cantidad de horas' ) ),
            'estado_maquina' => AGP_PV_Plugin::parse_machine_status_value( $this->legacy_value( $row, 'estado de la maquina' ) ),
            'fecha_contacto' => sanitize_text_field( $this->legacy_value( $row, 'fecha a contactar' ) ),
            'fecha_reparacion' => sanitize_text_field( $this->legacy_value( $row, 'fecha reparacion' ) ),
            'fecha_cierre' => sanitize_text_field( $this->legacy_value( $row, 'fecha cierre' ) ),
            'lubricantes' => sanitize_textarea_field( $this->legacy_value( $row, 'lubricantes' ) ),
            'lubricantes_json' => null,
            'filtros_utilizados' => sanitize_textarea_field( $this->legacy_value( $row, 'filtros utilizados' ) ),
            'componentes_utilizados' => sanitize_textarea_field( $this->legacy_value( $row, 'componentes utilizados' ) ),
            'trabajos_realizados' => sanitize_textarea_field( $this->legacy_value( $row, 'trabajos realizados' ) ),
            'observaciones' => $observaciones,
            'firma_cliente_id' => 0,
            'firma_tecnico_id' => 0,
            'fotos_ids' => wp_json_encode( array() ),
            'pdf_attachment_id' => 0,
            'pdf_status' => 'pending',
            'pdf_error' => '',
            'pdf_last_attempt_at' => null,
            'pdf_generated_ms' => 0,
            'pdf_size_bytes' => 0,
            'pdf_page_count' => 0,
            'pdf_warnings' => wp_json_encode( array() ),
            'correo_copia' => sanitize_email( $this->legacy_value( $row, 'correo copia' ) ),
            'mail_status' => 'pending',
            'mail_error' => '',
            'mail_last_attempt_at' => null,
            'review_status' => $review_status,
            'created_at' => $created_at,
            'updated_at' => $created_at,
        );
    }

    private function submission_insert_formats(): array {
        return array(
            '%d', // legacy_id
            '%s', // tecnico
            '%s', // cliente
            '%s', // email_cliente
            '%s', // faena_lugar
            '%s', // maquina
            '%s', // modelo
            '%s', // serie
            '%s', // numero_interno
            '%s', // fecha
            '%s', // horas
            '%s', // tipo_servicio
            '%s', // tipo_servicio_label
            '%s', // tipo_mantencion
            '%s', // tipo_mantencion_label
            '%s', // cantidad_horas
            '%s', // estado_maquina
            '%s', // fecha_contacto
            '%s', // fecha_reparacion
            '%s', // fecha_cierre
            '%s', // lubricantes
            '%s', // lubricantes_json
            '%s', // filtros_utilizados
            '%s', // componentes_utilizados
            '%s', // trabajos_realizados
            '%s', // observaciones
            '%d', // firma_cliente_id
            '%d', // firma_tecnico_id
            '%s', // fotos_ids
            '%d', // pdf_attachment_id
            '%s', // pdf_status
            '%s', // pdf_error
            '%s', // pdf_last_attempt_at
            '%d', // pdf_generated_ms
            '%d', // pdf_size_bytes
            '%d', // pdf_page_count
            '%s', // pdf_warnings
            '%s', // correo_copia
            '%s', // mail_status
            '%s', // mail_error
            '%s', // mail_last_attempt_at
            '%s', // review_status
            '%s', // created_at
            '%s', // updated_at
        );
    }


    private function extract_legacy_id( array $row ): int {
        $candidates = array(
            'legacy_id',
            'entry_id',
            'id',
            'id formulario',
            'id del formulario',
            'id formulario anterior',
        );

        foreach ( $candidates as $candidate ) {
            $value = isset( $row[ $candidate ] ) ? trim( (string) $row[ $candidate ] ) : '';
            if ( '' === $value ) {
                continue;
            }

            $legacy_id = absint( $value );
            if ( $legacy_id > 0 ) {
                return $legacy_id;
            }
        }

        return 0;
    }

    private function legacy_value( array $row, string $key ): string {
        return isset( $row[ $key ] ) ? (string) $row[ $key ] : '';
    }

    private function map_legacy_tipo_servicio_key( string $value ): string {
        $normalized = mb_strtolower( remove_accents( trim( $value ) ) );
        $map = array(
            'factura cliente' => 'one',
            'garantia' => 'two',
            'mantencion' => 'Interno',
            'visita de cortesia' => 'Visita-de-Cortesía',
            'diagnostico tecnico' => 'Diagnostico-Técnico',
            'entrega tecnica' => 'Entrega-Técnica',
        );

        return $map[ $normalized ] ?? sanitize_text_field( $value );
    }

    private function map_legacy_tipo_mantencion_key( string $value ): string {
        $normalized = mb_strtolower( remove_accents( trim( $value ) ) );
        $map = array(
            '100 horas' => 'one',
            '400 horas' => 'two',
            '500 horas' => '500-Horas',
            '800 horas' => '800-Horas',
            '1000 horas' => '1000-Horas',
            '1200 horas' => '1200',
            '1500 horas' => '1600-Horas',
            'otro' => 'OTRO',
        );

        return $map[ $normalized ] ?? sanitize_text_field( $value );
    }

    private function parse_legacy_datetime( string $value ): string {
        $timestamp = strtotime( trim( $value ) );
        if ( false === $timestamp ) {
            return current_time( 'mysql' );
        }

        return wp_date( 'Y-m-d H:i:s', $timestamp );
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( empty( $_GET['page'] ) ) {
            return;
        }

        $page = sanitize_key( wp_unslash( $_GET['page'] ) );
        if ( 'agp-pv-settings' !== $page && 'agp-pv-submissions' !== $page ) {
            return;
        }

        wp_enqueue_style(
            'agp-pv-admin',
            AGP_PV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AGP_PV_VERSION
        );

        if ( 'agp-pv-submissions' === $page ) {
            wp_enqueue_script(
                'agp-pv-admin-submissions',
                AGP_PV_PLUGIN_URL . 'assets/js/admin-submissions.js',
                array( 'jquery' ),
                AGP_PV_VERSION,
                true
            );

            wp_localize_script(
                'agp-pv-admin-submissions',
                'agpPvAdminSubmissions',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'agp_pv_regenerate_all_pdf' ),
                    'batchSize' => 25,
                    'batchMin' => 10,
                    'batchMax' => 100,
                    'messages' => array(
                        'running' => __( 'Regenerando PDF... %1$d/%2$d (ok: %3$d, fallidos: %4$d)', 'agrocampo-post-venta' ),
                        'done' => __( 'Regeneración completada. Procesados: %1$d, OK: %2$d, Fallidos: %3$d.', 'agrocampo-post-venta' ),
                        'error' => __( 'No fue posible completar la regeneración masiva de PDF.', 'agrocampo-post-venta' ),
                        'starting' => __( 'Iniciando...', 'agrocampo-post-venta' ),
                    ),
                    'labels' => array(
                        'buttonDefault' => __( 'Regenerar todos los PDF', 'agrocampo-post-venta' ),
                        'buttonRunning' => __( 'Regenerando...', 'agrocampo-post-venta' ),
                        'confirm' => __( '¿Regenerar todos los PDF históricos? Esta acción puede tardar varios minutos.', 'agrocampo-post-venta' ),
                    ),
                )
            );
        }

        if ( 'agp-pv-settings' !== $page ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'agp-pv-admin-settings',
            AGP_PV_PLUGIN_URL . 'assets/js/admin-settings.js',
            array( 'jquery' ),
            AGP_PV_VERSION,
            true
        );
    }
}

class AGP_PV_Submissions_Table extends WP_List_Table {
    private array $filters = array();
    private string $manager_page = 'agp-pv-submissions';
    private bool $observations_only = false;

    public function __construct( array $args = array() ) {
        $manager_page = isset( $args['manager_page'] ) ? sanitize_key( (string) $args['manager_page'] ) : 'agp-pv-submissions';
        if ( in_array( $manager_page, array( 'agp-pv-submissions', 'agp-pv-observations' ), true ) ) {
            $this->manager_page = $manager_page;
        }

        $this->observations_only = ! empty( $args['observations_only'] );

        parent::__construct(
            array(
                'singular' => 'agp_pv_submission',
                'plural'   => 'agp_pv_submissions',
                'ajax'     => false,
            )
        );
    }

    public function get_columns(): array {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'id' => __( 'ID', 'agrocampo-post-venta' ),
            'tecnico' => __( 'Técnico', 'agrocampo-post-venta' ),
            'cliente' => __( 'Cliente', 'agrocampo-post-venta' ),
            'email_cliente' => __( 'Correo cliente', 'agrocampo-post-venta' ),
            'serie' => __( 'Serie', 'agrocampo-post-venta' ),
            'tipo_servicio' => __( 'Tipo de servicio', 'agrocampo-post-venta' ),
            'review_status' => __( 'Revisión', 'agrocampo-post-venta' ),
            'reviewed_by' => __( 'Revisado por', 'agrocampo-post-venta' ),
            'reviewed_at' => __( 'Fecha revisión', 'agrocampo-post-venta' ),
            'mail_status' => __( 'Correo', 'agrocampo-post-venta' ),
            'pdf_status' => __( 'PDF', 'agrocampo-post-venta' ),
            'pdf_metrics' => __( 'Métricas PDF', 'agrocampo-post-venta' ),
            'created_at' => __( 'Fecha', 'agrocampo-post-venta' ),
        );

        if ( $this->observations_only ) {
            $columns = array_slice( $columns, 0, 4, true )
                + array( 'observaciones_preview' => __( 'Observación', 'agrocampo-post-venta' ) )
                + array_slice( $columns, 4, null, true );
        } else {
            unset( $columns['review_status'] );
            unset( $columns['reviewed_by'] );
            unset( $columns['reviewed_at'] );
        }

        return $columns;
    }

    protected function get_sortable_columns(): array {
        $columns = array(
            'id' => array( 'id', false ),
            'tecnico' => array( 'tecnico', false ),
            'cliente' => array( 'cliente', false ),
            'created_at' => array( 'created_at', true ),
            'mail_status' => array( 'mail_status', false ),
            'pdf_status' => array( 'pdf_status', false ),
        );

        if ( $this->observations_only ) {
            $columns['review_status'] = array( 'review_status', false );
            $columns['reviewed_at'] = array( 'reviewed_at', false );
        }

        return $columns;
    }

    public function get_bulk_actions(): array {
        return array(
            'bulk_resend' => __( 'Reintentar envío de correo', 'agrocampo-post-venta' ),
            'bulk_regenerate_pdf' => __( 'Regenerar PDF', 'agrocampo-post-venta' ),
            'bulk_export_csv' => __( 'Exportar CSV compatible', 'agrocampo-post-venta' ),
            'bulk_delete' => __( 'Eliminar', 'agrocampo-post-venta' ),
        );
    }

    public function no_items(): void {
        esc_html_e( 'No hay informes para los filtros seleccionados.', 'agrocampo-post-venta' );
    }

    public function column_cb( $item ): string {
        return sprintf( '<input type="checkbox" name="submission[]" value="%d" />', absint( $item['id'] ) );
    }

    public function prepare_items(): void {
        global $wpdb;

        $table = AGP_PV_DB::table_name();
        $this->filters = $this->get_filters_from_request();

        $this->process_bulk_action();

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $where_data = $this->build_where_clause( $this->filters );
        $where = $where_data['sql'];
        $where_values = $where_data['values'];

        $order_by = $this->get_order_by();
        $order = $this->get_order_direction();

        $items_sql = "SELECT * FROM {$table} {$where} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
        $items_query = $wpdb->prepare( $items_sql, array_merge( $where_values, array( $per_page, $offset ) ) );
        $this->items = $wpdb->get_results( $items_query, ARRAY_A );

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
        $count_query = empty( $where_values ) ? $count_sql : $wpdb->prepare( $count_sql, $where_values );
        $total_items = (int) $wpdb->get_var( $count_query );

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page' => $per_page,
                'total_pages' => (int) ceil( $total_items / $per_page ),
            )
        );

        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
    }

    public function render_filters(): void {
        $mail_status   = $this->filters['mail_status'];
        $pdf_status    = $this->filters['pdf_status'];
        $review_status = $this->filters['review_status'];
        $email         = $this->filters['email'];
        $date_from     = $this->filters['date_from'];
        $date_to       = $this->filters['date_to'];

        echo '<div class="agp-pv-toolbar">';
        echo '<div class="agp-pv-filter-row">';

        echo '<label class="agp-pv-field" for="agp-pv-filter-mail"><span class="agp-pv-field__label">' . esc_html__( 'Estado correo', 'agrocampo-post-venta' ) . '</span>';
        echo '<select id="agp-pv-filter-mail" name="mail_status">';
        echo '<option value="">' . esc_html__( 'Todos', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="pending" ' . selected( $mail_status, 'pending', false ) . '>' . esc_html__( 'Pendiente', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="sent" ' . selected( $mail_status, 'sent', false ) . '>' . esc_html__( 'Enviado', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="failed" ' . selected( $mail_status, 'failed', false ) . '>' . esc_html__( 'Falló', 'agrocampo-post-venta' ) . '</option>';
        echo '</select></label>';

        echo '<label class="agp-pv-field" for="agp-pv-filter-pdf"><span class="agp-pv-field__label">' . esc_html__( 'Estado PDF', 'agrocampo-post-venta' ) . '</span>';
        echo '<select id="agp-pv-filter-pdf" name="pdf_status">';
        echo '<option value="">' . esc_html__( 'Todos', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="pending" ' . selected( $pdf_status, 'pending', false ) . '>' . esc_html__( 'Pendiente', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="ready" ' . selected( $pdf_status, 'ready', false ) . '>' . esc_html__( 'Listo', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="failed" ' . selected( $pdf_status, 'failed', false ) . '>' . esc_html__( 'Falló', 'agrocampo-post-venta' ) . '</option>';
        echo '</select></label>';

        if ( $this->observations_only ) {
            echo '<label class="agp-pv-field" for="agp-pv-filter-review"><span class="agp-pv-field__label">' . esc_html__( 'Estado revisión', 'agrocampo-post-venta' ) . '</span>';
            echo '<select id="agp-pv-filter-review" name="review_status">';
            echo '<option value="">' . esc_html__( 'Todos', 'agrocampo-post-venta' ) . '</option>';
            echo '<option value="not_reviewed" ' . selected( $review_status, 'not_reviewed', false ) . '>' . esc_html__( 'No revisado', 'agrocampo-post-venta' ) . '</option>';
            echo '<option value="strict_pending_review" ' . selected( $review_status, 'strict_pending_review', false ) . '>' . esc_html__( 'Pendiente de revisión', 'agrocampo-post-venta' ) . '</option>';
            echo '<option value="reviewed" ' . selected( $review_status, 'reviewed', false ) . '>' . esc_html__( 'Revisado', 'agrocampo-post-venta' ) . '</option>';
            echo '<option value="overdue_pending" ' . selected( $review_status, 'overdue_pending', false ) . '>' . esc_html__( 'Pendiente vencida', 'agrocampo-post-venta' ) . '</option>';
            echo '</select></label>';
        }

        echo '<label class="agp-pv-field agp-pv-field--wide" for="agp-pv-filter-email"><span class="agp-pv-field__label">' . esc_html__( 'Correo', 'agrocampo-post-venta' ) . '</span>';
        echo '<input type="text" id="agp-pv-filter-email" name="email" value="' . esc_attr( $email ) . '" placeholder="cliente@correo.cl"></label>';

        echo '<label class="agp-pv-field agp-pv-field--date" for="agp-pv-filter-date-from"><span class="agp-pv-field__label">' . esc_html__( 'Desde', 'agrocampo-post-venta' ) . '</span>';
        echo '<input type="date" id="agp-pv-filter-date-from" name="date_from" value="' . esc_attr( $date_from ) . '"></label>';

        echo '<label class="agp-pv-field agp-pv-field--date" for="agp-pv-filter-date-to"><span class="agp-pv-field__label">' . esc_html__( 'Hasta', 'agrocampo-post-venta' ) . '</span>';
        echo '<input type="date" id="agp-pv-filter-date-to" name="date_to" value="' . esc_attr( $date_to ) . '"></label>';

        echo '<div class="agp-pv-field agp-pv-field--actions">';
        echo '<span class="agp-pv-field__label agp-pv-field__label--ghost">' . esc_html__( 'Acciones', 'agrocampo-post-venta' ) . '</span>';
        echo '<div class="agp-pv-filter-actions">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Aplicar filtros', 'agrocampo-post-venta' ) . '</button>';
        echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=' . $this->manager_page ) ) . '">' . esc_html__( 'Limpiar', 'agrocampo-post-venta' ) . '</a>';
        if ( $this->observations_only ) {
            echo '<a class="button button-secondary agp-pv-button-report" href="' . esc_url( $this->get_observations_export_url() ) . '">' . esc_html__( 'Descargar reporte', 'agrocampo-post-venta' ) . '</a>';
        }
        echo '</div>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    private function get_observations_export_url(): string {
        $query_args = array(
            'action'       => 'agp_pv_export_observations_report',
            'manager_page' => $this->manager_page,
        );

        if ( '' !== $this->filters['search'] ) {
            $query_args['s'] = $this->filters['search'];
        }

        foreach ( array( 'mail_status', 'pdf_status', 'review_status', 'email', 'date_from', 'date_to' ) as $key ) {
            if ( '' !== (string) $this->filters[ $key ] ) {
                $query_args[ $key ] = $this->filters[ $key ];
            }
        }

        $orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( (string) $_GET['orderby'] ) ) : '';
        $order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( (string) $_GET['order'] ) ) : '';
        if ( '' !== $orderby ) {
            $query_args['orderby'] = $orderby;
        }
        if ( '' !== $order ) {
            $query_args['order'] = $order;
        }

        return wp_nonce_url( add_query_arg( $query_args, admin_url( 'admin-post.php' ) ), 'agp_pv_export_observations_report' );
    }

    public function get_filters_from_request(): array {
        return array(
            'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'mail_status' => isset( $_GET['mail_status'] ) ? sanitize_key( wp_unslash( $_GET['mail_status'] ) ) : '',
            'pdf_status' => isset( $_GET['pdf_status'] ) ? sanitize_key( wp_unslash( $_GET['pdf_status'] ) ) : '',
            'review_status' => isset( $_GET['review_status'] ) ? AGP_PV_Plugin::normalize_observation_review_filter( (string) wp_unslash( $_GET['review_status'] ) ) : '',
            'email' => isset( $_GET['email'] ) ? sanitize_text_field( wp_unslash( $_GET['email'] ) ) : '',
            'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
            'date_to' => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
        );
    }

    public function build_where_clause( array $filters ): array {
        global $wpdb;

        $clauses = array();
        $values = array();

        if ( '' !== $filters['search'] ) {
            $like = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $clauses[] = '(tecnico LIKE %s OR cliente LIKE %s OR serie LIKE %s OR numero_interno LIKE %s)';
            array_push( $values, $like, $like, $like, $like );
        }

        if ( in_array( $filters['mail_status'], array( 'pending', 'sent', 'failed' ), true ) ) {
            $clauses[] = 'mail_status = %s';
            $values[] = $filters['mail_status'];
        }

        if ( in_array( $filters['pdf_status'], array( 'pending', 'ready', 'failed' ), true ) ) {
            $clauses[] = 'pdf_status = %s';
            $values[] = $filters['pdf_status'];
        }

        if ( 'not_reviewed' === $filters['review_status'] ) {
            $clauses[] = AGP_PV_Plugin::effective_pending_observation_sql();
        } elseif ( 'strict_pending_review' === $filters['review_status'] ) {
            $clauses[] = AGP_PV_Plugin::strict_pending_observation_sql();
        } elseif ( 'reviewed' === $filters['review_status'] ) {
            $clauses[] = AGP_PV_Plugin::reviewed_observation_sql();
        } elseif ( 'overdue_pending' === $filters['review_status'] ) {
            $clauses[] = AGP_PV_Plugin::effective_pending_observation_sql();
            $clauses[] = 'created_at <= %s';
            $values[]  = AGP_PV_Plugin::get_observations_overdue_cutoff_mysql();
        }

        if ( '' !== $filters['email'] ) {
            $like = '%' . $wpdb->esc_like( $filters['email'] ) . '%';
            $clauses[] = '(email_cliente LIKE %s OR correo_copia LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }

        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'] ) ) {
            $clauses[] = 'DATE(created_at) >= %s';
            $values[] = $filters['date_from'];
        }

        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'] ) ) {
            $clauses[] = 'DATE(created_at) <= %s';
            $values[] = $filters['date_to'];
        }

        if ( $this->observations_only ) {
            $clauses = array_merge( $clauses, AGP_PV_Plugin::observation_presence_sql_clauses() );
            $clauses[] = 'created_at >= %s';
            $values[] = AGP_PV_Plugin::get_observations_report_cutoff_mysql();
        }

        $sql = '';
        if ( ! empty( $clauses ) ) {
            $sql = 'WHERE ' . implode( ' AND ', $clauses );
        }

        return array(
            'sql' => $sql,
            'values' => $values,
        );
    }

    public function get_order_by(): string {
        $allowed = array( 'id', 'tecnico', 'cliente', 'created_at', 'mail_status', 'pdf_status', 'review_status', 'reviewed_at' );
        $orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
        return in_array( $orderby, $allowed, true ) ? $orderby : 'created_at';
    }

    public function get_order_direction(): string {
        $order = isset( $_GET['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
        return in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
    }

    private function process_bulk_action(): void {
        $action = $this->current_action();
        if ( ! $action ) {
            return;
        }

        check_admin_referer( 'bulk-' . $this->_args['plural'] );

        $ids = isset( $_REQUEST['submission'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['submission'] ) ) : array();
        $ids = array_values( array_filter( $ids ) );

        if ( empty( $ids ) ) {
            return;
        }

        if ( 'bulk_delete' === $action ) {
            $this->bulk_delete( $ids );
            return;
        }

        if ( 'bulk_resend' === $action ) {
            $this->bulk_resend_email( $ids );
            return;
        }

        if ( 'bulk_regenerate_pdf' === $action ) {
            $this->bulk_regenerate_pdf( $ids );
            return;
        }

        if ( 'bulk_export_csv' === $action ) {
            $this->bulk_export_csv( $ids );
            return;
        }
    }

    private function bulk_delete( array $ids ): void {
        global $wpdb;
        $table = AGP_PV_DB::table_name();

        $deleted = 0;
        foreach ( $ids as $id ) {
            $submission = AGP_PV_Email::get_submission( $id );
            if ( ! $submission ) {
                continue;
            }

            if ( ! empty( $submission['pdf_attachment_id'] ) ) {
                wp_delete_attachment( (int) $submission['pdf_attachment_id'], true );
            }

            $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
            if ( false !== $result ) {
                $deleted++;
            }
        }

        wp_safe_redirect(
            admin_url( 'admin.php?page=' . $this->manager_page . '&agp_pv_notice=bulk_deleted&agp_pv_notice_count=' . $deleted )
        );
        exit;
    }

    private function bulk_resend_email( array $ids ): void {
        $processed = 0;
        foreach ( $ids as $id ) {
            $result = AGP_PV_Email::send_submission_email( $id );
            if ( ! empty( $result['mail_sent'] ) ) {
                $processed++;
            }
        }

        wp_safe_redirect(
            admin_url( 'admin.php?page=' . $this->manager_page . '&agp_pv_notice=bulk_resend&agp_pv_notice_count=' . $processed )
        );
        exit;
    }

    private function bulk_regenerate_pdf( array $ids ): void {
        $processed = 0;
        foreach ( $ids as $id ) {
            $result = AGP_PV_PDF::regenerate_pdf_attachment( $id );
            if ( ! empty( $result['ok'] ) ) {
                $processed++;
            }
        }

        wp_safe_redirect(
            admin_url( 'admin.php?page=' . $this->manager_page . '&agp_pv_notice=bulk_regenerated&agp_pv_notice_count=' . $processed )
        );
        exit;
    }

    private function bulk_export_csv( array $ids ): void {
        global $wpdb;
        $table = AGP_PV_DB::table_name();

        $ids_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $query = $wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ({$ids_placeholders}) ORDER BY created_at DESC", $ids );
        $rows = $wpdb->get_results( $query, ARRAY_A );

        if ( empty( $rows ) ) {
            return;
        }

        $this->stream_legacy_compatible_csv( $rows, 'informes-tecnicos-seleccionados' );
    }


    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function stream_legacy_compatible_csv( array $rows, string $file_prefix ): void {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $file_prefix ) . '-' . gmdate( 'Ymd-His' ) . '.csv' );

        $output = fopen( 'php://output', 'w' );
        if ( false === $output ) {
            return;
        }

        $headers = $this->legacy_export_headers();
        fputcsv( $output, $headers );

        foreach ( $rows as $row ) {
            $legacy_row = $this->map_submission_to_legacy_export_row( $row );
            $ordered = array();
            foreach ( $headers as $header ) {
                $ordered[] = $this->escape_csv_formula_injection( (string) ( $legacy_row[ $header ] ?? '' ) );
            }
            fputcsv( $output, $ordered );
        }

        fclose( $output );
        exit;
    }

    /**
     * @return string[]
     */
    private function legacy_export_headers(): array {
        return array(
            'legacy_id',
            'Técnico',
            'Cliente',
            'Correo cliente',
            'Address - Faena Lugar',
            'Máquina',
            'Modelo',
            'Serie',
            'N° Interno',
            'Fecha',
            'Horas',
            'Tipo de servicio',
            'Tipo de mantención',
            'Cantidad de horas',
            'Estado de la máquina',
            'Fecha a contactar',
            'Fecha reparación',
            'Fecha cierre',
            'Lubricantes',
            'Filtros utilizados',
            'Componentes utilizados',
            'Trabajos realizados',
            'Observaciones',
            'Correo copia',
            'Hora de envío',
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,string>
     */
    private function map_submission_to_legacy_export_row( array $row ): array {
        $legacy_id = isset( $row['legacy_id'] ) ? absint( (string) $row['legacy_id'] ) : 0;
        if ( $legacy_id <= 0 ) {
            $legacy_id = isset( $row['id'] ) ? absint( (string) $row['id'] ) : 0;
        }

        $tipo_servicio_label = trim( (string) ( $row['tipo_servicio_label'] ?? '' ) );
        if ( '' === $tipo_servicio_label ) {
            $tipo_servicio_label = $this->map_stored_tipo_servicio_to_legacy_label( (string) ( $row['tipo_servicio'] ?? '' ) );
        }

        $tipo_mantencion_label = trim( (string) ( $row['tipo_mantencion_label'] ?? '' ) );
        if ( '' === $tipo_mantencion_label ) {
            $tipo_mantencion_label = $this->map_stored_tipo_mantencion_to_legacy_label( (string) ( $row['tipo_mantencion'] ?? '' ) );
        }

        return array(
            'legacy_id' => (string) $legacy_id,
            'Técnico' => sanitize_text_field( (string) ( $row['tecnico'] ?? '' ) ),
            'Cliente' => sanitize_text_field( (string) ( $row['cliente'] ?? '' ) ),
            'Correo cliente' => sanitize_email( (string) ( $row['email_cliente'] ?? '' ) ),
            'Address - Faena Lugar' => sanitize_text_field( (string) ( $row['faena_lugar'] ?? '' ) ),
            'Máquina' => sanitize_text_field( (string) ( $row['maquina'] ?? '' ) ),
            'Modelo' => sanitize_text_field( (string) ( $row['modelo'] ?? '' ) ),
            'Serie' => sanitize_text_field( (string) ( $row['serie'] ?? '' ) ),
            'N° Interno' => sanitize_text_field( (string) ( $row['numero_interno'] ?? '' ) ),
            'Fecha' => sanitize_text_field( (string) ( $row['fecha'] ?? '' ) ),
            'Horas' => sanitize_text_field( (string) ( $row['horas'] ?? '' ) ),
            'Tipo de servicio' => sanitize_text_field( $tipo_servicio_label ),
            'Tipo de mantención' => sanitize_text_field( $tipo_mantencion_label ),
            'Cantidad de horas' => sanitize_text_field( (string) ( $row['cantidad_horas'] ?? '' ) ),
            'Estado de la máquina' => sanitize_text_field( AGP_PV_Plugin::get_machine_status_label( (string) ( $row['estado_maquina'] ?? '' ) ) ),
            'Fecha a contactar' => sanitize_text_field( (string) ( $row['fecha_contacto'] ?? '' ) ),
            'Fecha reparación' => sanitize_text_field( (string) ( $row['fecha_reparacion'] ?? '' ) ),
            'Fecha cierre' => sanitize_text_field( (string) ( $row['fecha_cierre'] ?? '' ) ),
            'Lubricantes' => AGP_PV_Plugin::resolve_lubricants_text_from_submission( $row ),
            'Filtros utilizados' => sanitize_textarea_field( (string) ( $row['filtros_utilizados'] ?? '' ) ),
            'Componentes utilizados' => sanitize_textarea_field( (string) ( $row['componentes_utilizados'] ?? '' ) ),
            'Trabajos realizados' => sanitize_textarea_field( (string) ( $row['trabajos_realizados'] ?? '' ) ),
            'Observaciones' => sanitize_textarea_field( (string) ( $row['observaciones'] ?? '' ) ),
            'Correo copia' => sanitize_email( (string) ( $row['correo_copia'] ?? '' ) ),
            'Hora de envío' => sanitize_text_field( (string) ( $row['created_at'] ?? '' ) ),
        );
    }

    private function map_stored_tipo_servicio_to_legacy_label( string $value ): string {
        $map = array(
            'one' => __( 'Factura Cliente', 'agrocampo-post-venta' ),
            'two' => __( 'Garantía', 'agrocampo-post-venta' ),
            'Interno' => __( 'Mantención', 'agrocampo-post-venta' ),
            'Visita-de-Cortesía' => __( 'Visita de Cortesía', 'agrocampo-post-venta' ),
            'Diagnostico-Técnico' => __( 'Diagnóstico Técnico', 'agrocampo-post-venta' ),
            'Entrega-Técnica' => __( 'Entrega Técnica', 'agrocampo-post-venta' ),
        );

        return $map[ $value ] ?? sanitize_text_field( $value );
    }

    private function map_stored_tipo_mantencion_to_legacy_label( string $value ): string {
        $map = array(
            'one' => __( '100 Horas', 'agrocampo-post-venta' ),
            'two' => __( '400 Horas', 'agrocampo-post-venta' ),
            '500-Horas' => __( '500 Horas', 'agrocampo-post-venta' ),
            '800-Horas' => __( '800 Horas', 'agrocampo-post-venta' ),
            '1000-Horas' => __( '1000 Horas', 'agrocampo-post-venta' ),
            '1200' => __( '1200 Horas', 'agrocampo-post-venta' ),
            '1600-Horas' => __( '1500 Horas', 'agrocampo-post-venta' ),
            'OTRO' => __( 'OTRO', 'agrocampo-post-venta' ),
        );

        return $map[ $value ] ?? sanitize_text_field( $value );
    }

    private function escape_csv_formula_injection( string $value ): string {
        if ( '' === $value ) {
            return $value;
        }

        $first = substr( $value, 0, 1 );
        if ( in_array( $first, array( '=', '+', '-', '@' ), true ) ) {
            return "'" . $value;
        }

        return $value;
    }


    private function get_view_pdf_url( int $submission_id ): string {
        return wp_nonce_url(
            admin_url( 'admin.php?page=' . $this->manager_page . '&view=' . $submission_id ),
            'agp_pv_view_pdf_' . $submission_id
        );
    }

    private function get_observation_preview_parts( string $text, int $limit = 150 ): array {
        $normalized = AGP_PV_Plugin::normalize_observation_text( $text );
        $plain      = trim( preg_replace( '/\s+/u', ' ', $normalized ) ?? $normalized );

        if ( '' === $plain ) {
            return array(
                'preview' => '',
                'full'    => '',
                'is_long' => false,
            );
        }

        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $plain ) : strlen( $plain );
        if ( $length <= $limit ) {
            return array(
                'preview' => $plain,
                'full'    => $normalized,
                'is_long' => false,
            );
        }

        $preview = function_exists( 'mb_substr' ) ? mb_substr( $plain, 0, $limit ) : substr( $plain, 0, $limit );

        return array(
            'preview' => rtrim( $preview, " .,:; \n\r\t" ) . '…',
            'full'    => $normalized,
            'is_long' => true,
        );
    }

    public function column_observaciones_preview( $item ): string {
        $parts = $this->get_observation_preview_parts( (string) ( $item['observaciones'] ?? '' ) );
        if ( '' === $parts['preview'] ) {
            return '&mdash;';
        }

        $html  = '<div class="agp-pv-observation-stack">';
        $html .= '<div class="agp-pv-observation-preview" title="' . esc_attr( $parts['full'] ) . '">' . esc_html( $parts['preview'] ) . '</div>';

        if ( ! empty( $parts['is_long'] ) ) {
            $html .= '<details class="agp-pv-observation-more">';
            $html .= '<summary>' . esc_html__( 'Ver más', 'agrocampo-post-venta' ) . '</summary>';
            $html .= '<div class="agp-pv-observation-full">' . nl2br( esc_html( $parts['full'] ) ) . '</div>';
            $html .= '</details>';
        }

        $html .= '</div>';

        return $html;
    }

    public function column_default( $item, $column_name ) {
        if ( 'mail_status' === $column_name ) {
            return '<span class="agp-pv-status agp-pv-status-' . esc_attr( $item['mail_status'] ?? '' ) . '">' . esc_html( $this->format_status( (string) ( $item['mail_status'] ?? '' ) ) ) . '</span>';
        }

        if ( 'review_status' === $column_name ) {
            $review_status_value = (string) ( $item['review_status'] ?? '' );
            $status_slug         = AGP_PV_Plugin::is_reviewed_observation_status( $review_status_value ) ? 'reviewed' : ( AGP_PV_Plugin::is_overdue_observation_row( (array) $item ) ? 'overdue_pending' : 'not_reviewed' );
            $html                = '<div class="agp-pv-status-stack">';
            $html               .= '<span class="agp-pv-status agp-pv-status-' . esc_attr( $status_slug ) . '">' . esc_html( $this->format_status( $review_status_value, (array) $item ) ) . '</span>';

            if ( AGP_PV_Plugin::is_reviewed_observation_status( $review_status_value ) && AGP_PV_Plugin::has_appended_observation_notes( (string) ( $item['observaciones'] ?? '' ) ) ) {
                $html .= '<span class="agp-pv-status agp-pv-status-note_update">' . esc_html__( 'Nueva observación', 'agrocampo-post-venta' ) . '</span>';
            }

            $html .= '</div>';

            return $html;
        }

        if ( 'reviewed_by' === $column_name ) {
            $user_id = isset( $item['reviewed_by'] ) ? absint( $item['reviewed_by'] ) : 0;
            if ( $user_id <= 0 ) {
                return '&mdash;';
            }

            $user = get_user_by( 'id', $user_id );
            if ( ! $user ) {
                return sprintf( '#%d', $user_id );
            }

            return esc_html( $user->display_name );
        }

        if ( 'reviewed_at' === $column_name ) {
            $reviewed_at = isset( $item['reviewed_at'] ) ? (string) $item['reviewed_at'] : '';
            if ( '' === $reviewed_at || '0000-00-00 00:00:00' === $reviewed_at ) {
                return '&mdash;';
            }

            return esc_html( $reviewed_at );
        }

        if ( 'pdf_status' === $column_name ) {
            return '<span class="agp-pv-status agp-pv-status-' . esc_attr( $item['pdf_status'] ?? '' ) . '">' . esc_html( $this->format_status( (string) ( $item['pdf_status'] ?? '' ) ) ) . '</span>';
        }

        if ( 'pdf_metrics' === $column_name ) {
            $elapsed_ms = isset( $item['pdf_generated_ms'] ) ? max( 0, (int) $item['pdf_generated_ms'] ) : 0;
            $size_bytes = isset( $item['pdf_size_bytes'] ) ? max( 0, (int) $item['pdf_size_bytes'] ) : 0;
            $page_count = isset( $item['pdf_page_count'] ) ? max( 0, (int) $item['pdf_page_count'] ) : 0;

            $parts = array();
            if ( $elapsed_ms > 0 ) {
                $parts[] = sprintf( __( '%d ms', 'agrocampo-post-venta' ), $elapsed_ms );
            }
            if ( $size_bytes > 0 ) {
                $parts[] = size_format( $size_bytes, 1 );
            }
            if ( $page_count > 0 ) {
                $parts[] = sprintf( _n( '%d página', '%d páginas', $page_count, 'agrocampo-post-venta' ), $page_count );
            }

            if ( empty( $parts ) ) {
                return '&mdash;';
            }

            return esc_html( implode( ' · ', $parts ) );
        }

        if ( 'created_at' === $column_name ) {
            return esc_html( $item['created_at'] ?? '' );
        }

        return esc_html( $item[ $column_name ] ?? '' );
    }

    public function column_id( $item ): string {
        $submission_id = absint( $item['id'] );
        $display_id    = AGP_PV_DB::get_visible_report_id( $item );
        $actions       = array();

        $actions[] = sprintf(
            '<a class="agp-pv-row-menu__link" href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=' . $this->manager_page . '&submission_id=' . $submission_id ) ),
            esc_html__( 'Ver detalle', 'agrocampo-post-venta' )
        );

        $actions[] = sprintf(
            '<a class="agp-pv-row-menu__link" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( $this->get_view_pdf_url( $submission_id ) ),
            esc_html__( 'Ver PDF', 'agrocampo-post-venta' )
        );

        $actions[] = sprintf(
            '<a class="agp-pv-row-menu__link" href="%s">%s</a>',
            esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=agp_pv_resend_email&submission_id=' . $submission_id . '&manager_page=' . $this->manager_page ), 'agp_pv_resend_email_' . $submission_id ) ),
            esc_html__( 'Reintentar correo', 'agrocampo-post-venta' )
        );

        $actions[] = sprintf(
            '<a class="agp-pv-row-menu__link" href="%s">%s</a>',
            esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=agp_pv_regenerate_pdf&submission_id=' . $submission_id . '&manager_page=' . $this->manager_page ), 'agp_pv_regenerate_pdf_' . $submission_id ) ),
            esc_html__( 'Regenerar PDF', 'agrocampo-post-venta' )
        );

        if ( $this->observations_only && ! AGP_PV_Plugin::is_reviewed_observation_status( (string) ( $item['review_status'] ?? '' ) ) ) {
            $actions[] = sprintf(
                '<a class="agp-pv-row-menu__link is-primary" href="%s">%s</a>',
                esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=agp_pv_mark_reviewed&submission_id=' . $submission_id . '&manager_page=' . $this->manager_page ), 'agp_pv_mark_reviewed_' . $submission_id ) ),
                esc_html__( 'Marcar revisado', 'agrocampo-post-venta' )
            );
        }

        if ( $this->observations_only && AGP_PV_Plugin::is_reviewed_observation_status( (string) ( $item['review_status'] ?? '' ) ) ) {
            $actions[] = sprintf(
                '<a class="agp-pv-row-menu__link is-primary" href="%s">%s</a>',
                esc_url( admin_url( 'admin.php?page=' . $this->manager_page . '&submission_id=' . $submission_id ) . '#agp-pv-append-observation' ),
                esc_html__( 'Agregar observación', 'agrocampo-post-venta' )
            );
        }

        $html  = '<div class="agp-pv-admin-id-cell">';
        $html .= '<span class="agp-pv-admin-id-chip">#' . esc_html( (string) $display_id ) . '</span>';
        $html .= '<details class="agp-pv-row-menu">';
        $html .= '<summary>' . esc_html__( 'Acciones', 'agrocampo-post-venta' ) . '</summary>';
        $html .= '<div class="agp-pv-row-menu__panel">' . implode( '', $actions ) . '</div>';
        $html .= '</details>';
        $html .= '</div>';

        return $html;
    }

    public function single_row( $item ): void {
        $row_classes = array( 'agp-pv-admin-row' );
        if ( $this->observations_only ) {
            if ( AGP_PV_Plugin::is_reviewed_observation_status( (string) ( $item['review_status'] ?? '' ) ) ) {
                $row_classes[] = 'is-reviewed';
                if ( AGP_PV_Plugin::has_appended_observation_notes( (string) ( $item['observaciones'] ?? '' ) ) ) {
                    $row_classes[] = 'has-updated-note';
                }
            } elseif ( AGP_PV_Plugin::is_overdue_observation_row( (array) $item ) ) {
                $row_classes[] = 'is-overdue';
            } elseif ( AGP_PV_Plugin::has_meaningful_observation_text( (string) ( $item['observaciones'] ?? '' ) ) ) {
                $row_classes[] = 'is-pending';
            }
        }

        echo '<tr class="' . esc_attr( implode( ' ', $row_classes ) ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $this->single_row_columns( $item );
        echo '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ( isset( $_GET['submission_id'] ) && absint( $_GET['submission_id'] ) === (int) $item['id'] ) {
            $colspan = count( $this->get_columns() );
            echo '<tr class="agp-pv-detail"><td colspan="' . esc_attr( (string) $colspan ) . '">';
            echo $this->render_detail( (int) $item['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    private function render_detail( int $submission_id ): string {
        $submission = AGP_PV_Email::get_submission( $submission_id );
        if ( ! $submission ) {
            return esc_html__( 'No se encontró el envío.', 'agrocampo-post-venta' );
        }

        $fields = array();
        $machine_status_label = AGP_PV_Plugin::get_machine_status_label( (string) ( $submission['estado_maquina'] ?? '' ) );
        if ( '' !== $machine_status_label ) {
            $submission['estado_maquina'] = $machine_status_label;
        }

        $submission['lubricantes'] = AGP_PV_Plugin::resolve_lubricants_text_from_submission( $submission );

        foreach ( $submission as $key => $value ) {
            if ( 'fotos_ids' === $key ) {
                continue;
            }

            $fields[] = '<div class="agp-pv-detail-field"><span class="agp-pv-detail-field__label">' . esc_html( $key ) . '</span><div class="agp-pv-detail-field__value">' . nl2br( esc_html( (string) $value ) ) . '</div></div>';
        }

        $output  = '<div class="agp-pv-detail-card">';
        $output .= '<div class="agp-pv-detail-card__header">';
        $output .= '<div><span class="agp-pv-detail-card__eyebrow">' . esc_html__( 'Detalle del envío', 'agrocampo-post-venta' ) . '</span><h3>' . sprintf( esc_html__( 'Informe #%d', 'agrocampo-post-venta' ), $submission_id ) . '</h3></div>';
        $output .= '<a class="button button-secondary" href="' . esc_url( $this->get_view_pdf_url( $submission_id ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Ver PDF', 'agrocampo-post-venta' ) . '</a>';
        $output .= '</div>';
        $output .= '<div class="agp-pv-detail-grid">' . implode( '', $fields ) . '</div>';

        if ( ! empty( $submission['pdf_attachment_id'] ) || ! empty( $submission['id'] ) ) {
            $elapsed_ms = isset( $submission['pdf_generated_ms'] ) ? max( 0, (int) $submission['pdf_generated_ms'] ) : 0;
            $size_bytes = isset( $submission['pdf_size_bytes'] ) ? max( 0, (int) $submission['pdf_size_bytes'] ) : 0;
            $page_count = isset( $submission['pdf_page_count'] ) ? max( 0, (int) $submission['pdf_page_count'] ) : 0;
            $warnings   = json_decode( (string) ( $submission['pdf_warnings'] ?? '' ), true );
            if ( ! is_array( $warnings ) ) {
                $warnings = array();
            }

            $metrics = array();
            if ( $elapsed_ms > 0 ) {
                $metrics[] = sprintf( esc_html__( '%d ms', 'agrocampo-post-venta' ), $elapsed_ms );
            }
            if ( $size_bytes > 0 ) {
                $metrics[] = size_format( $size_bytes, 1 );
            }
            if ( $page_count > 0 ) {
                $metrics[] = sprintf( _n( '%d página', '%d páginas', $page_count, 'agrocampo-post-venta' ), $page_count );
            }

            $output .= '<div class="agp-pv-detail-section">';
            $output .= '<strong>' . esc_html__( 'Métricas PDF', 'agrocampo-post-venta' ) . '</strong>';
            $output .= '<div class="agp-pv-detail-section__body">' . ( ! empty( $metrics ) ? esc_html( implode( ' · ', $metrics ) ) : '&mdash;' ) . '</div>';
            if ( ! empty( $warnings ) ) {
                $warning_items = array_map(
                    static function ( $warning ) {
                        return '<li>' . esc_html( (string) $warning ) . '</li>';
                    },
                    $warnings
                );
                $output .= '<ul class="agp-pv-detail-list">' . implode( '', $warning_items ) . '</ul>';
            }
            $output .= '</div>';
        }

        $fotos = json_decode( (string) $submission['fotos_ids'], true );
        if ( is_array( $fotos ) && ! empty( $fotos ) ) {
            $photo_links = array();
            foreach ( $fotos as $foto_id ) {
                $url = wp_get_attachment_url( (int) $foto_id );
                if ( $url ) {
                    $photo_links[] = '<a class="agp-pv-detail-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver foto', 'agrocampo-post-venta' ) . '</a>';
                }
            }
            if ( ! empty( $photo_links ) ) {
                $output .= '<div class="agp-pv-detail-section"><strong>' . esc_html__( 'Fotos adjuntas', 'agrocampo-post-venta' ) . '</strong><div class="agp-pv-detail-links">' . implode( '', $photo_links ) . '</div></div>';
            }
        }

        if ( AGP_PV_Plugin::is_reviewed_observation_status( (string) ( $submission['review_status'] ?? '' ) ) ) {
            $output .= '<div class="agp-pv-detail-section agp-pv-detail-section--note" id="agp-pv-append-observation">';
            $output .= '<strong>' . esc_html__( 'Agregar observación', 'agrocampo-post-venta' ) . '</strong>';
            $output .= '<p class="description">' . esc_html__( 'La nueva observación se anexa al historial del informe sin cambiarlo de resuelto.', 'agrocampo-post-venta' ) . '</p>';
            $output .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="agp-pv-detail-note-form">';
            $output .= '<input type="hidden" name="action" value="agp_pv_append_observation">';
            $output .= '<input type="hidden" name="manager_page" value="' . esc_attr( $this->manager_page ) . '">';
            $output .= '<input type="hidden" name="submission_id" value="' . esc_attr( (string) $submission_id ) . '">';
            $output .= wp_nonce_field( 'agp_pv_append_observation_' . $submission_id, '_wpnonce', true, false );
            $output .= '<textarea name="observation_note" rows="4" required placeholder="' . esc_attr__( 'Escribe la nueva observación para anexarla al historial del informe.', 'agrocampo-post-venta' ) . '"></textarea>';
            $output .= '<button type="submit" class="button button-primary">' . esc_html__( 'Guardar observación', 'agrocampo-post-venta' ) . '</button>';
            $output .= '</form>';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    private function format_status( string $status, array $context = array() ): string {
        if ( 'sent' === $status || 'ready' === $status ) {
            return __( 'Listo', 'agrocampo-post-venta' );
        }
        if ( 'failed' === $status ) {
            return __( 'Falló', 'agrocampo-post-venta' );
        }
        if ( 'pending' === $status ) {
            return __( 'Pendiente', 'agrocampo-post-venta' );
        }
        if ( AGP_PV_Plugin::is_reviewed_observation_status( $status ) ) {
            return __( 'Revisado', 'agrocampo-post-venta' );
        }

        if ( ! empty( $context ) && AGP_PV_Plugin::has_meaningful_observation_text( (string) ( $context['observaciones'] ?? '' ) ) ) {
            if ( AGP_PV_Plugin::is_overdue_observation_row( $context ) ) {
                return __( 'Pendiente vencida', 'agrocampo-post-venta' );
            }

            return __( 'No revisado', 'agrocampo-post-venta' );
        }

        if ( 'pending_review' === $status ) {
            return __( 'Pendiente de revisión', 'agrocampo-post-venta' );
        }
        if ( 'not_required' === $status ) {
            return __( 'No requiere revisión', 'agrocampo-post-venta' );
        }

        return $status;
    }
}
