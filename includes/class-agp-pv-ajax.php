<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AGP_PV_Ajax {
    private static ?AGP_PV_Ajax $instance = null;

    public static function get_instance(): AGP_PV_Ajax {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_agp_pv_submit', array( $this, 'handle_submit' ) );
        add_action( 'wp_ajax_nopriv_agp_pv_submit', array( $this, 'handle_submit' ) );
    }

    private function enforce_post_request(): void {
        $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : '';
        if ( 'POST' !== $method ) {
            wp_send_json_error( array( 'message' => __( 'Método HTTP no permitido.', 'agrocampo-post-venta' ) ), 405 );
        }
    }

    private function is_submit_logging_enabled(): bool {
        $debug_enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || (bool) get_option( 'agp_pv_debug' );
        if ( ! $debug_enabled ) {
            return false;
        }

        $debug_log = defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : false;
        $debug_log_enabled = ( true === $debug_log ) || ( is_string( $debug_log ) && '' !== trim( $debug_log ) );

        return $debug_log_enabled;
    }

    private function log_submit_event( string $event, array $context = array() ): void {
        if ( ! $this->is_submit_logging_enabled() ) {
            return;
        }

        $payload = array( 'event' => $event );
        foreach ( $context as $key => $value ) {
            if ( is_scalar( $value ) || null === $value ) {
                $payload[ (string) $key ] = $value;
            }
        }

        $encoded = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( false === $encoded ) {
            $encoded = '{"event":"' . esc_js( $event ) . '"}';
        }

        error_log( '[agp_pv_submit] ' . $encoded ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }

    public function handle_submit(): void {
        $this->enforce_post_request();

        $this->log_submit_event(
            'request_started',
            array(
                'logged_in' => is_user_logged_in() ? 1 : 0,
                'has_files' => ! empty( $_FILES ) ? 1 : 0,
                'rate_limit_exempt' => $this->is_rate_limit_exempt() ? 1 : 0,
            )
        );

        if ( ! check_ajax_referer( 'agp_pv_submit', 'nonce', false ) ) {
            $this->log_submit_event( 'request_rejected_nonce' );
            wp_send_json_error( array( 'message' => __( 'Nonce inválido.', 'agrocampo-post-venta' ) ) );
        }

        if ( ! empty( $_POST['agp_pv_hp'] ) ) {
            $this->log_submit_event( 'request_rejected_honeypot' );
            wp_send_json_error( array( 'message' => __( 'Formulario inválido.', 'agrocampo-post-venta' ) ) );
        }

        if ( $this->is_rate_limited() ) {
            $this->log_submit_event( 'request_rejected_rate_limit' );
            wp_send_json_error(
                array(
                    'message' => __( 'Demasiados intentos. Espera unos minutos e inténtalo nuevamente.', 'agrocampo-post-venta' ),
                ),
                429
            );
        }

        $this->register_rate_limit_attempt();

        $data = $this->sanitize_submission( $_POST );
        $data['tipo_servicio_label'] = $this->get_tipo_servicio_label( $data['tipo_servicio'] );
        $data['tipo_mantencion_label'] = $this->get_tipo_mantencion_label( $data['tipo_mantencion'] );
        $errors = $this->validate_submission( $data );

        if ( ! empty( $errors ) ) {
            $this->log_submit_event(
                'validation_failed',
                array(
                    'error_count' => count( $errors ),
                    'error_fields' => implode( ',', array_keys( $errors ) ),
                )
            );
            wp_send_json_error( array( 'errors' => $errors ) );
        }

        $upload_result = $this->handle_uploads();
        if ( ! $upload_result['success'] ) {
            $this->log_submit_event( 'uploads_failed' );
            wp_send_json_error( array( 'message' => $upload_result['message'] ) );
        }

        $signature_result = $this->handle_signatures();
        if ( ! $signature_result['success'] ) {
            $this->log_submit_event( 'signatures_failed' );
            wp_send_json_error( array( 'message' => $signature_result['message'] ) );
        }

        $data['firma_cliente_id'] = $signature_result['firma_cliente_id'];
        $data['firma_tecnico_id'] = $signature_result['firma_tecnico_id'];
        $data['fotos_ids'] = wp_json_encode( $upload_result['fotos_ids'] );

        $insert_result = $this->insert_submission( $data );
        $submission_id = (int) ( $insert_result['submission_id'] ?? 0 );
        $report_id = (int) ( $insert_result['report_id'] ?? 0 );
        if ( ! $submission_id ) {
            $this->log_submit_event( 'db_insert_failed' );
            wp_send_json_error( array( 'message' => __( 'No se pudo guardar el envío.', 'agrocampo-post-venta' ) ) );
        }

        $email_result = AGP_PV_Email::send_submission_email( $submission_id );
        if ( empty( $email_result['mail_sent'] ) ) {
            $this->log_submit_event(
                'submit_partial_mail',
                array(
                    'submission_id' => $submission_id,
                    'report_id' => $report_id > 0 ? $report_id : $submission_id,
                )
            );
            wp_send_json_success(
                array(
                    'message' => __( 'Informe guardado correctamente, pero NO se pudo enviar el correo.', 'agrocampo-post-venta' ),
                    'mail_sent' => false,
                    'mail_error' => $email_result['mail_error'] ?? __( 'No se pudo enviar el correo.', 'agrocampo-post-venta' ),
                    'admin_hint' => $email_result['admin_hint'] ?? __( 'Configura SMTP (WP Mail SMTP u otro).', 'agrocampo-post-venta' ),
                    'submission_id' => $submission_id,
                    'report_id' => $report_id > 0 ? $report_id : $submission_id,
                    'status_type' => 'partial_mail',
                )
            );
        }

        if ( ! empty( $email_result['pdf_warning'] ) ) {
            $this->log_submit_event(
                'submit_partial_pdf',
                array(
                    'submission_id' => $submission_id,
                    'report_id' => $report_id > 0 ? $report_id : $submission_id,
                )
            );
            wp_send_json_success(
                array(
                    'message' => __( 'Informe enviado, pero el PDF no pudo adjuntarse.', 'agrocampo-post-venta' ),
                    'pdf_warning' => $email_result['pdf_warning'],
                    'submission_id' => $submission_id,
                    'report_id' => $report_id > 0 ? $report_id : $submission_id,
                    'status_type' => 'partial_pdf',
                )
            );
        }

        $this->log_submit_event(
            'submit_success',
            array(
                'submission_id' => $submission_id,
                'report_id' => $report_id > 0 ? $report_id : $submission_id,
            )
        );

        wp_send_json_success(
            array(
                'message' => __( 'Informe enviado.', 'agrocampo-post-venta' ),
                'autoclose' => true,
                'autocloseDelay' => 5000,
                'submission_id' => $submission_id,
                'report_id' => $report_id > 0 ? $report_id : $submission_id,
                'status_type' => 'success',
            )
        );
    }

    private function sanitize_submission( array $raw ): array {
        return array(
            'tecnico' => sanitize_text_field( $raw['tecnico'] ?? '' ),
            'cliente' => sanitize_text_field( $raw['cliente'] ?? '' ),
            'email_cliente' => sanitize_email( $raw['email_cliente'] ?? '' ),
            'faena_lugar' => sanitize_text_field( $raw['faena_lugar'] ?? '' ),
            'maquina' => sanitize_text_field( $raw['maquina'] ?? '' ),
            'modelo' => sanitize_text_field( $raw['modelo'] ?? '' ),
            'serie' => sanitize_text_field( $raw['serie'] ?? '' ),
            'numero_interno' => sanitize_text_field( $raw['numero_interno'] ?? '' ),
            'fecha' => sanitize_text_field( $raw['fecha'] ?? '' ),
            'horas' => sanitize_text_field( $raw['horas'] ?? '' ),
            'tipo_servicio' => sanitize_text_field( $raw['tipo_servicio'] ?? '' ),
            'tipo_mantencion' => sanitize_text_field( $raw['tipo_mantencion'] ?? '' ),
            'cantidad_horas' => sanitize_text_field( $raw['cantidad_horas'] ?? '' ),
            'fecha_reparacion' => sanitize_text_field( $raw['fecha_reparacion'] ?? '' ),
            'fecha_cierre' => sanitize_text_field( $raw['fecha_cierre'] ?? '' ),
            'lubricantes' => sanitize_textarea_field( $raw['lubricantes'] ?? '' ),
            'filtros_utilizados' => sanitize_textarea_field( $raw['filtros_utilizados'] ?? '' ),
            'componentes_utilizados' => sanitize_textarea_field( $raw['componentes_utilizados'] ?? '' ),
            'trabajos_realizados' => sanitize_textarea_field( $raw['trabajos_realizados'] ?? '' ),
            'observaciones' => AGP_PV_Plugin::normalize_observation_text( sanitize_textarea_field( $raw['observaciones'] ?? '' ) ),
            'correo_copia' => sanitize_email( $raw['correo_copia'] ?? '' ),
        );
    }

    private function validate_submission( array $data ): array {
        $errors = array();

        if ( '' === $data['tecnico'] ) {
            $errors['tecnico'] = __( 'Seleccionar Técnico.', 'agrocampo-post-venta' );
        }
        if ( '' === $data['cliente'] ) {
            $errors['cliente'] = __( 'Cliente es obligatorio.', 'agrocampo-post-venta' );
        }
        if ( '' === $data['maquina'] ) {
            $errors['maquina'] = __( 'Máquina es obligatorio.', 'agrocampo-post-venta' );
        }
        if ( '' === $data['modelo'] ) {
            $errors['modelo'] = __( 'Modelo es obligatorio.', 'agrocampo-post-venta' );
        }
        if ( '' === $data['serie'] ) {
            $errors['serie'] = __( 'Serie es obligatorio.', 'agrocampo-post-venta' );
        }
        if ( '' === $data['numero_interno'] ) {
            $errors['numero_interno'] = __( 'N° Interno es obligatorio.', 'agrocampo-post-venta' );
        }
        if ( '' === $data['horas'] ) {
            $errors['horas'] = __( 'Horas es obligatorio.', 'agrocampo-post-venta' );
        }
        if ( '' === $data['tipo_servicio'] ) {
            $errors['tipo_servicio'] = __( 'Tipo de Servicio es obligatorio.', 'agrocampo-post-venta' );
        }

        if ( 'Interno' === $data['tipo_servicio'] && '' === $data['tipo_mantencion'] ) {
            $errors['tipo_mantencion'] = __( 'Seleccionar Tipo de Mantención.', 'agrocampo-post-venta' );
        }

        if ( 'OTRO' === $data['tipo_mantencion'] && '' === $data['cantidad_horas'] ) {
            $errors['cantidad_horas'] = __( 'CANTIDAD DE HORAS es obligatorio.', 'agrocampo-post-venta' );
        }

        if ( 'two' !== $data['tipo_servicio'] && '' === $data['fecha'] ) {
            $errors['fecha'] = __( 'Fecha es obligatorio.', 'agrocampo-post-venta' );
        }

        if ( 'two' === $data['tipo_servicio'] && '' === $data['fecha_reparacion'] ) {
            $errors['fecha_reparacion'] = __( 'Fecha Reparación es obligatorio.', 'agrocampo-post-venta' );
        }


        $detalle_min_chars = 10;
        $trabajos_len = $this->text_length( trim( (string) ( $data['trabajos_realizados'] ?? '' ) ) );
        $observaciones_len = $this->text_length( trim( (string) ( $data['observaciones'] ?? '' ) ) );
        if ( $trabajos_len < $detalle_min_chars && $observaciones_len < $detalle_min_chars ) {
            $errors['trabajos_realizados'] = sprintf(
                /* translators: %d minimum characters required in at least one detail field */
                __( 'Completa “Trabajos realizados” u “Observaciones” con al menos %d caracteres.', 'agrocampo-post-venta' ),
                $detalle_min_chars
            );
        }

        $max_lengths = array(
            'cliente' => 80,
            'faena_lugar' => 80,
            'maquina' => 50,
            'modelo' => 50,
            'serie' => 60,
            'numero_interno' => 30,
            'trabajos_realizados' => 455,
        );

        foreach ( $max_lengths as $field => $max_length ) {
            $current = isset( $data[ $field ] ) ? (string) $data[ $field ] : '';
            if ( '' === $current ) {
                continue;
            }

            if ( $this->text_length( $current ) > (int) $max_length ) {
                $errors[ $field ] = sprintf(
                    /* translators: %d max allowed characters */
                    __( 'Máximo %d caracteres.', 'agrocampo-post-venta' ),
                    (int) $max_length
                );
            }
        }

        return $errors;
    }

    private function text_length( string $text ): int {
        if ( function_exists( 'mb_strlen' ) ) {
            return (int) mb_strlen( $text );
        }

        return strlen( $text );
    }

    private function get_tipo_servicio_label( string $value ): string {
        $map = array(
            'one' => __( 'Factura Cliente', 'agrocampo-post-venta' ),
            'two' => __( 'Garantía', 'agrocampo-post-venta' ),
            'Interno' => __( 'Mantención', 'agrocampo-post-venta' ),
            'Visita-de-Cortesía' => __( 'Visita de Cortesía', 'agrocampo-post-venta' ),
            'Diagnostico-Técnico' => __( 'Diagnóstico Técnico', 'agrocampo-post-venta' ),
            'Entrega-Técnica' => __( 'Entrega Técnica', 'agrocampo-post-venta' ),
        );

        return $map[ $value ] ?? $value;
    }

    private function get_tipo_mantencion_label( string $value ): string {
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

        return $map[ $value ] ?? $value;
    }

    private function handle_signatures(): array {
        $result = array(
            'success' => true,
            'message' => '',
            'firma_cliente_id' => 0,
            'firma_tecnico_id' => 0,
        );

        $firma_cliente = $this->normalize_signature_data_url( $_POST['firma_cliente'] ?? '' );
        $firma_tecnico = $this->normalize_signature_data_url( $_POST['firma_tecnico'] ?? '' );

        if ( $firma_cliente ) {
            $result['firma_cliente_id'] = $this->store_signature( $firma_cliente, 'firma-cliente' );
            if ( ! $result['firma_cliente_id'] ) {
                return array(
                    'success' => false,
                    'message' => __( 'No se pudo guardar la firma del cliente.', 'agrocampo-post-venta' ),
                );
            }
        }

        if ( $firma_tecnico ) {
            $result['firma_tecnico_id'] = $this->store_signature( $firma_tecnico, 'firma-tecnico' );
            if ( ! $result['firma_tecnico_id'] ) {
                return array(
                    'success' => false,
                    'message' => __( 'No se pudo guardar la firma del técnico.', 'agrocampo-post-venta' ),
                );
            }
        }

        return $result;
    }

    private function normalize_signature_data_url( $raw ): string {
        if ( ! is_string( $raw ) ) {
            return '';
        }

        $value = trim( wp_unslash( $raw ) );
        if ( '' === $value ) {
            return '';
        }

        // In case payload was urlencoded, restore base64 plus signs.
        return str_replace( ' ', '+', $value );
    }

    private function store_signature( string $data_url, string $prefix ): int {
        if ( ! str_starts_with( $data_url, 'data:image/png;base64,' ) ) {
            return 0;
        }

        $data = base64_decode( str_replace( 'data:image/png;base64,', '', $data_url ) );
        if ( false === $data ) {
            return 0;
        }

        $filename = sprintf( '%s-%s.png', $prefix, wp_generate_password( 8, false ) );
        $upload = wp_upload_bits( $filename, null, $data );
        if ( ! empty( $upload['error'] ) ) {
            return 0;
        }

        $filetype = wp_check_filetype( $upload['file'], null );

        $attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name( $filename ),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attach_id = wp_insert_attachment( $attachment, $upload['file'] );
        if ( ! $attach_id ) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        return (int) $attach_id;
    }

    private function handle_uploads(): array {
        $result = array(
            'success' => true,
            'message' => '',
            'fotos_ids' => array(),
        );

        if ( empty( $_FILES['fotos'] ) || empty( $_FILES['fotos']['name'] ) ) {
            return $result;
        }

        $file_count = count( $_FILES['fotos']['name'] );
        if ( $file_count > 10 ) {
            return array(
                'success' => false,
                'message' => __( 'Máximo 10 fotos.', 'agrocampo-post-venta' ),
            );
        }

        $max_file_bytes = 5 * 1024 * 1024;
        $allowed_mimes  = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
        );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        for ( $i = 0; $i < $file_count; $i++ ) {
            if ( empty( $_FILES['fotos']['name'][ $i ] ) ) {
                continue;
            }

            $name     = sanitize_file_name( $_FILES['fotos']['name'][ $i ] );
            $type     = sanitize_text_field( $_FILES['fotos']['type'][ $i ] ?? '' );
            $tmp_name = $_FILES['fotos']['tmp_name'][ $i ] ?? '';
            $error    = (int) ( $_FILES['fotos']['error'][ $i ] ?? UPLOAD_ERR_NO_FILE );
            $size     = (int) ( $_FILES['fotos']['size'][ $i ] ?? 0 );

            if ( UPLOAD_ERR_OK !== $error ) {
                return array(
                    'success' => false,
                    'message' => sprintf( __( 'Error al subir la foto: %s.', 'agrocampo-post-venta' ), $name ),
                );
            }

            if ( $size <= 0 || $size > $max_file_bytes ) {
                return array(
                    'success' => false,
                    'message' => sprintf( __( 'La foto %s supera el máximo de 5 MB.', 'agrocampo-post-venta' ), $name ),
                );
            }

            $checked = wp_check_filetype_and_ext( $tmp_name, $name, $allowed_mimes );
            if ( empty( $checked['type'] ) || ! str_starts_with( $checked['type'], 'image/' ) ) {
                return array(
                    'success' => false,
                    'message' => sprintf( __( 'Formato no permitido para la foto: %s.', 'agrocampo-post-venta' ), $name ),
                );
            }

            if ( ! str_starts_with( $type, 'image/' ) ) {
                return array(
                    'success' => false,
                    'message' => sprintf( __( 'Tipo MIME inválido para la foto: %s.', 'agrocampo-post-venta' ), $name ),
                );
            }

            $file = array(
                'name'     => $name,
                'type'     => $checked['type'],
                'tmp_name' => $tmp_name,
                'error'    => $error,
                'size'     => $size,
            );

            $attachment_id = media_handle_sideload( $file, 0 );
            if ( is_wp_error( $attachment_id ) ) {
                return array(
                    'success' => false,
                    'message' => __( 'Error al subir fotos.', 'agrocampo-post-venta' ),
                );
            }

            $result['fotos_ids'][] = (int) $attachment_id;
        }

        return $result;
    }

    private function insert_submission( array $data ): array {
        global $wpdb;
        $table = AGP_PV_DB::table_name();
        $now = current_time( 'mysql' );
        $data['observaciones'] = AGP_PV_Plugin::normalize_observation_text( (string) ( $data['observaciones'] ?? '' ) );
        $review_status = AGP_PV_Plugin::resolve_review_status_for_observation( (string) $data['observaciones'] );
        $lock_name = $this->get_report_id_lock_name();
        $should_release_lock = false;

        if ( '' !== $lock_name && $this->acquire_report_id_lock( $lock_name ) ) {
            $should_release_lock = true;
        }

        $report_id = $this->get_next_imported_report_id();

        $inserted = $wpdb->insert(
            $table,
            array(
                'legacy_id' => $report_id,
                'tecnico' => $data['tecnico'],
                'cliente' => $data['cliente'],
                'email_cliente' => $data['email_cliente'],
                'faena_lugar' => $data['faena_lugar'],
                'maquina' => $data['maquina'],
                'modelo' => $data['modelo'],
                'serie' => $data['serie'],
                'numero_interno' => $data['numero_interno'],
                'fecha' => $data['fecha'],
                'horas' => $data['horas'],
                'tipo_servicio' => $data['tipo_servicio'],
                'tipo_servicio_label' => $data['tipo_servicio_label'],
                'tipo_mantencion' => $data['tipo_mantencion'],
                'tipo_mantencion_label' => $data['tipo_mantencion_label'],
                'cantidad_horas' => $data['cantidad_horas'],
                'fecha_reparacion' => $data['fecha_reparacion'],
                'fecha_cierre' => $data['fecha_cierre'],
                'lubricantes' => $data['lubricantes'],
                'filtros_utilizados' => $data['filtros_utilizados'],
                'componentes_utilizados' => $data['componentes_utilizados'],
                'trabajos_realizados' => $data['trabajos_realizados'],
                'observaciones' => $data['observaciones'],
                'firma_cliente_id' => $data['firma_cliente_id'],
                'firma_tecnico_id' => $data['firma_tecnico_id'],
                'fotos_ids' => $data['fotos_ids'],
                'pdf_attachment_id' => 0,
                'pdf_status' => 'pending',
                'pdf_error' => '',
                'pdf_last_attempt_at' => null,
                'pdf_generated_ms' => 0,
                'pdf_size_bytes' => 0,
                'pdf_page_count' => 0,
                'pdf_warnings' => wp_json_encode( array() ),
                'correo_copia' => $data['correo_copia'],
                'mail_status' => 'pending',
                'mail_error' => '',
                'mail_last_attempt_at' => null,
                'review_status' => $review_status,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array(
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
                '%s', // review_status
                '%s', // created_at
                '%s', // updated_at
            )
        );

        if ( $should_release_lock ) {
            $this->release_report_id_lock( $lock_name );
        }

        if ( false === $inserted ) {
            return array(
                'submission_id' => 0,
                'report_id' => 0,
            );
        }

        return array(
            'submission_id' => (int) $wpdb->insert_id,
            'report_id' => $report_id,
        );
    }

    private function get_next_imported_report_id(): int {
        global $wpdb;

        $table = AGP_PV_DB::table_name();
        $max_legacy_id = (int) $wpdb->get_var( "SELECT MAX(legacy_id) FROM {$table} WHERE legacy_id > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if ( $max_legacy_id <= 0 ) {
            return 0;
        }

        return $max_legacy_id + 1;
    }

    private function get_report_id_lock_name(): string {
        global $wpdb;

        $table = AGP_PV_DB::table_name();

        return substr( 'agp_pv_report_id_' . md5( $wpdb->dbname . ':' . $table ), 0, 64 );
    }

    private function acquire_report_id_lock( string $lock_name ): bool {
        global $wpdb;

        $query = $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 5 );
        if ( null === $query ) {
            return false;
        }

        return '1' === (string) $wpdb->get_var( $query );
    }

    private function release_report_id_lock( string $lock_name ): void {
        global $wpdb;

        $query = $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name );
        if ( null === $query ) {
            return;
        }

        $wpdb->query( $query );
    }

    private function is_rate_limit_exempt(): bool {
        $is_logged_in = is_user_logged_in();

        return (bool) apply_filters( 'agp_pv_rate_limit_exempt', $is_logged_in, $is_logged_in ? 'logged_in' : 'anonymous' );
    }

    private function is_rate_limited(): bool {
        if ( $this->is_rate_limit_exempt() ) {
            return false;
        }

        $max_attempts = (int) apply_filters( 'agp_pv_rate_limit_max_attempts', 5 );
        $window_seconds = (int) apply_filters( 'agp_pv_rate_limit_window_seconds', 15 * MINUTE_IN_SECONDS );

        if ( $max_attempts < 1 || $window_seconds < 1 ) {
            return false;
        }

        $key = $this->get_rate_limit_key();
        $attempts = (int) get_transient( $key );

        return $attempts >= $max_attempts;
    }

    private function register_rate_limit_attempt(): void {
        if ( $this->is_rate_limit_exempt() ) {
            return;
        }

        $window_seconds = (int) apply_filters( 'agp_pv_rate_limit_window_seconds', 15 * MINUTE_IN_SECONDS );
        if ( $window_seconds < 1 ) {
            return;
        }

        $key = $this->get_rate_limit_key();
        $attempts = (int) get_transient( $key );
        set_transient( $key, $attempts + 1, $window_seconds );
    }

    private function get_rate_limit_key(): string {
        $ip = $this->get_request_ip();
        $default = 'agp_pv_rate_limit_' . md5( $ip );

        return (string) apply_filters( 'agp_pv_rate_limit_key', $default, $ip );
    }

    private function get_request_ip(): string {
        $keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        );

        foreach ( $keys as $key ) {
            if ( empty( $_SERVER[ $key ] ) ) {
                continue;
            }

            $raw = sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) );
            if ( 'HTTP_X_FORWARDED_FOR' === $key ) {
                $parts = array_map( 'trim', explode( ',', $raw ) );
                $raw = $parts[0] ?? '';
            }

            $ip = filter_var( $raw, FILTER_VALIDATE_IP );
            if ( false !== $ip ) {
                return (string) $ip;
            }
        }

        return 'unknown';
    }

}
