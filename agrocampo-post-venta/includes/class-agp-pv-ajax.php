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

    public function handle_submit(): void {
        if ( ! check_ajax_referer( 'agp_pv_submit', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce inválido.', 'agrocampo-post-venta' ) ) );
        }

        if ( ! empty( $_POST['agp_pv_hp'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Formulario inválido.', 'agrocampo-post-venta' ) ) );
        }

        $data = $this->sanitize_submission( $_POST );
        $data['tipo_servicio_label'] = $this->get_tipo_servicio_label( $data['tipo_servicio'] );
        $data['tipo_mantencion_label'] = $this->get_tipo_mantencion_label( $data['tipo_mantencion'] );
        $errors = $this->validate_submission( $data );

        if ( ! empty( $errors ) ) {
            wp_send_json_error( array( 'errors' => $errors ) );
        }

        $upload_result = $this->handle_uploads();
        if ( ! $upload_result['success'] ) {
            wp_send_json_error( array( 'message' => $upload_result['message'] ) );
        }

        $signature_result = $this->handle_signatures();
        if ( ! $signature_result['success'] ) {
            wp_send_json_error( array( 'message' => $signature_result['message'] ) );
        }

        $data['firma_cliente_id'] = $signature_result['firma_cliente_id'];
        $data['firma_tecnico_id'] = $signature_result['firma_tecnico_id'];
        $data['fotos_ids'] = wp_json_encode( $upload_result['fotos_ids'] );

        $submission_id = $this->insert_submission( $data );
        if ( ! $submission_id ) {
            wp_send_json_error( array( 'message' => __( 'No se pudo guardar el envío.', 'agrocampo-post-venta' ) ) );
        }

        $email_result = AGP_PV_Email::send_submission_email( $submission_id );
        if ( empty( $email_result['mail_sent'] ) ) {
            wp_send_json_success(
                array(
                    'message' => __( 'Informe guardado correctamente, pero NO se pudo enviar el correo.', 'agrocampo-post-venta' ),
                    'mail_sent' => false,
                    'mail_error' => $email_result['mail_error'] ?? __( 'No se pudo enviar el correo.', 'agrocampo-post-venta' ),
                    'admin_hint' => $email_result['admin_hint'] ?? __( 'Configura SMTP (WP Mail SMTP u otro).', 'agrocampo-post-venta' ),
                    'submission_id' => $submission_id,
                )
            );
        }

        if ( ! empty( $email_result['pdf_warning'] ) ) {
            wp_send_json_success(
                array(
                    'message' => __( 'Informe enviado, pero el PDF no pudo adjuntarse.', 'agrocampo-post-venta' ),
                    'pdf_warning' => $email_result['pdf_warning'],
                    'submission_id' => $submission_id,
                )
            );
        }

        wp_send_json_success(
            array(
                'message' => __( 'Informe enviado.', 'agrocampo-post-venta' ),
                'autoclose' => true,
                'autocloseDelay' => 5000,
                'submission_id' => $submission_id,
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
            'observaciones' => sanitize_textarea_field( $raw['observaciones'] ?? '' ),
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

        return $errors;
    }

    private function get_tipo_servicio_label( string $value ): string {
        $map = array(
            'one' => 'Factura Cliente',
            'two' => 'Garantia',
            'Interno' => 'Mantención',
            'Visita-de-Cortesía' => 'Visita de Cortesía',
            'Diagnostico-Técnico' => 'Diagnostico Técnico',
            'Entrega-Técnica' => 'Entrega Técnica',
        );

        return $map[ $value ] ?? $value;
    }

    private function get_tipo_mantencion_label( string $value ): string {
        $map = array(
            'one' => '100 Horas',
            'two' => '400 Horas',
            '500-Horas' => '500 Horas',
            '800-Horas' => '800 Horas',
            '1000-Horas' => '1000 Horas',
            '1200' => '1200 Horas',
            '1600-Horas' => '1500 Horas',
            'OTRO' => 'OTRO',
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

        $firma_cliente = isset( $_POST['firma_cliente'] ) ? sanitize_text_field( wp_unslash( $_POST['firma_cliente'] ) ) : '';
        $firma_tecnico = isset( $_POST['firma_tecnico'] ) ? sanitize_text_field( wp_unslash( $_POST['firma_tecnico'] ) ) : '';

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

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        for ( $i = 0; $i < $file_count; $i++ ) {
            if ( empty( $_FILES['fotos']['name'][ $i ] ) ) {
                continue;
            }

            $file = array(
                'name'     => sanitize_file_name( $_FILES['fotos']['name'][ $i ] ),
                'type'     => $_FILES['fotos']['type'][ $i ],
                'tmp_name' => $_FILES['fotos']['tmp_name'][ $i ],
                'error'    => $_FILES['fotos']['error'][ $i ],
                'size'     => $_FILES['fotos']['size'][ $i ],
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

    private function insert_submission( array $data ): int {
        global $wpdb;
        $table = AGP_PV_DB::table_name();
        $now = current_time( 'mysql' );

        $inserted = $wpdb->insert(
            $table,
            array(
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
                'correo_copia' => $data['correo_copia'],
                'mail_status' => 'pending',
                'mail_error' => '',
                'mail_last_attempt_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array(
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
                '%s', // correo_copia
                '%s', // mail_status
                '%s', // mail_error
                '%s', // mail_last_attempt_at
                '%s', // created_at
                '%s', // updated_at
            )
        );

        if ( false === $inserted ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }
}
