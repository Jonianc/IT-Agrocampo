<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once AGP_PV_PLUGIN_DIR . 'includes/fpdf/fpdf.php';

class AGP_PV_PDF_Document extends FPDF {
    private int $submission_id = 0;
    private string $issue_date = '';
    private string $logo_path = '';
    private float $logo_width_mm = 38.0;
    private float $label_width_mm = 45.0;
    private float $gap_mm = 4.0;
    private float $footer_height_mm = 18.0;

    public function configure( int $submission_id, string $issue_date, string $logo_path, float $logo_width_mm ): void {
        $this->submission_id = $submission_id;
        $this->issue_date = $issue_date;
        $this->logo_path = $logo_path;
        $this->logo_width_mm = $logo_width_mm;

        $margin = $this->mm_to_pt( 12 );
        $this->SetMargins( $margin, $margin, $margin );
        $this->SetAutoPageBreak( true, $this->mm_to_pt( $this->footer_height_mm ) );
        $this->AddPage( 'P', 'A4' );
    }

    public function Header(): void {
        $this->SetFont( 'Helvetica', 'B', 14 );
        $start_y = $this->GetY();
        $logo_height = 0.0;

        if ( $this->logo_path && file_exists( $this->logo_path ) ) {
            $size = getimagesize( $this->logo_path );
            if ( $size ) {
                $width_pt = $this->mm_to_pt( $this->logo_width_mm );
                $ratio = $size[1] / $size[0];
                $height_pt = $width_pt * $ratio;
                $this->Image( $this->logo_path, $this->GetX(), $start_y, $width_pt, 0 );
                $logo_height = $height_pt;
            }
        }

        $title = __( 'INFORME TÉCNICO', 'agrocampo-post-venta' );
        $title_width = $this->GetStringWidth( $title );
        $center_x = ( $this->page_width - $title_width ) / 2;
        $this->SetXY( $center_x, $start_y );
        $this->Cell( $title_width, $this->mm_to_pt( 6 ), $title );

        $this->SetFont( 'Helvetica', '', 10 );
        $right_block_width = $this->mm_to_pt( 35 );
        $right_x = $this->page_width - $this->r_margin - $right_block_width;
        $this->SetXY( $right_x, $start_y );
        $this->MultiCell(
            $right_block_width,
            $this->mm_to_pt( 5 ),
            sprintf( "IT: %d\n%s", $this->submission_id, $this->issue_date )
        );

        $header_height = max( $logo_height, $this->mm_to_pt( 16 ) );
        $line_y = $start_y + $header_height + $this->mm_to_pt( 3 );
        $this->Line( $this->l_margin, $line_y, $this->page_width - $this->r_margin, $line_y );
        $this->SetY( $line_y + $this->mm_to_pt( 3 ) );
    }

    public function Footer(): void {
        $this->SetFont( 'Helvetica', '', 9 );
        $footer_text = __( 'Talca • Linares • Parral   |   +56 9 9748 5650', 'agrocampo-post-venta' );
        $page_text = sprintf( __( 'Página %d', 'agrocampo-post-venta' ), $this->page );
        $full_text = $footer_text . '   |   ' . $page_text;
        $width = $this->GetStringWidth( $full_text );
        $y = $this->page_height - $this->mm_to_pt( 10 );
        $x = ( $this->page_width - $width ) / 2;
        $this->SetXY( $x, $y );
        $this->Cell( $width, $this->mm_to_pt( 4 ), $full_text );
    }

    public function section_title( string $text ): void {
        $this->ensure_space( $this->mm_to_pt( 8 ) );
        $this->SetFont( 'Helvetica', 'B', 11 );
        $this->Cell( 0, $this->mm_to_pt( 6 ), $text );
        $this->Ln( $this->mm_to_pt( 2 ) );
        $line_y = $this->GetY();
        $this->Line( $this->l_margin, $line_y, $this->page_width - $this->r_margin, $line_y );
        $this->Ln( $this->mm_to_pt( 4 ) );
        $this->SetFont( 'Helvetica', '', 10 );
    }

    public function row2( string $label, string $value ): void {
        $label_width = $this->mm_to_pt( $this->label_width_mm );
        $gap = $this->mm_to_pt( $this->gap_mm );
        $value_width = $this->page_width - $this->l_margin - $this->r_margin - $label_width - $gap;
        $lines = $this->wrap_text( $value, $value_width );
        $line_height = $this->mm_to_pt( 5 );
        $height = max( 1, count( $lines ) ) * $line_height;

        $this->ensure_space( $height );
        $start_x = $this->l_margin;
        $start_y = $this->GetY();

        $this->SetFont( 'Helvetica', 'B', 10 );
        $this->SetXY( $start_x, $start_y );
        $this->Cell( $label_width, $line_height, $label );

        $this->SetFont( 'Helvetica', '', 10 );
        $this->SetXY( $start_x + $label_width + $gap, $start_y );
        foreach ( $lines as $index => $line ) {
            $this->Cell( $value_width, $line_height, $line );
            if ( $index < count( $lines ) - 1 ) {
                $this->Ln( $line_height );
                $this->SetX( $start_x + $label_width + $gap );
            }
        }

        $this->SetY( $start_y + $height );
        $this->SetX( $this->l_margin );
    }

    public function box_text( string $title, string $text ): void {
        $this->ensure_space( $this->mm_to_pt( 10 ) );
        $this->SetFont( 'Helvetica', 'B', 10 );
        $this->Cell( 0, $this->mm_to_pt( 6 ), $title );
        $this->Ln( $this->mm_to_pt( 1 ) );

        $this->SetFont( 'Helvetica', '', 10 );
        $box_width = $this->page_width - $this->l_margin - $this->r_margin;
        $line_height = $this->mm_to_pt( 5 );
        $lines = $this->wrap_text( $text, $box_width - $this->mm_to_pt( 4 ) );
        $text_height = max( 1, count( $lines ) ) * $line_height;
        $box_height = $text_height + $this->mm_to_pt( 4 );

        $this->ensure_space( $box_height );
        $x = $this->l_margin;
        $y = $this->GetY();
        $this->Rect( $x, $y, $box_width, $box_height );

        $this->SetXY( $x + $this->mm_to_pt( 2 ), $y + $this->mm_to_pt( 2 ) );
        foreach ( $lines as $index => $line ) {
            $this->Cell( $box_width - $this->mm_to_pt( 4 ), $line_height, $line );
            if ( $index < count( $lines ) - 1 ) {
                $this->Ln( $line_height );
                $this->SetX( $x + $this->mm_to_pt( 2 ) );
            }
        }

        $this->SetY( $y + $box_height + $this->mm_to_pt( 2 ) );
    }

    public function signature_row( array $left, array $right ): void {
        $box_height = $this->mm_to_pt( 35 );
        $box_width = ( $this->page_width - $this->l_margin - $this->r_margin - $this->mm_to_pt( 6 ) ) / 2;
        $gap = $this->mm_to_pt( 6 );

        $this->ensure_space( $box_height + $this->mm_to_pt( 10 ) );
        $y = $this->GetY();
        $x = $this->l_margin;

        $this->signature_box( $left['label'], $left['path'], $x, $y, $box_width, $box_height );
        $this->signature_box( $right['label'], $right['path'], $x + $box_width + $gap, $y, $box_width, $box_height );

        $this->SetY( $y + $box_height + $this->mm_to_pt( 6 ) );
    }

    private function signature_box( string $label, string $path, float $x, float $y, float $width, float $height ): void {
        $this->SetFont( 'Helvetica', 'B', 9 );
        $this->SetXY( $x, $y );
        $this->Cell( $width, $this->mm_to_pt( 5 ), $label );

        $box_y = $y + $this->mm_to_pt( 5 );
        $this->Rect( $x, $box_y, $width, $height );
        if ( $path && file_exists( $path ) ) {
            $size = getimagesize( $path );
            if ( $size ) {
                $ratio = $size[1] / $size[0];
                $max_w = $width - $this->mm_to_pt( 6 );
                $max_h = $height - $this->mm_to_pt( 6 );
                $img_w = $max_w;
                $img_h = $img_w * $ratio;
                if ( $img_h > $max_h ) {
                    $img_h = $max_h;
                    $img_w = $img_h / $ratio;
                }
                $img_x = $x + ( $width - $img_w ) / 2;
                $img_y = $box_y + ( $height - $img_h ) / 2;
                $this->Image( $path, $img_x, $img_y, $img_w, $img_h );
                return;
            }
        }

        $this->SetFont( 'Helvetica', '', 9 );
        $this->SetXY( $x, $box_y + ( $height / 2 ) );
        $this->Cell( $width, $this->mm_to_pt( 4 ), __( 'Firma no disponible', 'agrocampo-post-venta' ) );
    }

    private function wrap_text( string $text, float $width ): array {
        $text = trim( $text );
        if ( '' === $text ) {
            return array( '' );
        }

        $words = preg_split( '/\s+/', $text );
        $lines = array();
        $current = '';
        foreach ( $words as $word ) {
            $test = '' === $current ? $word : $current . ' ' . $word;
            if ( $this->GetStringWidth( $test ) <= $width ) {
                $current = $test;
                continue;
            }

            if ( '' !== $current ) {
                $lines[] = $current;
            }
            $current = $word;
        }

        if ( '' !== $current ) {
            $lines[] = $current;
        }

        return $lines;
    }

    private function ensure_space( float $height ): void {
        if ( $this->auto_page_break && ( $this->GetY() + $height ) > $this->page_break_trigger ) {
            $this->AddPage( 'P', 'A4' );
        }
    }

    private function mm_to_pt( float $mm ): float {
        return ( $mm * 72 ) / 25.4;
    }
}

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
        $logo_config = self::get_logo_config();
        $issue_date = date_i18n( 'd-m-Y' );
        $document = new AGP_PV_PDF_Document();
        $document->configure( $submission_id, $issue_date, $logo_config['path'], $logo_config['width_mm'] );

        $tipo_servicio_label = $submission['tipo_servicio_label'] ?? self::map_tipo_servicio( (string) $submission['tipo_servicio'] );
        $tipo_mantencion_label = $submission['tipo_mantencion_label'] ?? self::map_tipo_mantencion( (string) $submission['tipo_mantencion'] );

        $document->section_title( __( 'Datos Generales', 'agrocampo-post-venta' ) );
        $document->row2( __( 'Técnico', 'agrocampo-post-venta' ), (string) $submission['tecnico'] );
        $document->row2( __( 'Cliente', 'agrocampo-post-venta' ), (string) $submission['cliente'] );
        $document->row2( __( 'Correo Cliente', 'agrocampo-post-venta' ), (string) $submission['email_cliente'] );
        $document->row2( __( 'Faena / Lugar', 'agrocampo-post-venta' ), (string) $submission['faena_lugar'] );

        $document->section_title( __( 'Equipo', 'agrocampo-post-venta' ) );
        $document->row2( __( 'Máquina', 'agrocampo-post-venta' ), (string) $submission['maquina'] );
        $document->row2( __( 'Modelo', 'agrocampo-post-venta' ), (string) $submission['modelo'] );
        $document->row2( __( 'Serie', 'agrocampo-post-venta' ), (string) $submission['serie'] );
        $document->row2( __( 'N° Interno', 'agrocampo-post-venta' ), (string) $submission['numero_interno'] );
        $document->row2( __( 'Fecha', 'agrocampo-post-venta' ), (string) $submission['fecha'] );
        $document->row2( __( 'Horas', 'agrocampo-post-venta' ), (string) $submission['horas'] );

        $document->section_title( __( 'Servicio', 'agrocampo-post-venta' ) );
        $document->row2( __( 'Tipo de Servicio', 'agrocampo-post-venta' ), (string) $tipo_servicio_label );
        $document->row2( __( 'Tipo de Mantención', 'agrocampo-post-venta' ), (string) $tipo_mantencion_label );
        $document->row2( __( 'Cantidad de Horas', 'agrocampo-post-venta' ), (string) $submission['cantidad_horas'] );
        $document->row2( __( 'Fecha Reparación', 'agrocampo-post-venta' ), (string) $submission['fecha_reparacion'] );
        $document->row2( __( 'Fecha Cierre', 'agrocampo-post-venta' ), (string) $submission['fecha_cierre'] );

        $document->section_title( __( 'Detalle', 'agrocampo-post-venta' ) );
        $document->box_text( __( 'Lubricantes', 'agrocampo-post-venta' ), (string) $submission['lubricantes'] );
        $document->box_text( __( 'Filtros Utilizados', 'agrocampo-post-venta' ), (string) $submission['filtros_utilizados'] );
        $document->box_text( __( 'Componentes Utilizados', 'agrocampo-post-venta' ), (string) $submission['componentes_utilizados'] );
        $document->box_text( __( 'Trabajos Realizados', 'agrocampo-post-venta' ), (string) $submission['trabajos_realizados'] );
        $document->box_text( __( 'Observaciones', 'agrocampo-post-venta' ), (string) $submission['observaciones'] );

        $document->section_title( __( 'Firmas', 'agrocampo-post-venta' ) );
        $document->signature_row(
            array(
                'label' => __( 'Firma Cliente', 'agrocampo-post-venta' ),
                'path' => self::resolve_attachment_path( (int) ( $submission['firma_cliente_id'] ?? 0 ) ),
            ),
            array(
                'label' => __( 'Firma Técnico', 'agrocampo-post-venta' ),
                'path' => self::resolve_attachment_path( (int) ( $submission['firma_tecnico_id'] ?? 0 ) ),
            )
        );

        $document->Output( 'F', $path );
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
        if ( ! $size || $size < 1024 ) {
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

    private static function get_logo_config(): array {
        $logo_id = absint( get_option( 'agp_pv_logo_attachment_id', 0 ) );
        $width_mm = (float) get_option( 'agp_pv_logo_width_mm', 38 );
        $path = '';

        if ( $logo_id ) {
            $candidate = get_attached_file( $logo_id );
            if ( $candidate && file_exists( $candidate ) ) {
                $path = $candidate;
            }
        }

        return array(
            'path' => $path,
            'width_mm' => $width_mm > 0 ? $width_mm : 38.0,
        );
    }

    private static function resolve_attachment_path( int $attachment_id ): string {
        if ( ! $attachment_id ) {
            return '';
        }

        $path = get_attached_file( $attachment_id );
        if ( $path && file_exists( $path ) ) {
            return $path;
        }

        $url = wp_get_attachment_url( $attachment_id );
        if ( $url ) {
            $uploads = wp_upload_dir();
            if ( 0 === strpos( $url, $uploads['baseurl'] ) ) {
                $relative = substr( $url, strlen( $uploads['baseurl'] ) );
                $candidate = $uploads['basedir'] . $relative;
                if ( file_exists( $candidate ) ) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private static function map_tipo_servicio( string $value ): string {
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

    private static function map_tipo_mantencion( string $value ): string {
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

    private static function log( string $message ): void {
        if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) && ! get_option( 'agp_pv_debug' ) ) {
            return;
        }

        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[agp_pv_pdf] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
}
