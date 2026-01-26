<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once AGP_PV_PLUGIN_DIR . 'includes/fpdf/fpdf.php';

class AGP_PV_PDF {
    public static function generate_pdf( int $submission_id, array $submission ): ?string {
        if ( ! class_exists( 'FPDF' ) ) {
            return null;
        }

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

        $output = $pdf->Output( 'S' );
        if ( '' === $output ) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        $filename = 'agp-pv-' . $submission_id . '.pdf';
        $path = trailingslashit( $upload_dir['path'] ) . $filename;
        file_put_contents( $path, $output );

        return $path;
    }
}
