<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AGP_PV_Email {
    private const RECIPIENTS_OPTION_KEY = 'agp_pv_recipients';
    private const NOTIFICATION_SETTINGS_OPTION_KEY = 'agp_pv_notification_settings';

    private const DEFAULT_RECIPIENTS = array(
        'info@agrocampo.cl',
        'paolacisterna@agrocampo.cl',
        'ivonnechacon@agrocampo.cl',
        'patriciagutierrez@agrocampo.cl',
        'luiszuniga@agrocampo.cl',
        'enriquerivas@agrocampo.cl',
    );

    public static function send_submission_email( int $submission_id ): array {
        $submission = self::get_submission( $submission_id );
        if ( ! $submission ) {
            return array(
                'mail_sent' => false,
                'mail_error' => __( 'No se encontró el envío.', 'agrocampo-post-venta' ),
            );
        }

        $pdf_result = AGP_PV_PDF::ensure_pdf_attachment( $submission_id, $submission );

        $mail_available = function_exists( 'mail' ) || has_filter( 'phpmailer_init' );
        if ( ! $mail_available ) {
            $message = __( 'La función mail() no está disponible en el servidor.', 'agrocampo-post-venta' );
            AGP_PV_DB::update_mail_status( $submission_id, 'failed', $message );
            return array(
                'mail_sent' => false,
                'mail_error' => $message,
                'admin_hint' => __( 'Configura SMTP (WP Mail SMTP u otro).', 'agrocampo-post-venta' ),
            );
        }

        $recipients = self::get_configured_recipients();

        if ( ! empty( $submission['email_cliente'] ) ) {
            $recipients[] = $submission['email_cliente'];
        }
        if ( ! empty( $submission['correo_copia'] ) ) {
            $recipients[] = $submission['correo_copia'];
        }
        $recipients = array_values( array_unique( $recipients ) );

        $report_id = AGP_PV_DB::get_visible_report_id( $submission );

        $subject = sprintf(
            'IT %d / %s / %s',
            $report_id > 0 ? $report_id : $submission_id,
            $submission['tecnico'],
            $submission['serie']
        );

        $body = self::build_email_body( $submission_id, $submission );

        $attachments = array();
        $pdf_warning = '';
        if ( isset( $pdf_result['status'] ) && 'ready' === $pdf_result['status'] && ! empty( $pdf_result['attachment_id'] ) ) {
            $pdf_path = get_attached_file( (int) $pdf_result['attachment_id'] );
            $pdf_path_validation = AGP_PV_PDF::validate_pdf_attachment_path( (string) $pdf_path );
            if ( ! empty( $pdf_path_validation['valid'] ) ) {
                $attachments[] = $pdf_path;
            } else {
                $pdf_warning = $pdf_path_validation['message'] ?? __( 'PDF inválido, se envió el correo sin adjunto.', 'agrocampo-post-venta' );
            }
        } elseif ( isset( $pdf_result['status'] ) && 'failed' === $pdf_result['status'] ) {
            $pdf_warning = $pdf_result['message'] ?? __( 'PDF inválido, se envió el correo sin adjunto.', 'agrocampo-post-venta' );
        }

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        $mail_error = null;
        $failed_callback = static function ( $wp_error ) use ( &$mail_error ) {
            if ( $wp_error instanceof WP_Error ) {
                $mail_error = $wp_error;
            }
        };

        add_action( 'wp_mail_failed', $failed_callback );
        $sent = wp_mail( $recipients, $subject, $body, $headers, $attachments );
        remove_action( 'wp_mail_failed', $failed_callback );

        if ( ! $sent ) {
            $message = __( 'No se pudo enviar el correo.', 'agrocampo-post-venta' );
            if ( $mail_error instanceof WP_Error ) {
                $message = $mail_error->get_error_message();
            }
            $message = self::normalize_submission_mail_error_message( wp_strip_all_tags( (string) $message ) );
            AGP_PV_DB::update_mail_status( $submission_id, 'failed', $message );
            return array(
                'mail_sent' => false,
                'mail_error' => $message,
                'admin_hint' => __( 'Configura SMTP (WP Mail SMTP u otro).', 'agrocampo-post-venta' ),
            );
        }

        AGP_PV_DB::update_mail_status( $submission_id, 'sent', '' );

        if ( $pdf_warning ) {
            return array(
                'mail_sent' => true,
                'pdf_warning' => $pdf_warning,
            );
        }

        return array( 'mail_sent' => true );
    }

    private static function normalize_submission_mail_error_message( string $message ): string {
        $clean_message = trim( wp_strip_all_tags( $message ) );
        if ( '' === $clean_message ) {
            return __( 'Correo pendiente de envío por un error temporal del proveedor.', 'agrocampo-post-venta' );
        }

        $normalized = strtolower( $clean_message );
        $is_rate_limited = false !== strpos( $normalized, 'ratelimit' )
            || false !== strpos( $normalized, 'rate limit' )
            || false !== strpos( $normalized, 'ratelimitexceeded' )
            || false !== strpos( $normalized, 'resource_exhausted' )
            || false !== strpos( $normalized, 'retry after' )
            || false !== strpos( $normalized, '429' );

        if ( ! $is_rate_limited ) {
            return $clean_message;
        }

        $retry_text = '';
        if ( 1 === preg_match( '/retry after\\s+([0-9]{4}-[0-9]{2}-[0-9]{2}t[0-9:\\.\\-]+z)/i', $clean_message, $matches ) ) {
            $retry_at_utc = strtotime( $matches[1] );
            if ( false !== $retry_at_utc ) {
                $retry_text = wp_date( 'd/m/Y H:i', $retry_at_utc );
            }
        }

        if ( '' !== $retry_text ) {
            return sprintf(
                __( 'Correo pendiente de envío por límite temporal del proveedor. Reintenta después de %s.', 'agrocampo-post-venta' ),
                $retry_text
            );
        }

        return __( 'Correo pendiente de envío por límite temporal del proveedor. Reintenta en unos minutos.', 'agrocampo-post-venta' );
    }

    public static function build_email_body( int $submission_id, array $submission ): string {
        $body = '';
        $body .= '<p><strong>Nuevo Informe Técnico</strong></p>';
        $body .= '<p>Tiene Observaciones para ' . esc_html( $submission['maquina'] ) . ' - ' . esc_html( $submission['modelo'] ) . ' - ' . esc_html( $submission['numero_interno'] ) . '<br>';
        $body .= esc_html( $submission['tecnico'] ) . ' - Cliente ' . esc_html( $submission['cliente'] ) . '</p>';
        $machine_status_label = AGP_PV_Plugin::get_machine_status_label( (string) ( $submission['estado_maquina'] ?? '' ) );
        if ( '' !== $machine_status_label ) {
            $body .= '<p><strong>' . esc_html__( 'Estado de la máquina:', 'agrocampo-post-venta' ) . '</strong> ' . esc_html( $machine_status_label ) . '</p>';
        }
        if ( ! empty( $submission['fecha_contacto'] ) ) {
            $body .= '<p><strong>' . esc_html__( 'Fecha a contactar:', 'agrocampo-post-venta' ) . '</strong> ' . esc_html( (string) $submission['fecha_contacto'] ) . '</p>';
        }
        if ( ! empty( $submission['whatsapp_cliente'] ) ) {
            $body .= '<p><strong>' . esc_html__( 'WhatsApp cliente:', 'agrocampo-post-venta' ) . '</strong> ' . esc_html( (string) $submission['whatsapp_cliente'] ) . '</p>';
        }
        if ( ! empty( $submission['observaciones'] ) ) {
            $body .= '<p>' . nl2br( esc_html( $submission['observaciones'] ) ) . '</p>';
        }

        $report_id = AGP_PV_DB::get_visible_report_id( $submission );
        $body .= '<p>ID: ' . esc_html( (string) ( $report_id > 0 ? $report_id : $submission_id ) ) . '</p>';

        $links = array();
        if ( ! empty( $submission['firma_cliente_id'] ) ) {
            $links[] = '<a href="' . esc_url( wp_get_attachment_url( $submission['firma_cliente_id'] ) ) . '">Firma Cliente</a>';
        }
        if ( ! empty( $submission['firma_tecnico_id'] ) ) {
            $links[] = '<a href="' . esc_url( wp_get_attachment_url( $submission['firma_tecnico_id'] ) ) . '">Firma Técnico</a>';
        }
        if ( ! empty( $submission['firma_jefe_taller_id'] ) ) {
            $links[] = '<a href="' . esc_url( wp_get_attachment_url( $submission['firma_jefe_taller_id'] ) ) . '">Firma Jefe de Taller</a>';
        }

        $fotos = json_decode( (string) $submission['fotos_ids'], true );
        if ( is_array( $fotos ) ) {
            foreach ( $fotos as $foto_id ) {
                $url = wp_get_attachment_url( (int) $foto_id );
                if ( $url ) {
                    $links[] = '<a href="' . esc_url( $url ) . '">Foto</a>';
                }
            }
        }

        if ( ! empty( $links ) ) {
            $body .= '<p>Adjuntos:<br>' . implode( '<br>', $links ) . '</p>';
        }

        return $body;
    }

    public static function get_submission( int $submission_id ): ?array {
        global $wpdb;
        $table = AGP_PV_DB::table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $submission_id ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public static function get_configured_recipients(): array {
        $saved = get_option( self::RECIPIENTS_OPTION_KEY, array() );
        $recipients = array();

        if ( is_array( $saved ) && ! empty( $saved ) ) {
            foreach ( $saved as $email ) {
                $email = sanitize_email( (string) $email );
                if ( $email && is_email( $email ) ) {
                    $recipients[] = $email;
                }
            }
        }

        if ( empty( $recipients ) ) {
            $recipients = self::DEFAULT_RECIPIENTS;
        }

        return $recipients;
    }

    public static function update_recipients_option( string $raw ): void {
        $emails = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        $clean = array();
        foreach ( $emails as $email ) {
            $email = sanitize_email( $email );
            if ( $email && is_email( $email ) ) {
                $clean[] = $email;
            }
        }

        if ( empty( $clean ) ) {
            delete_option( self::RECIPIENTS_OPTION_KEY );
            return;
        }

        update_option( self::RECIPIENTS_OPTION_KEY, array_values( array_unique( $clean ) ) );
    }

    public static function send_test_email( string $email ): array {
        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) {
            return array(
                'sent' => false,
                'message' => __( 'Correo inválido.', 'agrocampo-post-venta' ),
            );
        }

        $mail_available = function_exists( 'mail' ) || has_filter( 'phpmailer_init' );
        if ( ! $mail_available ) {
            return array(
                'sent' => false,
                'message' => __( 'La función mail() no está disponible en el servidor.', 'agrocampo-post-venta' ),
            );
        }

        $mail_error = null;
        $failed_callback = static function ( $wp_error ) use ( &$mail_error ) {
            if ( $wp_error instanceof WP_Error ) {
                $mail_error = $wp_error;
            }
        };

        add_action( 'wp_mail_failed', $failed_callback );
        $sent = wp_mail(
            $email,
            __( 'Prueba de correo Informe Técnico', 'agrocampo-post-venta' ),
            __( 'Este es un correo de prueba del plugin Agrocampo Informe Técnico.', 'agrocampo-post-venta' ),
            array( 'Content-Type: text/plain; charset=UTF-8' )
        );
        remove_action( 'wp_mail_failed', $failed_callback );

        if ( ! $sent ) {
            $message = __( 'No se pudo enviar el correo de prueba.', 'agrocampo-post-venta' );
            if ( $mail_error instanceof WP_Error ) {
                $message = $mail_error->get_error_message();
            }
            $message = wp_strip_all_tags( (string) $message );
            return array(
                'sent' => false,
                'message' => $message,
            );
        }

        return array(
            'sent' => true,
            'message' => __( 'Correo de prueba enviado.', 'agrocampo-post-venta' ),
        );
    }

    public static function get_notification_settings(): array {
        $defaults = self::default_notification_settings();
        $saved = get_option( self::NOTIFICATION_SETTINGS_OPTION_KEY, array() );
        $settings = is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;

        $settings['admin_email'] = sanitize_email( (string) ( $settings['admin_email'] ?? '' ) );
        if ( ! is_email( $settings['admin_email'] ) ) {
            $settings['admin_email'] = sanitize_email( (string) get_option( 'admin_email', '' ) );
        }

        $settings['enable_overdue'] = ! empty( $settings['enable_overdue'] ) ? 1 : 0;
        $settings['enable_daily_summary'] = ! empty( $settings['enable_daily_summary'] ) ? 1 : 0;
        $settings['enable_weekly_summary'] = ! empty( $settings['enable_weekly_summary'] ) ? 1 : 0;
        $settings['overdue_days'] = max( 1, min( 3650, (int) ( $settings['overdue_days'] ?? 7 ) ) );
        $settings['overdue_subject'] = sanitize_text_field( (string) ( $settings['overdue_subject'] ?? $defaults['overdue_subject'] ) );
        $settings['summary_subject'] = sanitize_text_field( (string) ( $settings['summary_subject'] ?? $defaults['summary_subject'] ) );
        $settings['overdue_body'] = sanitize_textarea_field( (string) ( $settings['overdue_body'] ?? $defaults['overdue_body'] ) );
        $settings['summary_body'] = sanitize_textarea_field( (string) ( $settings['summary_body'] ?? $defaults['summary_body'] ) );

        return $settings;
    }

    public static function update_notification_settings( array $raw ): void {
        $defaults = self::default_notification_settings();

        $settings = array(
            'admin_email' => sanitize_email( (string) ( $raw['admin_email'] ?? '' ) ),
            'enable_overdue' => ! empty( $raw['enable_overdue'] ) ? 1 : 0,
            'enable_daily_summary' => ! empty( $raw['enable_daily_summary'] ) ? 1 : 0,
            'enable_weekly_summary' => ! empty( $raw['enable_weekly_summary'] ) ? 1 : 0,
            'overdue_days' => max( 1, min( 3650, (int) ( $raw['overdue_days'] ?? 7 ) ) ),
            'overdue_subject' => sanitize_text_field( (string) ( $raw['overdue_subject'] ?? $defaults['overdue_subject'] ) ),
            'summary_subject' => sanitize_text_field( (string) ( $raw['summary_subject'] ?? $defaults['summary_subject'] ) ),
            'overdue_body' => sanitize_textarea_field( (string) ( $raw['overdue_body'] ?? $defaults['overdue_body'] ) ),
            'summary_body' => sanitize_textarea_field( (string) ( $raw['summary_body'] ?? $defaults['summary_body'] ) ),
        );

        if ( ! is_email( $settings['admin_email'] ) ) {
            $settings['admin_email'] = sanitize_email( (string) get_option( 'admin_email', '' ) );
        }

        update_option( self::NOTIFICATION_SETTINGS_OPTION_KEY, $settings );
    }

    public static function get_notification_template_tokens(): array {
        return array(
            'overdue' => array(
                '{id}',
                '{cliente}',
                '{tecnico}',
                '{observacion}',
                '{estado}',
                '{fecha_creacion}',
                '{dias_abierta}',
                '{pdf_url}',
                '{pdf_link}',
                '{gestor_url}',
                '{gestor_link}',
            ),
            'summary' => array(
                '{periodo}',
                '{total}',
                '{pendientes}',
                '{revisadas}',
                '{fecha_inicio}',
                '{fecha_fin}',
                '{rango_dias}',
                '{items_html}',
                '{gestor_url}',
                '{gestor_link}',
            ),
        );
    }

    public static function send_due_observation_notifications(): array {
        $settings = self::get_notification_settings();
        if ( empty( $settings['enable_overdue'] ) || empty( $settings['admin_email'] ) ) {
            return array(
                'processed' => 0,
                'sent' => 0,
            );
        }

        global $wpdb;
        $table = AGP_PV_DB::table_name();
        $cutoff = current_datetime()->modify( '-' . (int) $settings['overdue_days'] . ' days' )->format( 'Y-m-d H:i:s' );
        $today = current_datetime()->format( 'Y-m-d' );

        $pending_sql  = AGP_PV_Plugin::effective_pending_observation_sql();
        $presence_sql = implode( ' AND ', AGP_PV_Plugin::observation_presence_sql_clauses() );

        $sql = $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE {$pending_sql}
              AND {$presence_sql}
              AND created_at <= %s
              AND ( overdue_notified_at IS NULL OR DATE(overdue_notified_at) < %s )
            ORDER BY created_at ASC",
            $cutoff,
            $today
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );
        $sent = 0;

        foreach ( $rows as $row ) {
            $context = self::build_submission_notification_context( $row );
            $subject = self::render_notification_subject( $settings['overdue_subject'], $context );
            $body = self::render_notification_body( $settings['overdue_body'], $context );
            $result = self::send_html_mail( array( $settings['admin_email'] ), $subject, $body );

            if ( ! empty( $result['sent'] ) ) {
                AGP_PV_DB::update_overdue_notified_at( (int) $row['id'] );
                $sent++;
            }
        }

        return array(
            'processed' => count( $rows ),
            'sent' => $sent,
        );
    }

    public static function send_overdue_test_email(): array {
        $settings = self::get_notification_settings();
        if ( empty( $settings['admin_email'] ) ) {
            return array(
                'sent' => false,
                'message' => __( 'Configura primero el correo admin para notificaciones.', 'agrocampo-post-venta' ),
            );
        }

        $row = self::get_first_overdue_observation_row();
        $mode = 'real';

        if ( ! $row ) {
            $row = self::get_latest_observation_row();
        }

        if ( ! $row ) {
            $mode = 'sample';
            $context = self::get_sample_overdue_context();
        } else {
            $context = self::build_submission_notification_context( $row );
        }

        $subject = '[PRUEBA] ' . self::render_notification_subject( $settings['overdue_subject'], $context );
        $intro = '<p><strong>' . esc_html__( 'Este es un envío manual de prueba del correo de vencimiento.', 'agrocampo-post-venta' ) . '</strong></p>';
        $body = $intro . self::render_notification_body( $settings['overdue_body'], $context );
        $result = self::send_html_mail( array( $settings['admin_email'] ), $subject, $body );

        if ( ! empty( $result['sent'] ) ) {
            $result['mode'] = $mode;
        }

        return $result;
    }

    public static function send_period_summary( string $period, bool $force = false ): array {
        $settings = self::get_notification_settings();
        if ( empty( $settings['admin_email'] ) ) {
            return array( 'sent' => false );
        }

        if ( ! $force && 'daily' === $period && empty( $settings['enable_daily_summary'] ) ) {
            return array( 'sent' => false );
        }

        if ( ! $force && 'weekly' === $period && empty( $settings['enable_weekly_summary'] ) ) {
            return array( 'sent' => false );
        }

        $now = current_datetime();
        $start = 'weekly' === $period ? $now->modify( '-7 days' ) : $now->modify( '-1 day' );
        $rows = self::get_observation_rows_between( $start->format( 'Y-m-d H:i:s' ), $now->format( 'Y-m-d H:i:s' ) );
        $pending = 0;
        $reviewed = 0;

        foreach ( $rows as $row ) {
            if ( AGP_PV_Plugin::is_reviewed_observation_status( (string) ( $row['review_status'] ?? '' ) ) ) {
                $reviewed++;
            } else {
                $pending++;
            }
        }

        $context = array(
            'periodo' => 'weekly' === $period ? __( 'semanal', 'agrocampo-post-venta' ) : __( 'diario', 'agrocampo-post-venta' ),
            'total' => (string) count( $rows ),
            'pendientes' => (string) $pending,
            'revisadas' => (string) $reviewed,
            'fecha_inicio' => self::format_datetime( $start->format( 'Y-m-d H:i:s' ) ),
            'fecha_fin' => self::format_datetime( $now->format( 'Y-m-d H:i:s' ) ),
            'rango_dias' => (string) AGP_PV_Plugin::get_observations_report_window_days(),
            'items_html' => self::build_summary_items_html( $rows ),
            'gestor_url' => AGP_PV_Plugin::observations_standalone_url(),
            'gestor_link' => '<a href="' . esc_url( AGP_PV_Plugin::observations_standalone_url() ) . '">' . esc_html__( 'Abrir gestor', 'agrocampo-post-venta' ) . '</a>',
        );

        $subject = self::render_notification_subject( $settings['summary_subject'], $context );
        $body = self::render_notification_body( $settings['summary_body'], $context );

        return self::send_html_mail( array( $settings['admin_email'] ), $subject, $body );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_observation_rows_between( string $from, string $to ): array {
        global $wpdb;
        $table = AGP_PV_DB::table_name();

        $sql = $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE COALESCE(TRIM(observaciones), '') <> ''
              AND UPPER(TRIM(COALESCE(observaciones, ''))) NOT IN ('NULL', 'N/A', 'NA', '-', 'SIN OBSERVACIONES')
              AND created_at >= %s
              AND created_at <= %s
            ORDER BY created_at DESC",
            $from,
            $to
        );

        return (array) $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_first_overdue_observation_row(): ?array {
        $settings = self::get_notification_settings();

        global $wpdb;
        $table = AGP_PV_DB::table_name();
        $cutoff = current_datetime()->modify( '-' . (int) $settings['overdue_days'] . ' days' )->format( 'Y-m-d H:i:s' );

        $pending_sql  = AGP_PV_Plugin::effective_pending_observation_sql();
        $presence_sql = implode( ' AND ', AGP_PV_Plugin::observation_presence_sql_clauses() );

        $sql = $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE {$pending_sql}
              AND {$presence_sql}
              AND created_at <= %s
            ORDER BY created_at ASC
            LIMIT 1",
            $cutoff
        );

        $row = $wpdb->get_row( $sql, ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_latest_observation_row(): ?array {
        global $wpdb;
        $table = AGP_PV_DB::table_name();

        $sql = "SELECT *
            FROM {$table}
            WHERE COALESCE(TRIM(observaciones), '') <> ''
              AND UPPER(TRIM(COALESCE(observaciones, ''))) NOT IN ('NULL', 'N/A', 'NA', '-', 'SIN OBSERVACIONES')
            ORDER BY created_at DESC
            LIMIT 1";

        $row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array( $row ) ? $row : null;
    }

    private static function get_sample_overdue_context(): array {
        return array(
            'id' => 'TEST-001',
            'cliente' => __( 'Cliente de prueba', 'agrocampo-post-venta' ),
            'tecnico' => __( 'Técnico de prueba', 'agrocampo-post-venta' ),
            'observacion' => __( 'Observación de ejemplo para validar asunto, contenido y formato del correo.', 'agrocampo-post-venta' ),
            'estado' => __( 'Pendiente', 'agrocampo-post-venta' ),
            'fecha_creacion' => self::format_datetime( current_datetime()->modify( '-10 days' )->format( 'Y-m-d H:i:s' ) ),
            'dias_abierta' => '10',
            'pdf_url' => '',
            'pdf_link' => __( 'Sin PDF de prueba', 'agrocampo-post-venta' ),
            'gestor_url' => AGP_PV_Plugin::observations_standalone_url(),
            'gestor_link' => '<a href="' . esc_url( AGP_PV_Plugin::observations_standalone_url() ) . '">' . esc_html__( 'Abrir gestor', 'agrocampo-post-venta' ) . '</a>',
        );
    }

    private static function default_notification_settings(): array {
        return array(
            'admin_email' => sanitize_email( (string) get_option( 'admin_email', '' ) ),
            'enable_overdue' => 1,
            'enable_daily_summary' => 1,
            'enable_weekly_summary' => 1,
            'overdue_days' => 7,
            'overdue_subject' => __( 'Observación vencida #{id} / {cliente}', 'agrocampo-post-venta' ),
            'summary_subject' => __( 'Resumen {periodo} observaciones ({total})', 'agrocampo-post-venta' ),
            'overdue_body' => __( "Se detectó una observación pendiente fuera de plazo.\n\nID: {id}\nCliente: {cliente}\nTécnico: {tecnico}\nEstado: {estado}\nFecha de creación: {fecha_creacion}\nDías abierta: {dias_abierta}\nObservación:\n{observacion}\n\nPDF: {pdf_link}\nGestor: {gestor_link}", 'agrocampo-post-venta' ),
            'summary_body' => __( "Resumen {periodo} de informes con observaciones.\n\nDesde: {fecha_inicio}\nHasta: {fecha_fin}\nTotal: {total}\nPendientes: {pendientes}\nResueltas: {revisadas}\nRango global vigente: últimos {rango_dias} días.\n\n{items_html}\n\nGestor: {gestor_link}", 'agrocampo-post-venta' ),
        );
    }

    private static function build_submission_notification_context( array $submission ): array {
        $report_id = AGP_PV_DB::get_visible_report_id( $submission );
        $pdf_url = '';
        if ( ! empty( $submission['pdf_attachment_id'] ) ) {
            $pdf_url = (string) wp_get_attachment_url( (int) $submission['pdf_attachment_id'] );
        }

        $admin_manager_url = AGP_PV_Plugin::observations_standalone_url();
        $created_at = (string) ( $submission['created_at'] ?? '' );
        $days_open = 0;

        if ( '' !== $created_at && '0000-00-00 00:00:00' !== $created_at ) {
            try {
                $created_dt = new DateTimeImmutable( $created_at, wp_timezone() );
                $days_open = (int) $created_dt->diff( current_datetime() )->days;
            } catch ( Exception $e ) {
                $days_open = 0;
            }
        }

        return array(
            'id' => (string) ( $report_id > 0 ? $report_id : (int) ( $submission['id'] ?? 0 ) ),
            'cliente' => (string) ( $submission['cliente'] ?? '' ),
            'tecnico' => (string) ( $submission['tecnico'] ?? '' ),
            'observacion' => self::normalize_observation_text( (string) ( $submission['observaciones'] ?? '' ) ),
            'estado' => self::get_review_status_label( (string) ( $submission['review_status'] ?? '' ) ),
            'fecha_creacion' => self::format_datetime( $created_at ),
            'dias_abierta' => (string) $days_open,
            'pdf_url' => $pdf_url,
            'pdf_link' => $pdf_url ? '<a href="' . esc_url( $pdf_url ) . '">' . esc_html__( 'Abrir PDF', 'agrocampo-post-venta' ) . '</a>' : esc_html__( 'No disponible', 'agrocampo-post-venta' ),
            'gestor_url' => $admin_manager_url,
            'gestor_link' => '<a href="' . esc_url( $admin_manager_url ) . '">' . esc_html__( 'Abrir gestor', 'agrocampo-post-venta' ) . '</a>',
        );
    }

    private static function render_notification_subject( string $template, array $context ): string {
        $replacements = array();
        foreach ( $context as $key => $value ) {
            $replacements[ '{' . $key . '}' ] = wp_strip_all_tags( (string) $value );
        }

        return trim( wp_strip_all_tags( strtr( $template, $replacements ) ) );
    }

    private static function render_notification_body( string $template, array $context ): string {
        $html_keys = array( 'pdf_link', 'gestor_link', 'items_html' );
        $replacements = array();

        foreach ( $context as $key => $value ) {
            $string_value = (string) $value;
            if ( 'observacion' === $key ) {
                $replacements[ '{' . $key . '}' ] = nl2br( esc_html( $string_value ) );
                continue;
            }

            if ( in_array( $key, $html_keys, true ) ) {
                $replacements[ '{' . $key . '}' ] = $string_value;
                continue;
            }

            $replacements[ '{' . $key . '}' ] = esc_html( $string_value );
        }

        return wp_kses_post( nl2br( strtr( $template, $replacements ) ) );
    }

    private static function send_html_mail( array $recipients, string $subject, string $body ): array {
        $recipients = array_values(
            array_filter(
                array_map(
                    static function ( $email ) {
                        $email = sanitize_email( (string) $email );
                        return is_email( $email ) ? $email : '';
                    },
                    $recipients
                )
            )
        );

        if ( empty( $recipients ) ) {
            return array(
                'sent' => false,
                'message' => __( 'No hay destinatarios válidos.', 'agrocampo-post-venta' ),
            );
        }

        $mail_available = function_exists( 'mail' ) || has_filter( 'phpmailer_init' );
        if ( ! $mail_available ) {
            return array(
                'sent' => false,
                'message' => __( 'La función mail() no está disponible en el servidor.', 'agrocampo-post-venta' ),
            );
        }

        $mail_error = null;
        $failed_callback = static function ( $wp_error ) use ( &$mail_error ) {
            if ( $wp_error instanceof WP_Error ) {
                $mail_error = $wp_error;
            }
        };

        add_action( 'wp_mail_failed', $failed_callback );
        $sent = wp_mail( $recipients, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
        remove_action( 'wp_mail_failed', $failed_callback );

        if ( ! $sent ) {
            $message = __( 'No se pudo enviar el correo.', 'agrocampo-post-venta' );
            if ( $mail_error instanceof WP_Error ) {
                $message = $mail_error->get_error_message();
            }

            return array(
                'sent' => false,
                'message' => wp_strip_all_tags( (string) $message ),
            );
        }

        return array( 'sent' => true );
    }

    private static function build_summary_items_html( array $rows ): string {
        if ( empty( $rows ) ) {
            return '<p>' . esc_html__( 'Sin observaciones nuevas en este período.', 'agrocampo-post-venta' ) . '</p>';
        }

        $max_items = 50;
        $visible_rows = array_slice( $rows, 0, $max_items );
        $html = '<table style="width:100%;border-collapse:collapse">';
        $html .= '<thead><tr>';
        $html .= '<th style="text-align:left;padding:8px;border-bottom:1px solid #d8e0ea">ID</th>';
        $html .= '<th style="text-align:left;padding:8px;border-bottom:1px solid #d8e0ea">Cliente</th>';
        $html .= '<th style="text-align:left;padding:8px;border-bottom:1px solid #d8e0ea">Técnico</th>';
        $html .= '<th style="text-align:left;padding:8px;border-bottom:1px solid #d8e0ea">Estado</th>';
        $html .= '<th style="text-align:left;padding:8px;border-bottom:1px solid #d8e0ea">Fecha</th>';
        $html .= '<th style="text-align:left;padding:8px;border-bottom:1px solid #d8e0ea">Observación</th>';
        $html .= '</tr></thead><tbody>';

        foreach ( $visible_rows as $row ) {
            $report_id = AGP_PV_DB::get_visible_report_id( $row );
            $html .= '<tr>';
            $html .= '<td style="padding:8px;border-bottom:1px solid #eef2f7">#' . esc_html( (string) ( $report_id > 0 ? $report_id : (int) ( $row['id'] ?? 0 ) ) ) . '</td>';
            $html .= '<td style="padding:8px;border-bottom:1px solid #eef2f7">' . esc_html( (string) ( $row['cliente'] ?? '' ) ) . '</td>';
            $html .= '<td style="padding:8px;border-bottom:1px solid #eef2f7">' . esc_html( (string) ( $row['tecnico'] ?? '' ) ) . '</td>';
            $html .= '<td style="padding:8px;border-bottom:1px solid #eef2f7">' . esc_html( self::get_review_status_label( (string) ( $row['review_status'] ?? '' ) ) ) . '</td>';
            $html .= '<td style="padding:8px;border-bottom:1px solid #eef2f7">' . esc_html( self::format_datetime( (string) ( $row['created_at'] ?? '' ) ) ) . '</td>';
            $html .= '<td style="padding:8px;border-bottom:1px solid #eef2f7">' . esc_html( self::get_observation_excerpt( (string) ( $row['observaciones'] ?? '' ) ) ) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        if ( count( $rows ) > $max_items ) {
            $html .= '<p>' . sprintf(
                /* translators: %d: extra observations count. */
                esc_html__( 'Hay %d observaciones adicionales fuera de esta lista resumida.', 'agrocampo-post-venta' ),
                count( $rows ) - $max_items
            ) . '</p>';
        }

        return $html;
    }

    private static function normalize_observation_text( string $text ): string {
        return AGP_PV_Plugin::normalize_observation_text( $text );
    }

    private static function get_observation_excerpt( string $text ): string {
        $text = self::normalize_observation_text( $text );
        if ( '' === $text ) {
            return '—';
        }

        if ( function_exists( 'mb_substr' ) && function_exists( 'mb_strlen' ) ) {
            return mb_strlen( $text ) > 120 ? mb_substr( $text, 0, 120 ) . '…' : $text;
        }

        return strlen( $text ) > 120 ? substr( $text, 0, 120 ) . '…' : $text;
    }

    private static function get_review_status_label( string $status ): string {
        if ( AGP_PV_Plugin::is_reviewed_observation_status( $status ) ) {
            return __( 'Resuelta', 'agrocampo-post-venta' );
        }

        if ( AGP_PV_Plugin::is_strict_pending_review_status( $status ) ) {
            return __( 'Pendiente', 'agrocampo-post-venta' );
        }

        return __( 'Sin revisión', 'agrocampo-post-venta' );
    }

    private static function format_datetime( string $datetime ): string {
        if ( '' === $datetime || '0000-00-00 00:00:00' === $datetime ) {
            return '—';
        }

        return mysql2date( 'd/m/Y H:i', $datetime );
    }
}
