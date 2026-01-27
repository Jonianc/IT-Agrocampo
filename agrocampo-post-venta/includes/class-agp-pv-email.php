<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AGP_PV_Email {
    private const RECIPIENTS_OPTION_KEY = 'agp_pv_recipients';
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

        $subject = sprintf(
            'IT %d / %s / %s',
            $submission_id,
            $submission['tecnico'],
            $submission['serie']
        );

        $body = self::build_email_body( $submission_id, $submission );

        $attachments = array();
        $pdf_warning = '';
        if ( isset( $pdf_result['status'] ) && 'ready' === $pdf_result['status'] && ! empty( $pdf_result['attachment_id'] ) ) {
            $pdf_path = get_attached_file( (int) $pdf_result['attachment_id'] );
            if ( $pdf_path && file_exists( $pdf_path ) ) {
                $attachments[] = $pdf_path;
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
            $message = wp_strip_all_tags( (string) $message );
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

    public static function build_email_body( int $submission_id, array $submission ): string {
        $body = '';
        $body .= '<p><strong>Nuevo Informe Técnico</strong></p>';
        $body .= '<p>Tiene Observaciones para ' . esc_html( $submission['maquina'] ) . ' - ' . esc_html( $submission['modelo'] ) . ' - ' . esc_html( $submission['numero_interno'] ) . '<br>';
        $body .= esc_html( $submission['tecnico'] ) . ' - Cliente ' . esc_html( $submission['cliente'] ) . '</p>';
        if ( ! empty( $submission['observaciones'] ) ) {
            $body .= '<p>' . nl2br( esc_html( $submission['observaciones'] ) ) . '</p>';
        }

        $body .= '<p>ID: ' . esc_html( (string) $submission_id ) . '</p>';

        $links = array();
        if ( ! empty( $submission['firma_cliente_id'] ) ) {
            $links[] = '<a href="' . esc_url( wp_get_attachment_url( $submission['firma_cliente_id'] ) ) . '">Firma Cliente</a>';
        }
        if ( ! empty( $submission['firma_tecnico_id'] ) ) {
            $links[] = '<a href="' . esc_url( wp_get_attachment_url( $submission['firma_tecnico_id'] ) ) . '">Firma Técnico</a>';
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
            __( 'Prueba de correo Post Venta', 'agrocampo-post-venta' ),
            __( 'Este es un correo de prueba del plugin Agrocampo Post Venta.', 'agrocampo-post-venta' ),
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
}
