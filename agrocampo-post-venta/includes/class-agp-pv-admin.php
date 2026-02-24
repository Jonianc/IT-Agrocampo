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
        add_action( 'admin_post_agp_pv_send_test_email', array( $this, 'handle_send_test_email' ) );
        add_action( 'admin_post_agp_pv_save_recipients', array( $this, 'handle_save_recipients' ) );
        add_action( 'admin_post_agp_pv_save_logo', array( $this, 'handle_save_logo' ) );
        add_action( 'admin_post_agp_pv_import_legacy_csv', array( $this, 'handle_import_legacy_csv' ) );
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
            __( 'Ajustes', 'agrocampo-post-venta' ),
            __( 'Ajustes', 'agrocampo-post-venta' ),
            'manage_options',
            'agp-pv-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function render_list_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $table = new AGP_PV_Submissions_Table();
        $table->prepare_items();

        echo '<div class="wrap agp-pv-admin">';
        echo '<h1>' . esc_html__( 'Gestor de informes técnicos', 'agrocampo-post-venta' ) . '</h1>';
        echo '<p class="description">' . esc_html__( 'Busca, filtra y ejecuta acciones sobre los informes enviados.', 'agrocampo-post-venta' ) . '</p>';

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

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $logo_id = absint( get_option( 'agp_pv_logo_attachment_id', 0 ) );
        $logo_width = (float) get_option( 'agp_pv_logo_width_mm', 38 );
        $logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';
        $recipients = AGP_PV_Email::get_configured_recipients();
        $recipients_value = implode( ', ', $recipients );

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
        echo '<h2>' . esc_html__( 'Logo del PDF', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Este logo se mostrará en la cabecera del PDF generado.', 'agrocampo-post-venta' ) . '</p>';
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
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Guardar logo', 'agrocampo-post-venta' ) . '</button></p>';
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

        if ( $message ) {
            echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>' . esc_html( $message ) . '</strong></p></div>';
        }
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
        if ( ! $submission_id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-submissions' ) );
            exit;
        }

        if ( ! $this->verify_submission_action_nonce( 'agp_pv_resend_email', $submission_id ) ) {
            wp_die( esc_html__( 'Enlace inválido o expirado.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
        }
        if ( $submission_id ) {
            $result = AGP_PV_Email::send_submission_email( $submission_id );
            if ( empty( $result['mail_sent'] ) ) {
                $message = $result['mail_error'] ?? __( 'No se pudo enviar el correo.', 'agrocampo-post-venta' );
                wp_safe_redirect(
                    admin_url( 'admin.php?page=agp-pv-submissions&agp_pv_notice=mail_failed&agp_pv_notice_message=' . rawurlencode( $message ) )
                );
                exit;
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-submissions&agp_pv_notice=mail_sent' ) );
        exit;
    }

    public function handle_regenerate_pdf(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $submission_id = isset( $_GET['submission_id'] ) ? absint( $_GET['submission_id'] ) : 0;
        if ( ! $submission_id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-submissions' ) );
            exit;
        }

        if ( ! $this->verify_submission_action_nonce( 'agp_pv_regenerate_pdf', $submission_id ) ) {
            wp_die( esc_html__( 'Enlace inválido o expirado.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
        }

        $result = AGP_PV_PDF::regenerate_pdf_attachment( $submission_id );
        if ( empty( $result['ok'] ) ) {
            wp_safe_redirect(
                admin_url( 'admin.php?page=agp-pv-submissions&agp_pv_notice=pdf_failed&agp_pv_notice_message=' . rawurlencode( $result['message'] ?? '' ) )
            );
            exit;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-submissions&agp_pv_notice=pdf_regenerated' ) );
        exit;
    }


    public function handle_view_pdf_request(): void {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'agp-pv-submissions' !== $page ) {
            return;
        }

        $view_id = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;
        if ( ! $view_id ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'agp_pv_view_pdf_' . $view_id ) ) {
            wp_die( esc_html__( 'Enlace de PDF inválido o expirado.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
        }

        $submission = AGP_PV_Email::get_submission( $view_id );
        if ( ! $submission ) {
            wp_die( esc_html__( 'No se encontró el informe solicitado.', 'agrocampo-post-venta' ), esc_html__( 'Informe no encontrado', 'agrocampo-post-venta' ), array( 'response' => 404 ) );
        }

        $pdf_result = AGP_PV_PDF::ensure_pdf_attachment( $view_id, $submission );
        if ( empty( $pdf_result['status'] ) || 'ready' !== $pdf_result['status'] ) {
            wp_die( esc_html( $pdf_result['message'] ?? __( 'No fue posible generar el PDF.', 'agrocampo-post-venta' ) ), esc_html__( 'Error al generar PDF', 'agrocampo-post-venta' ), array( 'response' => 500 ) );
        }

        $pdf_path = isset( $pdf_result['path'] ) ? (string) $pdf_result['path'] : '';
        if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
            wp_die( esc_html__( 'No se encontró el archivo PDF en el servidor.', 'agrocampo-post-venta' ), esc_html__( 'PDF no disponible', 'agrocampo-post-venta' ), array( 'response' => 404 ) );
        }

        $pdf_bytes = file_get_contents( $pdf_path );
        if ( false === $pdf_bytes ) {
            wp_die( esc_html__( 'No fue posible leer el PDF generado.', 'agrocampo-post-venta' ), esc_html__( 'Error de lectura', 'agrocampo-post-venta' ), array( 'response' => 500 ) );
        }

        $report_id = AGP_PV_DB::get_visible_report_id( $submission );
        $filename = 'informe-tecnico-' . ( $report_id > 0 ? $report_id : $view_id ) . '.pdf';

        while ( ob_get_level() ) {
            ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'Content-Length: ' . (string) strlen( $pdf_bytes ) );

        echo $pdf_bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

    public function handle_save_logo(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_save_logo' );

        $logo_id = isset( $_POST['agp_pv_logo_attachment_id'] ) ? absint( $_POST['agp_pv_logo_attachment_id'] ) : 0;
        $width = isset( $_POST['agp_pv_logo_width_mm'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['agp_pv_logo_width_mm'] ) ) : 38.0;

        update_option( 'agp_pv_logo_attachment_id', $logo_id );
        update_option( 'agp_pv_logo_width_mm', $width > 0 ? $width : 38.0 );

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
            'fecha_reparacion' => sanitize_text_field( $this->legacy_value( $row, 'fecha reparacion' ) ),
            'fecha_cierre' => sanitize_text_field( $this->legacy_value( $row, 'fecha cierre' ) ),
            'lubricantes' => sanitize_textarea_field( $this->legacy_value( $row, 'lubricantes' ) ),
            'filtros_utilizados' => sanitize_textarea_field( $this->legacy_value( $row, 'filtros utilizados' ) ),
            'componentes_utilizados' => sanitize_textarea_field( $this->legacy_value( $row, 'componentes utilizados' ) ),
            'trabajos_realizados' => sanitize_textarea_field( $this->legacy_value( $row, 'trabajos realizados' ) ),
            'observaciones' => sanitize_textarea_field( $this->legacy_value( $row, 'observaciones' ) ),
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
            '%s', // fecha_reparacion
            '%s', // fecha_cierre
            '%s', // lubricantes
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

    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'agp_pv_submission',
                'plural'   => 'agp_pv_submissions',
                'ajax'     => false,
            )
        );
    }

    public function get_columns(): array {
        return array(
            'cb' => '<input type="checkbox" />',
            'id' => __( 'ID', 'agrocampo-post-venta' ),
            'tecnico' => __( 'Técnico', 'agrocampo-post-venta' ),
            'cliente' => __( 'Cliente', 'agrocampo-post-venta' ),
            'email_cliente' => __( 'Correo cliente', 'agrocampo-post-venta' ),
            'serie' => __( 'Serie', 'agrocampo-post-venta' ),
            'tipo_servicio' => __( 'Tipo de servicio', 'agrocampo-post-venta' ),
            'mail_status' => __( 'Correo', 'agrocampo-post-venta' ),
            'pdf_status' => __( 'PDF', 'agrocampo-post-venta' ),
            'pdf_metrics' => __( 'Métricas PDF', 'agrocampo-post-venta' ),
            'created_at' => __( 'Fecha', 'agrocampo-post-venta' ),
        );
    }

    protected function get_sortable_columns(): array {
        return array(
            'id' => array( 'id', false ),
            'tecnico' => array( 'tecnico', false ),
            'cliente' => array( 'cliente', false ),
            'created_at' => array( 'created_at', true ),
            'mail_status' => array( 'mail_status', false ),
            'pdf_status' => array( 'pdf_status', false ),
        );
    }

    public function get_bulk_actions(): array {
        return array(
            'bulk_resend' => __( 'Reintentar envío de correo', 'agrocampo-post-venta' ),
            'bulk_regenerate_pdf' => __( 'Regenerar PDF', 'agrocampo-post-venta' ),
            'bulk_export_csv' => __( 'Exportar CSV', 'agrocampo-post-venta' ),
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
        $mail_status = $this->filters['mail_status'];
        $pdf_status = $this->filters['pdf_status'];
        $email = $this->filters['email'];
        $date_from = $this->filters['date_from'];
        $date_to = $this->filters['date_to'];

        echo '<div class="agp-pv-toolbar">';
        echo '<div class="agp-pv-filter-row">';

        echo '<label for="agp-pv-filter-mail"><strong>' . esc_html__( 'Estado correo', 'agrocampo-post-venta' ) . '</strong></label>';
        echo '<select id="agp-pv-filter-mail" name="mail_status">';
        echo '<option value="">' . esc_html__( 'Todos', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="pending" ' . selected( $mail_status, 'pending', false ) . '>' . esc_html__( 'Pendiente', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="sent" ' . selected( $mail_status, 'sent', false ) . '>' . esc_html__( 'Enviado', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="failed" ' . selected( $mail_status, 'failed', false ) . '>' . esc_html__( 'Falló', 'agrocampo-post-venta' ) . '</option>';
        echo '</select>';

        echo '<label for="agp-pv-filter-pdf"><strong>' . esc_html__( 'Estado PDF', 'agrocampo-post-venta' ) . '</strong></label>';
        echo '<select id="agp-pv-filter-pdf" name="pdf_status">';
        echo '<option value="">' . esc_html__( 'Todos', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="pending" ' . selected( $pdf_status, 'pending', false ) . '>' . esc_html__( 'Pendiente', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="ready" ' . selected( $pdf_status, 'ready', false ) . '>' . esc_html__( 'Listo', 'agrocampo-post-venta' ) . '</option>';
        echo '<option value="failed" ' . selected( $pdf_status, 'failed', false ) . '>' . esc_html__( 'Falló', 'agrocampo-post-venta' ) . '</option>';
        echo '</select>';

        echo '<label for="agp-pv-filter-email"><strong>' . esc_html__( 'Correo', 'agrocampo-post-venta' ) . '</strong></label>';
        echo '<input type="text" id="agp-pv-filter-email" name="email" value="' . esc_attr( $email ) . '" placeholder="cliente@correo.cl">';

        echo '<label for="agp-pv-filter-date-from"><strong>' . esc_html__( 'Desde', 'agrocampo-post-venta' ) . '</strong></label>';
        echo '<input type="date" id="agp-pv-filter-date-from" name="date_from" value="' . esc_attr( $date_from ) . '">';

        echo '<label for="agp-pv-filter-date-to"><strong>' . esc_html__( 'Hasta', 'agrocampo-post-venta' ) . '</strong></label>';
        echo '<input type="date" id="agp-pv-filter-date-to" name="date_to" value="' . esc_attr( $date_to ) . '">';

        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Aplicar filtros', 'agrocampo-post-venta' ) . '</button>';
        echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=agp-pv-submissions' ) ) . '">' . esc_html__( 'Limpiar', 'agrocampo-post-venta' ) . '</a>';

        echo '</div>';
        echo '</div>';
    }

    private function get_filters_from_request(): array {
        return array(
            'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'mail_status' => isset( $_GET['mail_status'] ) ? sanitize_key( wp_unslash( $_GET['mail_status'] ) ) : '',
            'pdf_status' => isset( $_GET['pdf_status'] ) ? sanitize_key( wp_unslash( $_GET['pdf_status'] ) ) : '',
            'email' => isset( $_GET['email'] ) ? sanitize_text_field( wp_unslash( $_GET['email'] ) ) : '',
            'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
            'date_to' => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
        );
    }

    private function build_where_clause( array $filters ): array {
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

        $sql = '';
        if ( ! empty( $clauses ) ) {
            $sql = 'WHERE ' . implode( ' AND ', $clauses );
        }

        return array(
            'sql' => $sql,
            'values' => $values,
        );
    }

    private function get_order_by(): string {
        $allowed = array( 'id', 'tecnico', 'cliente', 'created_at', 'mail_status', 'pdf_status' );
        $orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
        return in_array( $orderby, $allowed, true ) ? $orderby : 'created_at';
    }

    private function get_order_direction(): string {
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
            admin_url( 'admin.php?page=agp-pv-submissions&agp_pv_notice=bulk_deleted&agp_pv_notice_count=' . $deleted )
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
            admin_url( 'admin.php?page=agp-pv-submissions&agp_pv_notice=bulk_resend&agp_pv_notice_count=' . $processed )
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
            admin_url( 'admin.php?page=agp-pv-submissions&agp_pv_notice=bulk_regenerated&agp_pv_notice_count=' . $processed )
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

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=informes-tecnicos-' . gmdate( 'Ymd-His' ) . '.csv' );

        $output = fopen( 'php://output', 'w' );
        if ( false === $output ) {
            return;
        }

        fputcsv( $output, array_keys( $rows[0] ) );
        foreach ( $rows as $row ) {
            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }


    private function get_view_pdf_url( int $submission_id ): string {
        return wp_nonce_url(
            admin_url( 'admin.php?page=agp-pv-submissions&view=' . $submission_id ),
            'agp_pv_view_pdf_' . $submission_id
        );
    }

    public function column_default( $item, $column_name ) {
        if ( 'mail_status' === $column_name ) {
            return '<span class="agp-pv-status agp-pv-status-' . esc_attr( $item['mail_status'] ?? '' ) . '">' . esc_html( $this->format_status( (string) ( $item['mail_status'] ?? '' ) ) ) . '</span>';
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
        $actions = array();
        $submission_id = absint( $item['id'] );

        $actions['view'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=agp-pv-submissions&submission_id=' . $submission_id ) ),
            esc_html__( 'Ver detalle', 'agrocampo-post-venta' )
        );

        $actions['resend'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=agp_pv_resend_email&submission_id=' . $submission_id ), 'agp_pv_resend_email_' . $submission_id ) ),
            esc_html__( 'Reintentar correo', 'agrocampo-post-venta' )
        );

        $actions['regenerate_pdf'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=agp_pv_regenerate_pdf&submission_id=' . $submission_id ), 'agp_pv_regenerate_pdf_' . $submission_id ) ),
            esc_html__( 'Regenerar PDF', 'agrocampo-post-venta' )
        );

        $actions['view_pdf'] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( $this->get_view_pdf_url( $submission_id ) ),
            esc_html__( 'Ver PDF', 'agrocampo-post-venta' )
        );

        $display_id = AGP_PV_DB::get_visible_report_id( $item );

        return esc_html( (string) $display_id ) . $this->row_actions( $actions );
    }

    public function single_row( $item ): void {
        echo '<tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $this->single_row_columns( $item );
        echo '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ( isset( $_GET['submission_id'] ) && absint( $_GET['submission_id'] ) === (int) $item['id'] ) {
            echo '<tr class="agp-pv-detail"><td colspan="11">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo wp_kses_post( $this->render_detail( (int) $item['id'] ) );
            echo '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    private function render_detail( int $submission_id ): string {
        $submission = AGP_PV_Email::get_submission( $submission_id );
        if ( ! $submission ) {
            return esc_html__( 'No se encontró el envío.', 'agrocampo-post-venta' );
        }

        $output = '<strong>' . esc_html__( 'Detalle del envío', 'agrocampo-post-venta' ) . '</strong><br>';
        foreach ( $submission as $key => $value ) {
            if ( 'fotos_ids' === $key ) {
                continue;
            }
            $output .= '<strong>' . esc_html( $key ) . ':</strong> ' . esc_html( (string) $value ) . '<br>';
        }

        if ( ! empty( $submission['pdf_attachment_id'] ) || ! empty( $submission['id'] ) ) {
            $view_url = $this->get_view_pdf_url( (int) $submission_id );
            $output .= '<strong>' . esc_html__( 'PDF:', 'agrocampo-post-venta' ) . '</strong> ';
            $output .= '<a href="' . esc_url( $view_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Ver PDF', 'agrocampo-post-venta' ) . '</a><br>';

            $elapsed_ms = isset( $submission['pdf_generated_ms'] ) ? max( 0, (int) $submission['pdf_generated_ms'] ) : 0;
            $size_bytes = isset( $submission['pdf_size_bytes'] ) ? max( 0, (int) $submission['pdf_size_bytes'] ) : 0;
            $page_count = isset( $submission['pdf_page_count'] ) ? max( 0, (int) $submission['pdf_page_count'] ) : 0;
            $warnings = json_decode( (string) ( $submission['pdf_warnings'] ?? '' ), true );
            if ( ! is_array( $warnings ) ) {
                $warnings = array();
            }

            $output .= '<strong>' . esc_html__( 'Métricas PDF:', 'agrocampo-post-venta' ) . '</strong> ';
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
            $output .= ! empty( $metrics ) ? esc_html( implode( ' · ', $metrics ) ) : '&mdash;';
            $output .= '<br>';

            if ( ! empty( $warnings ) ) {
                $output .= '<strong>' . esc_html__( 'Warnings PDF:', 'agrocampo-post-venta' ) . '</strong><br>';
                foreach ( $warnings as $warning ) {
                    $output .= '- ' . esc_html( (string) $warning ) . '<br>';
                }
            }
        }

        $fotos = json_decode( (string) $submission['fotos_ids'], true );
        if ( is_array( $fotos ) && ! empty( $fotos ) ) {
            $output .= '<strong>' . esc_html__( 'Fotos:', 'agrocampo-post-venta' ) . '</strong><br>';
            foreach ( $fotos as $foto_id ) {
                $url = wp_get_attachment_url( (int) $foto_id );
                if ( $url ) {
                    $output .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver foto', 'agrocampo-post-venta' ) . '</a><br>';
                }
            }
        }

        return $output;
    }

    private function format_status( string $status ): string {
        if ( 'sent' === $status || 'ready' === $status ) {
            return __( 'Listo', 'agrocampo-post-venta' );
        }
        if ( 'failed' === $status ) {
            return __( 'Falló', 'agrocampo-post-venta' );
        }
        if ( 'pending' === $status ) {
            return __( 'Pendiente', 'agrocampo-post-venta' );
        }

        return $status;
    }
}
