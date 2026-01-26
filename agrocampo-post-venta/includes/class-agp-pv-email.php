<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AGP_PV_Email {
    public static function send_submission_email( int $submission_id ): array {
        $submission = self::get_submission( $submission_id );
        if ( ! $submission ) {
            return array(
                'success' => false,
                'message' => __( 'No se encontró el envío.', 'agrocampo-post-venta' ),
            );
        }

        $recipients = array(
            'info@agrocampo.cl',
            'paolacisterna@agrocampo.cl',
            'ivonnechacon@agrocampo.cl',
            'patriciagutierrez@agrocampo.cl',
            'luiszuniga@agrocampo.cl',
            'enriquerivas@agrocampo.cl',
        );

        if ( ! empty( $submission['email_cliente'] ) ) {
            $recipients[] = $submission['email_cliente'];
        }
        if ( ! empty( $submission['correo_copia'] ) ) {
            $recipients[] = $submission['correo_copia'];
        }

        $subject = sprintf(
            'IT %d / %s / %s',
            $submission_id,
            $submission['tecnico'],
            $submission['serie']
        );

        $body = self::build_email_body( $submission_id, $submission );

        $attachments = array();
        $pdf_path = AGP_PV_PDF::generate_pdf( $submission_id, $submission );
        if ( $pdf_path ) {
            $attachments[] = $pdf_path;
        }

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        $sent = wp_mail( $recipients, $subject, $body, $headers, $attachments );

        if ( $pdf_path && file_exists( $pdf_path ) ) {
            unlink( $pdf_path );
        }

        if ( ! $sent ) {
            return array(
                'success' => false,
                'message' => __( 'No se pudo enviar el correo.', 'agrocampo-post-venta' ),
            );
        }

        return array( 'success' => true );
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
}
