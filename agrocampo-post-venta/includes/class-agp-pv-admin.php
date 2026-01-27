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
        add_action( 'admin_post_agp_pv_send_test_email', array( $this, 'handle_send_test_email' ) );
        add_action( 'admin_post_agp_pv_save_recipients', array( $this, 'handle_save_recipients' ) );
        add_action( 'admin_post_agp_pv_save_logo', array( $this, 'handle_save_logo' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'Post Venta', 'agrocampo-post-venta' ),
            __( 'Post Venta', 'agrocampo-post-venta' ),
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

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Envíos Post Venta', 'agrocampo-post-venta' ) . '</h1>';

        if ( isset( $_GET['agp_pv_notice'] ) ) {
            $notice = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice'] ) );
            $message = '';
            $class = 'notice-success';
            if ( 'mail_failed' === $notice ) {
                $message = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? '' ) );
                $class = 'notice-error';
            }
            if ( 'mail_sent' === $notice ) {
                $message = __( 'Correo reenviado.', 'agrocampo-post-venta' );
            }
            if ( 'test_sent' === $notice ) {
                $message = __( 'Correo de prueba enviado.', 'agrocampo-post-venta' );
            }
            if ( 'test_failed' === $notice ) {
                $message = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? '' ) );
                $class = 'notice-error';
            }
            if ( 'recipients_saved' === $notice ) {
                $message = __( 'Destinatarios actualizados.', 'agrocampo-post-venta' );
            }

            if ( $message ) {
                echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
            }
        }

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="agp-pv-submissions">';
        $table->search_box( __( 'Buscar', 'agrocampo-post-venta' ), 'agp-pv-search' );
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $notice = isset( $_GET['agp_pv_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['agp_pv_notice'] ) ) : '';
        $message = '';
        $class = 'notice-success';

        if ( 'recipients_saved' === $notice ) {
            $message = __( 'Destinatarios actualizados.', 'agrocampo-post-venta' );
        }
        if ( 'logo_saved' === $notice ) {
            $message = __( 'Logo actualizado.', 'agrocampo-post-venta' );
        }
        if ( 'test_sent' === $notice ) {
            $message = __( 'Correo de prueba enviado.', 'agrocampo-post-venta' );
        }
        if ( 'test_failed' === $notice ) {
            $message = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? '' ) );
            $class = 'notice-error';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Ajustes Post Venta', 'agrocampo-post-venta' ) . '</h1>';

        if ( $message ) {
            echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
        }

        $logo_id = absint( get_option( 'agp_pv_logo_attachment_id', 0 ) );
        $logo_width = (float) get_option( 'agp_pv_logo_width_mm', 38 );
        $logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';

        echo '<div class="card">';
        echo '<h2>' . esc_html__( 'Logo PDF', 'agrocampo-post-venta' ) . '</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="agp_pv_save_logo">';
        wp_nonce_field( 'agp_pv_save_logo' );
        echo '<input type="hidden" name="agp_pv_logo_attachment_id" id="agp-pv-logo-attachment-id" value="' . esc_attr( (string) $logo_id ) . '">';
        echo '<p><img id="agp-pv-logo-preview" src="' . esc_url( $logo_url ) . '" style="max-width:200px;display:' . ( $logo_url ? 'block' : 'none' ) . ';" alt=""></p>';
        echo '<p><button type="button" class="button" id="agp-pv-logo-select">' . esc_html__( 'Seleccionar logo', 'agrocampo-post-venta' ) . '</button> ';
        echo '<button type="button" class="button" id="agp-pv-logo-remove">' . esc_html__( 'Quitar logo', 'agrocampo-post-venta' ) . '</button></p>';
        echo '<p><label for="agp-pv-logo-width">' . esc_html__( 'Ancho máximo (mm)', 'agrocampo-post-venta' ) . '</label> ';
        echo '<input type="number" step="0.1" min="10" max="80" id="agp-pv-logo-width" name="agp_pv_logo_width_mm" value="' . esc_attr( (string) $logo_width ) . '"></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Guardar logo', 'agrocampo-post-venta' ) . '</button></p>';
        echo '</form>';
        echo '</div>';

        echo '<div class="card">';
        echo '<h2>' . esc_html__( 'Destinatarios de correo', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Define los correos que recibirán el informe. Sepáralos por coma.', 'agrocampo-post-venta' ) . '</p>';
        $recipients = AGP_PV_Email::get_configured_recipients();
        $recipients_value = implode( ', ', $recipients );
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="agp_pv_save_recipients">';
        wp_nonce_field( 'agp_pv_save_recipients' );
        echo '<textarea name="agp_pv_recipients" rows="3" class="large-text">' . esc_textarea( $recipients_value ) . '</textarea>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Guardar destinatarios', 'agrocampo-post-venta' ) . '</button></p>';
        echo '</form>';
        echo '</div>';

        echo '<div class="card">';
        echo '<h2>' . esc_html__( 'Requisito de correo', 'agrocampo-post-venta' ) . '</h2>';
        echo '<p>' . esc_html__( 'Si tu hosting no tiene habilitada la función mail(), necesitas configurar SMTP con un plugin como WP Mail SMTP (u otro). Solicita al proveedor: host SMTP, puerto, cifrado TLS/SSL, usuario y contraseña (o app password).', 'agrocampo-post-venta' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="agp_pv_send_test_email">';
        wp_nonce_field( 'agp_pv_send_test_email' );
        echo '<label for="agp-pv-test-email">' . esc_html__( 'Enviar correo de prueba a:', 'agrocampo-post-venta' ) . '</label> ';
        echo '<input type="email" id="agp-pv-test-email" name="test_email" required> ';
        echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Enviar correo de prueba', 'agrocampo-post-venta' ) . '</button>';
        echo '</form>';
        echo '</div>';

        echo '</div>';
    }

    public function handle_resend_email(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_resend_email' );

        $submission_id = isset( $_GET['submission_id'] ) ? absint( $_GET['submission_id'] ) : 0;
        if ( $submission_id ) {
            $result = AGP_PV_Email::send_submission_email( $submission_id );
            if ( empty( $result['mail_sent'] ) ) {
                $message = $result['mail_error'] ?? __( 'No se pudo enviar el correo.', 'agrocampo-post-venta' );
                wp_safe_redirect(
                    admin_url(
                        'admin.php?page=agp-pv-submissions&agp_pv_notice=mail_failed&agp_pv_notice_message=' . rawurlencode( $message )
                    )
                );
                exit;
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-submissions&agp_pv_notice=mail_sent' ) );
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
            admin_url(
                'admin.php?page=agp-pv-settings&agp_pv_notice=test_failed&agp_pv_notice_message=' . rawurlencode( $message )
            )
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

    public function enqueue_admin_assets( string $hook ): void {
        if ( empty( $_GET['page'] ) || 'agp-pv-settings' !== $_GET['page'] ) {
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
    public function get_columns(): array {
        return array(
            'id' => __( 'ID', 'agrocampo-post-venta' ),
            'tecnico' => __( 'Técnico', 'agrocampo-post-venta' ),
            'cliente' => __( 'Cliente', 'agrocampo-post-venta' ),
            'serie' => __( 'Serie', 'agrocampo-post-venta' ),
            'tipo_servicio' => __( 'Tipo de Servicio', 'agrocampo-post-venta' ),
            'mail_status' => __( 'Correo', 'agrocampo-post-venta' ),
            'mail_last_attempt_at' => __( 'Último intento', 'agrocampo-post-venta' ),
            'created_at' => __( 'Fecha', 'agrocampo-post-venta' ),
        );
    }

    public function prepare_items(): void {
        global $wpdb;
        $table = AGP_PV_DB::table_name();

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $where = '';
        $args = array();

        if ( $search ) {
            $where = "WHERE tecnico LIKE %s OR cliente LIKE %s OR serie LIKE %s";
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $args = array( $like, $like, $like );
        }

        $items_sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $items_query = $wpdb->prepare( $items_sql, array_merge( $args, array( $per_page, $offset ) ) );
        $this->items = $wpdb->get_results( $items_query, ARRAY_A );

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
        if ( $args ) {
            $count_query = $wpdb->prepare( $count_sql, $args );
        } else {
            $count_query = $count_sql;
        }
        $total_items = (int) $wpdb->get_var( $count_query );

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page' => $per_page,
            )
        );

        $this->_column_headers = array( $this->get_columns(), array(), array() );
    }

    public function column_default( $item, $column_name ) {
        if ( 'mail_status' === $column_name ) {
            return esc_html( $this->format_mail_status( (string) ( $item['mail_status'] ?? '' ) ) );
        }

        if ( 'mail_last_attempt_at' === $column_name ) {
            return esc_html( $item['mail_last_attempt_at'] ?? '' );
        }

        if ( 'created_at' === $column_name ) {
            return esc_html( $item['created_at'] );
        }

        return esc_html( $item[ $column_name ] ?? '' );
    }

    public function column_id( $item ): string {
        $actions = array();

        $actions['view'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=agp-pv-submissions&submission_id=' . absint( $item['id'] ) ) ),
            esc_html__( 'Ver detalle', 'agrocampo-post-venta' )
        );

        $actions['resend'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=agp_pv_resend_email&submission_id=' . absint( $item['id'] ) ), 'agp_pv_resend_email' ) ),
            esc_html__( 'Reenviar correo', 'agrocampo-post-venta' )
        );

        $value = esc_html( (string) $item['id'] );
        return $value . $this->row_actions( $actions );
    }

    public function single_row( $item ): void {
        echo '<tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $this->single_row_columns( $item );
        echo '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ( isset( $_GET['submission_id'] ) && absint( $_GET['submission_id'] ) === (int) $item['id'] ) {
            echo '<tr class="agp-pv-detail"><td colspan="8">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

        if ( ! empty( $submission['pdf_attachment_id'] ) ) {
            $url = wp_get_attachment_url( (int) $submission['pdf_attachment_id'] );
            if ( $url ) {
                $output .= '<strong>' . esc_html__( 'PDF:', 'agrocampo-post-venta' ) . '</strong> ';
                $output .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver PDF', 'agrocampo-post-venta' ) . '</a><br>';
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

    private function format_mail_status( string $status ): string {
        if ( 'sent' === $status ) {
            return __( 'Enviado', 'agrocampo-post-venta' );
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
