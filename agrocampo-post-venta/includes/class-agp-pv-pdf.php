<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once AGP_PV_PLUGIN_DIR . 'includes/fpdf/fpdf.php';

class AGP_PV_PDF {
    public static function ensure_pdf_attachment( int $submission_id, array $submission ): array {
        if ( ! class_exists( 'FPDF' ) ) {
            self::update_pdf_status( $submission_id, 'failed', __( 'No se encontró FPDF para generar el PDF.', 'agrocampo-post-venta' ) );
            return array(
                'status' => 'failed',
                'message' => __( 'No se encontró FPDF para generar el PDF.', 'agrocampo-post-venta' ),
                'attachment_id' => 0,
                'path' => '',
            );
        }

        if ( ! empty( $submission['pdf_attachment_id'] ) ) {
            $existing_path = get_attached_file( (int) $submission['pdf_attachment_id'] );
            if ( $existing_path && file_exists( $existing_path ) ) {
                self::update_pdf_status( $submission_id, 'ready', '' );
                return array(
                    'status' => 'ready',
                    'message' => '',
                    'attachment_id' => (int) $submission['pdf_attachment_id'],
                    'path' => $existing_path,
                );
            }
        }

        $upload_dir = wp_upload_dir();
        $filename = wp_unique_filename( $upload_dir['path'], 'agp-pv-' . $submission_id . '.pdf' );
        $path = trailingslashit( $upload_dir['path'] ) . $filename;

        $generated = self::generate_pdf_file( $submission_id, $submission, $path );
        if ( ! $generated ) {
            self::update_pdf_status( $submission_id, 'failed', __( 'No se pudo generar el PDF.', 'agrocampo-post-venta' ) );
            return array(
                'status' => 'failed',
                'message' => __( 'No se pudo generar el PDF.', 'agrocampo-post-venta' ),
                'attachment_id' => 0,
                'path' => '',
            );
        }

        $validation = self::validate_pdf_file( $path );
        self::log( sprintf( 'PDF %s generado en %s (%d bytes). Validación: %s.', $submission_id, $path, (int) ( $validation['size'] ?? 0 ), $validation['message'] ?? '' ) );
        if ( empty( $validation['valid'] ) ) {
            self::update_pdf_status( $submission_id, 'failed', $validation['message'] ?? __( 'Validación de PDF fallida.', 'agrocampo-post-venta' ) );
            return array(
                'status' => 'failed',
                'message' => $validation['message'] ?? __( 'Validación de PDF fallida.', 'agrocampo-post-venta' ),
                'attachment_id' => 0,
                'path' => $path,
            );
        }

        $attachment = array(
            'post_mime_type' => 'application/pdf',
            'post_title' => sanitize_file_name( $filename ),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attach_id = wp_insert_attachment( $attachment, $path );
        if ( ! $attach_id ) {
            self::update_pdf_status( $submission_id, 'failed', __( 'No se pudo registrar el PDF en la biblioteca de medios.', 'agrocampo-post-venta' ) );
            return array(
                'status' => 'failed',
                'message' => __( 'No se pudo registrar el PDF en la biblioteca de medios.', 'agrocampo-post-venta' ),
                'attachment_id' => 0,
                'path' => $path,
            );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attach_id, $path );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        AGP_PV_DB::update_pdf_attachment( $submission_id, (int) $attach_id );
        self::update_pdf_status( $submission_id, 'ready', '' );

        return array(
            'status' => 'ready',
            'message' => '',
            'attachment_id' => (int) $attach_id,
            'path' => $path,
        );
    }

    private static function generate_pdf_file( int $submission_id, array $submission, string $path ): bool {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont( 'Helvetica', '', 14 );
        $pdf->Cell( 0, 10, 'Informe Tecnico' );
        $pdf->Ln( 10 );

        $pdf->SetFont( 'Helvetica', '', 10 );
        $pdf->MultiCell( 0, 5, 'ID: ' . $submission_id );
        $pdf->MultiCell( 0, 5, 'Tecnico: ' . $submission['tecnico'] );
        $pdf->MultiCell( 0, 5, 'Cliente: ' . $submission['cliente'] );
        $pdf->MultiCell( 0, 5, 'Correo Cliente: ' . $submission['email_cliente'] );
        $pdf->MultiCell( 0, 5, 'Faena Lugar: ' . $submission['faena_lugar'] );
        $pdf->MultiCell( 0, 5, 'Maquina: ' . $submission['maquina'] );
        $pdf->MultiCell( 0, 5, 'Modelo: ' . $submission['modelo'] );
        $pdf->MultiCell( 0, 5, 'Serie: ' . $submission['serie'] );
        $pdf->MultiCell( 0, 5, 'N° Interno: ' . $submission['numero_interno'] );
        $pdf->MultiCell( 0, 5, 'Fecha: ' . $submission['fecha'] );
        $pdf->MultiCell( 0, 5, 'Horas: ' . $submission['horas'] );
        $pdf->MultiCell( 0, 5, 'Tipo de Servicio: ' . $submission['tipo_servicio'] );
        $pdf->MultiCell( 0, 5, 'Tipo de Mantencion: ' . $submission['tipo_mantencion'] );
        $pdf->MultiCell( 0, 5, 'Cantidad de Horas: ' . $submission['cantidad_horas'] );
        $pdf->MultiCell( 0, 5, 'Fecha Reparacion: ' . $submission['fecha_reparacion'] );
        $pdf->MultiCell( 0, 5, 'Fecha Cierre: ' . $submission['fecha_cierre'] );
        $pdf->Ln( 2 );

        $pdf->MultiCell( 0, 5, "Lubricantes: \n" . $submission['lubricantes'] );
        $pdf->Ln( 1 );
        $pdf->MultiCell( 0, 5, "Filtros Utilizados: \n" . $submission['filtros_utilizados'] );
        $pdf->Ln( 1 );
        $pdf->MultiCell( 0, 5, "Componentes Utilizados: \n" . $submission['componentes_utilizados'] );
        $pdf->Ln( 1 );
        $pdf->MultiCell( 0, 5, "Trabajos Realizados: \n" . $submission['trabajos_realizados'] );
        $pdf->Ln( 1 );
        $pdf->MultiCell( 0, 5, "Observaciones: \n" . $submission['observaciones'] );
        $pdf->Ln( 2 );

        $pdf->MultiCell( 0, 5, 'Firmas y Fotos:' );
        if ( ! empty( $submission['firma_cliente_id'] ) ) {
            $url = wp_get_attachment_url( $submission['firma_cliente_id'] );
            $pdf->MultiCell( 0, 5, 'Firma Cliente: ' . ( $url ?: '' ) );
        }
        if ( ! empty( $submission['firma_tecnico_id'] ) ) {
            $url = wp_get_attachment_url( $submission['firma_tecnico_id'] );
            $pdf->MultiCell( 0, 5, 'Firma Tecnico: ' . ( $url ?: '' ) );
        }

        $fotos = json_decode( (string) $submission['fotos_ids'], true );
        if ( is_array( $fotos ) && ! empty( $fotos ) ) {
            foreach ( $fotos as $foto_id ) {
                $url = wp_get_attachment_url( (int) $foto_id );
                if ( $url ) {
                    $pdf->MultiCell( 0, 5, 'Foto: ' . $url );
                }
            }
        }

        $pdf->Output( 'F', $path );
        return file_exists( $path );
    }

    private static function validate_pdf_file( string $path ): array {
        if ( ! file_exists( $path ) ) {
            return array(
                'valid' => false,
                'message' => __( 'No se encontró el archivo PDF.', 'agrocampo-post-venta' ),
            );
        }

        $size = filesize( $path );
        if ( ! $size || $size < 10240 ) {
            return array(
                'valid' => false,
                'message' => __( 'El PDF generado es demasiado pequeño o está vacío.', 'agrocampo-post-venta' ),
                'size' => (int) $size,
            );
        }

        $fh = fopen( $path, 'rb' );
        if ( ! $fh ) {
            return array(
                'valid' => false,
                'message' => __( 'No se pudo abrir el PDF para validación.', 'agrocampo-post-venta' ),
                'size' => (int) $size,
            );
        }

        $header = fread( $fh, 8 );
        fseek( $fh, -12, SEEK_END );
        $tail = fread( $fh, 12 );
        fclose( $fh );

        if ( false === strpos( $header, '%PDF-' ) ) {
            return array(
                'valid' => false,
                'message' => __( 'El PDF no contiene encabezado válido.', 'agrocampo-post-venta' ),
                'size' => (int) $size,
            );
        }

        if ( false === strpos( $tail, '%%EOF' ) ) {
            return array(
                'valid' => false,
                'message' => __( 'El PDF no contiene el marcador EOF.', 'agrocampo-post-venta' ),
                'size' => (int) $size,
            );
        }

        $qpdf_check = self::run_qpdf_check( $path );
        if ( null !== $qpdf_check['valid'] ) {
            $qpdf_check['size'] = (int) $size;
            return $qpdf_check;
        }

        $pypdf_check = self::run_pypdf_check( $path );
        if ( null !== $pypdf_check['valid'] ) {
            $pypdf_check['size'] = (int) $size;
            return $pypdf_check;
        }

        return array(
            'valid' => true,
            'message' => __( 'Validación básica OK (sin qpdf/PyPDF2 disponibles).', 'agrocampo-post-venta' ),
            'size' => (int) $size,
        );
    }

    private static function run_qpdf_check( string $path ): array {
        if ( ! function_exists( 'proc_open' ) ) {
            return array( 'valid' => null, 'message' => '' );
        }

        $binary = self::locate_binary( 'qpdf' );
        if ( ! $binary ) {
            return array( 'valid' => null, 'message' => '' );
        }

        $command = array( $binary, '--check', $path );
        $result = self::run_command( $command );
        if ( 0 === $result['exit_code'] ) {
            return array(
                'valid' => true,
                'message' => __( 'qpdf --check OK.', 'agrocampo-post-venta' ),
            );
        }

        return array(
            'valid' => false,
            'message' => $result['stderr'] ? $result['stderr'] : ( $result['stdout'] ? $result['stdout'] : __( 'qpdf --check falló.', 'agrocampo-post-venta' ) ),
        );
    }

    private static function run_pypdf_check( string $path ): array {
        if ( ! function_exists( 'proc_open' ) ) {
            return array( 'valid' => null, 'message' => '' );
        }

        $binary = self::locate_binary( 'python3' );
        if ( ! $binary ) {
            return array( 'valid' => null, 'message' => '' );
        }

        $temp = wp_tempnam( 'agp-pv-pdf-check' );
        if ( ! $temp ) {
            return array( 'valid' => null, 'message' => '' );
        }

        $script = "import sys\ntry:\n    from PyPDF2 import PdfReader\nexcept Exception as exc:\n    print('PyPDF2 not available', exc)\n    sys.exit(2)\ntry:\n    reader = PdfReader(sys.argv[1])\n    _ = len(reader.pages)\nexcept Exception as exc:\n    print(str(exc))\n    sys.exit(1)\nprint('OK')\n";
        file_put_contents( $temp, $script );

        $result = self::run_command( array( $binary, $temp, $path ) );
        unlink( $temp );

        if ( 0 === $result['exit_code'] ) {
            return array(
                'valid' => true,
                'message' => __( 'PyPDF2 OK.', 'agrocampo-post-venta' ),
            );
        }

        if ( 2 === $result['exit_code'] ) {
            return array(
                'valid' => null,
                'message' => '',
            );
        }

        return array(
            'valid' => false,
            'message' => $result['stderr'] ? $result['stderr'] : ( $result['stdout'] ? $result['stdout'] : __( 'PyPDF2 falló.', 'agrocampo-post-venta' ) ),
        );
    }

    private static function locate_binary( string $binary ): string {
        $paths = array( '/usr/bin/', '/usr/local/bin/' );
        foreach ( $paths as $prefix ) {
            $candidate = $prefix . $binary;
            if ( file_exists( $candidate ) && is_executable( $candidate ) ) {
                return $candidate;
            }
        }

        $command = array( 'command', '-v', $binary );
        $result = self::run_command( $command );
        if ( 0 === $result['exit_code'] ) {
            return trim( $result['stdout'] );
        }

        return '';
    }

    private static function run_command( array $command ): array {
        $descriptor = array(
            1 => array( 'pipe', 'w' ),
            2 => array( 'pipe', 'w' ),
        );

        $process = proc_open( $command, $descriptor, $pipes );
        if ( ! is_resource( $process ) ) {
            return array(
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => __( 'No se pudo ejecutar el comando de validación.', 'agrocampo-post-venta' ),
            );
        }

        $stdout = stream_get_contents( $pipes[1] );
        $stderr = stream_get_contents( $pipes[2] );
        foreach ( $pipes as $pipe ) {
            fclose( $pipe );
        }
        $exit_code = proc_close( $process );

        return array(
            'exit_code' => (int) $exit_code,
            'stdout' => trim( (string) $stdout ),
            'stderr' => trim( (string) $stderr ),
        );
    }

    private static function update_pdf_status( int $submission_id, string $status, string $error ): void {
        AGP_PV_DB::update_pdf_status( $submission_id, $status, $error );
    }

    private static function log( string $message ): void {
        if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) && ! get_option( 'agp_pv_debug' ) ) {
            return;
        }

        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[agp_pv_pdf] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
}
