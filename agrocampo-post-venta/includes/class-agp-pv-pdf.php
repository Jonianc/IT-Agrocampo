<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'FPDF' ) ) {
    require_once AGP_PV_PLUGIN_DIR . 'includes/fpdf/fpdf.php';
}

/**
 * PDF document for Post Venta.
 *
 * Uses the official FPDF library (units in mm).
 */
class AGP_PV_PDF_Document extends FPDF {
    private int $report_id = 0;
    private string $issue_date = '';
    private string $logo_path = '';
    private float $logo_width_mm = 38.0;
    private float $label_width_mm = 42.0;
    private float $gap_mm = 3.0;
    private float $card_x = 0.0;
    private float $card_y = 0.0;
    private float $card_w = 0.0;
    private bool $card_open = false;
    private bool $force_signature_compact = false;

    /** @var string[] */
    private array $warnings = array();

    public function configure( int $report_id, string $issue_date, string $logo_path, float $logo_width_mm ): void {
        $this->report_id      = $report_id;
        $this->issue_date     = $issue_date;
        $this->logo_path      = $logo_path;
        $this->logo_width_mm  = $logo_width_mm > 0 ? $logo_width_mm : 38.0;

        // Units are mm in FPDF.
        $this->SetMargins( 12, 12, 12 );
        $this->SetAutoPageBreak( true, 18 );
        $this->AddPage( 'P', 'A4' );
        $this->SetFont( 'Helvetica', '', 10 );
        $this->SetTextColor( 20, 20, 20 );
        $this->SetDrawColor( 70, 70, 70 );
        $this->SetLineWidth( 0.2 );
    }

    /**
     * Override FPDF fatal Error() to throw exceptions instead of die().
     *
     * @throws Exception
     */
    public function Error( $msg ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        throw new Exception( 'FPDF: ' . (string) $msg );
    }

    public function Header(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $start_y = $this->GetY();
        $logo_h  = 0.0;

        // Logo (left).
        if ( $this->logo_path && file_exists( $this->logo_path ) ) {
            $prepared = AGP_PV_PDF::prepare_image_for_fpdf( $this->logo_path, 'logo' );
            if ( ! empty( $prepared['path'] ) && file_exists( $prepared['path'] ) ) {
                $size = @getimagesize( $prepared['path'] );
                if ( $size && ! empty( $size[0] ) ) {
                    $ratio  = (float) $size[1] / (float) $size[0];
                    $logo_h = $this->logo_width_mm * $ratio;

                    try {
                        $this->Image( $prepared['path'], $this->lMargin, $start_y, $this->logo_width_mm );
                    } catch ( Exception $e ) {
                        $this->warnings[] = 'Logo omitido: ' . $e->getMessage();
                    }
                }
            } elseif ( ! empty( $prepared['warning'] ) ) {
                $this->warnings[] = $prepared['warning'];
            }
        }

        // Title (center).
        $this->SetFont( 'Helvetica', 'B', 15 );
        $title      = self::enc( __( 'INFORME TÉCNICO', 'agrocampo-post-venta' ) );
        $title_w    = $this->GetStringWidth( $title );
        $center_x   = ( $this->w - $title_w ) / 2;
        $title_y    = $start_y + 3;
        $this->SetXY( $center_x, $title_y );
        $this->Cell( $title_w, 7.5, $title, 0, 0, 'C' );

        // Right block (IT + date).
        $this->SetFont( 'Helvetica', 'B', 10 );
        $right_w = 38;
        $right_h = 10.8;
        $right_x = $this->w - $this->rMargin - $right_w;
        $this->RoundedRect( $right_x, $start_y, $right_w, $right_h, 2.5, 'D' );
        $this->SetXY( $right_x + 2.2, $start_y + 1.8 );
        $this->Cell( $right_w - 4.4, 3.8, self::enc( sprintf( 'IT: %d', $this->report_id ) ), 0, 2, 'L' );
        $this->SetFont( 'Helvetica', '', 8 );
        $this->SetX( $right_x + 2.2 );
        $this->Cell( $right_w - 4.4, 3.6, self::enc( $this->issue_date ), 0, 0, 'L' );

        // Separator line.
        $header_h = max( $logo_h, 17.0 );
        $line_y   = $start_y + $header_h + 4;
        $this->Line( $this->lMargin, $line_y, $this->w - $this->rMargin, $line_y );
        $this->SetY( $line_y + 4.2 );
    }

    public function Footer(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $this->SetY( -12 );
        $this->SetFont( 'Helvetica', '', 9.5 );
        $footer_text = self::enc( __( 'Talca • Linares • Parral   |   +56 9 9748 5650', 'agrocampo-post-venta' ) );
        $page_text   = self::enc( sprintf( __( 'Página %d', 'agrocampo-post-venta' ), $this->PageNo() ) );
        $full        = $footer_text . self::enc( '   |   ' ) . $page_text;
        $this->Cell( 0, 5.2, $full, 0, 0, 'C' );
    }

    public function section_title( string $text ): void {
        $this->ensure_space( 12 );
        $this->SetFont( 'Helvetica', 'B', 11.5 );
        $this->Cell( 0, 5.8, self::enc( $text ), 0, 1, 'L' );
        $y = $this->GetY();
        $this->Line( $this->lMargin, $y, $this->w - $this->rMargin, $y );
        $this->Ln( 3.2 );
        $this->SetFont( 'Helvetica', '', 10 );
    }

    public function card_start( string $title, float $min_content_height = 20.0 ): void {
        $header_h = 11.5;
        $this->ensure_space( $header_h + max( 8.0, $min_content_height ) );

        $this->card_x = $this->lMargin;
        $this->card_y = $this->GetY();
        $this->card_w = $this->w - $this->lMargin - $this->rMargin;
        $this->card_open = true;

        $this->SetXY( $this->card_x + 3, $this->card_y + 3.2 );
        $this->SetFont( 'Helvetica', 'B', 12 );
        $this->Cell( $this->card_w - 6, 5.2, self::enc( $title ), 0, 1, 'L' );

        $line_y = $this->GetY() + 0.6;
        $this->Line( $this->card_x + 2.8, $line_y, $this->card_x + $this->card_w - 2.8, $line_y );
        $this->SetXY( $this->card_x + 3, $line_y + 2.1 );
    }

    public function card_end( float $bottom_padding = 2.2 ): void {
        if ( ! $this->card_open ) {
            return;
        }

        $end_y = $this->GetY() + $bottom_padding;
        $this->RoundedRect( $this->card_x, $this->card_y, $this->card_w, $end_y - $this->card_y, 3.2, 'D' );
        $this->SetXY( $this->lMargin, $end_y + 1.5 );
        $this->card_open = false;
    }

    /**
     * Two-column row (label/value), with wrapping.
     */
    public function row2( string $label, string $value ): void {
        $label_w = $this->label_width_mm;
        $gap     = $this->gap_mm;
        $value_w = $this->w - $this->lMargin - $this->rMargin - $label_w - $gap;

        $line_h = 5.3;

        $label = self::enc( $label );
        $value = self::enc( $value );

        $nb     = max( 1, $this->NbLines( $value_w, $value ) );
        $row_h  = $line_h * $nb;

        $this->ensure_space( $row_h + 1.5 );

        $x = $this->GetX();
        $y = $this->GetY();

        // Label.
        $this->SetFont( 'Helvetica', 'B', 10.2 );
        $this->Cell( $label_w, $row_h, $label, 0, 0, 'L' );

        // Value.
        $this->SetFont( 'Helvetica', '', 10.1 );
        $this->SetXY( $x + $label_w + $gap, $y );
        $this->MultiCell( $value_w, $line_h, $value, 0, 'L' );

        $this->SetXY( $x, $y + $row_h + 1.4 );
    }


    /**
     * Compact two-pairs row: (label:value) + (label:value) on the same line.
     * Best for short fields to save vertical space.
     */
    public function row4( string $label1, string $value1, string $label2, string $value2 ): void {
        $usable_w  = $this->w - $this->lMargin - $this->rMargin;
        $pair_gap  = 7.0;
        $pair_w    = ( $usable_w - $pair_gap ) / 2;

        // Pair internal widths.
        $label_w   = 23.0;
        $gap       = 3.2;
        $value_w   = $pair_w - $label_w - $gap;

        $line_h = 5.0;

        $label1 = self::enc( $label1 );
        $value1 = self::enc( $value1 );
        $label2 = self::enc( $label2 );
        $value2 = self::enc( $value2 );

        $nb1   = max( 1, $this->NbLines( $value_w, $value1 ) );
        $nb2   = max( 1, $this->NbLines( $value_w, $value2 ) );
        $row_h = $line_h * max( $nb1, $nb2 );

        $this->ensure_space( $row_h + 1.2 );

        $x0 = $this->lMargin;
        $y0 = $this->GetY();

        // Left pair.
        $this->SetFont( 'Helvetica', 'B', 10.2 );
        $this->SetXY( $x0, $y0 );
        $this->Cell( $label_w, $row_h, $label1, 0, 0, 'L' );

        $this->SetFont( 'Helvetica', '', 10.1 );
        $this->SetXY( $x0 + $label_w + $gap, $y0 );
        $this->MultiCell( $value_w, $line_h, $value1, 0, 'L' );

        // Right pair.
        $x1 = $x0 + $pair_w + $pair_gap;
        $this->SetFont( 'Helvetica', 'B', 10.2 );
        $this->SetXY( $x1, $y0 );
        $this->Cell( $label_w, $row_h, $label2, 0, 0, 'L' );

        $this->SetFont( 'Helvetica', '', 10.1 );
        $this->SetXY( $x1 + $label_w + $gap, $y0 );
        $this->MultiCell( $value_w, $line_h, $value2, 0, 'L' );

        $this->SetXY( $x0, $y0 + $row_h + 1.2 );
    }

    /**
     * Full-width bordered box with text.
     */
    public function box_text( string $title, string $text ): void {
        $title = self::enc( $title );
        $text  = self::enc( $text );

        $this->ensure_space( 14 );

        $this->SetFont( 'Helvetica', 'B', 10.4 );
        $this->Cell( 0, 6.3, $title, 0, 1, 'L' );
        $this->SetFont( 'Helvetica', '', 10.1 );

        $box_w     = $this->w - $this->lMargin - $this->rMargin;
        $line_h    = 4.8;
        $padding   = 2.1;
        $inner_w   = $box_w - ( 2 * $padding );
        $nb        = max( 1, $this->NbLines( $inner_w, $text ) );
        $text_h    = $nb * $line_h;
        $box_h     = $text_h + ( 2 * $padding );

        $this->ensure_space( $box_h + 2.2 );

        $x = $this->lMargin;
        $y = $this->GetY();

        $this->Rect( $x, $y, $box_w, $box_h );
        $this->SetXY( $x + $padding, $y + $padding );
        $this->MultiCell( $inner_w, $line_h, $text, 0, 'L' );

        $this->SetXY( $this->lMargin, $y + $box_h + 3.2 );
    }
    /**
     * Render text as compact row or boxed block depending on length/content.
     */
    public function adaptive_text( string $title, string $text ): void {
        $trimmed = trim( $text );
        if ( '' === $trimmed ) {
            return;
        }

        $is_placeholder = strtolower( $trimmed ) === strtolower( __( 'No informado', 'agrocampo-post-venta' ) );
        $is_short_single_line = strlen( $trimmed ) <= 90 && false === strpos( $trimmed, "\n" );

        if ( $is_placeholder || $is_short_single_line ) {
            $this->row2( $title, $trimmed );
            return;
        }

        $this->box_text( $title, $trimmed );
    }

    /**
     * Render two medium/short lists in a two-column 50/50 layout.
     * Returns true when rendered in two columns; false to fallback single-column.
     */
    public function detail_lists_two_columns( string $title_left, string $text_left, string $title_right, string $text_right ): bool {
        $left  = trim( $text_left );
        $right = trim( $text_right );

        if ( '' === $left || '' === $right ) {
            return false;
        }

        $usable_w = $this->w - $this->lMargin - $this->rMargin;
        $gap      = 6.0;
        $col_w    = ( $usable_w - $gap ) / 2;

        $title_h    = 5.8;
        $padding    = 1.7;
        $line_h     = 4.6;
        $inner_w    = $col_w - ( 2 * $padding );

        $left_enc   = self::enc( $left );
        $right_enc  = self::enc( $right );
        $nb_left    = max( 1, $this->NbLines( $inner_w, $left_enc ) );
        $nb_right   = max( 1, $this->NbLines( $inner_w, $right_enc ) );
        $max_lines  = max( $nb_left, $nb_right );

        // Auto fallback to single column when one list is too long.
        if ( $max_lines > 8 ) {
            return false;
        }

        $box_h = ( $max_lines * $line_h ) + ( 2 * $padding );
        $row_h = $title_h + $box_h + 2.0;
        $this->ensure_space( $row_h + 1.5 );

        $x = $this->lMargin;
        $y = $this->GetY();

        // Left column.
        $this->SetFont( 'Helvetica', 'B', 10.4 );
        $this->SetXY( $x, $y );
        $this->Cell( $col_w, $title_h, self::enc( $title_left ), 0, 0, 'L' );
        $left_box_y = $y + $title_h;
        $this->Rect( $x, $left_box_y, $col_w, $box_h );
        $this->SetFont( 'Helvetica', '', 10.1 );
        $this->SetXY( $x + $padding, $left_box_y + $padding );
        $this->MultiCell( $inner_w, $line_h, $left_enc, 0, 'L' );

        // Right column.
        $x2 = $x + $col_w + $gap;
        $this->SetFont( 'Helvetica', 'B', 10.4 );
        $this->SetXY( $x2, $y );
        $this->Cell( $col_w, $title_h, self::enc( $title_right ), 0, 0, 'L' );
        $right_box_y = $y + $title_h;
        $this->Rect( $x2, $right_box_y, $col_w, $box_h );
        $this->SetFont( 'Helvetica', '', 10.1 );
        $this->SetXY( $x2 + $padding, $right_box_y + $padding );
        $this->MultiCell( $inner_w, $line_h, $right_enc, 0, 'L' );

        $this->SetXY( $this->lMargin, $y + $row_h );
        return true;
    }

    public function set_signature_compact( bool $compact ): void {
        $this->force_signature_compact = $compact;
    }

    public function signature_row( array $left, array $right ): void {
        $gap       = 6.5;
        $box_h     = 27.0;
        $tail_gap  = 5.2;
        $box_w     = ( $this->w - $this->lMargin - $this->rMargin - $gap ) / 2;

        $space_left = $this->space_left();
        if ( $this->force_signature_compact || ( $space_left < 36.0 && $space_left >= 30.0 ) ) {
            // Compact mode to avoid pushing "Firmas" alone to a new page.
            $box_h    = 22.0;
            $tail_gap = 3.8;
        }

        $this->ensure_space( $box_h + 7.0 );

        $y = $this->GetY();
        $x = $this->lMargin;

        $this->signature_box( (string) $left['label'], (string) $left['path'], $x, $y, $box_w, $box_h );
        $this->signature_box( (string) $right['label'], (string) $right['path'], $x + $box_w + $gap, $y, $box_w, $box_h );

        $this->SetXY( $this->lMargin, $y + $box_h + $tail_gap );
        $this->force_signature_compact = false;
    }

    private function signature_box( string $label, string $path, float $x, float $y, float $w, float $h ): void {
        $label = self::enc( $label );

        $this->SetFont( 'Helvetica', 'B', 10 );
        $this->SetXY( $x, $y );
        $this->Cell( $w, 5.5, $label, 0, 0, 'L' );

        $box_y = $y + 6;
        $this->Rect( $x, $box_y, $w, $h );

        $this->SetFont( 'Helvetica', '', 9.5 );

        if ( $path && file_exists( $path ) ) {
            $prepared = AGP_PV_PDF::prepare_image_for_fpdf( $path, 'signature' );
            $img_path = $prepared['path'] ?? '';

            if ( $img_path && file_exists( $img_path ) ) {
                $size = @getimagesize( $img_path );
                if ( $size && ! empty( $size[0] ) ) {
                    $ratio = (float) $size[1] / (float) $size[0];
                    $max_w = $w - 6;
                    $max_h = $h - 6;

                    $img_w = $max_w;
                    $img_h = $img_w * $ratio;

                    if ( $img_h > $max_h ) {
                        $img_h = $max_h;
                        $img_w = $img_h / $ratio;
                    }

                    $img_x = $x + ( $w - $img_w ) / 2;
                    $img_y = $box_y + ( $h - $img_h ) / 2;

                    try {
                        $this->Image( $img_path, $img_x, $img_y, $img_w, $img_h );
                        return;
                    } catch ( Exception $e ) {
                        $this->warnings[] = 'Firma omitida: ' . $e->getMessage();
                    }
                }
            } elseif ( ! empty( $prepared['warning'] ) ) {
                $this->warnings[] = $prepared['warning'];
            }
        }

        $this->SetXY( $x, $box_y + ( $h / 2 ) - 2 );
        $this->Cell( $w, 4.2, self::enc( __( 'Firma no disponible', 'agrocampo-post-venta' ) ), 0, 0, 'C' );
    }

    /**
     * Ensure there is space on the page; otherwise add a new page.
     */
    private function ensure_space( float $h ): void {
        if ( ( $this->GetY() + $h ) > ( $this->h - $this->bMargin ) ) {
            $this->AddPage( 'P', 'A4' );
        }
    }

    private function space_left(): float {
        return ( $this->h - $this->bMargin ) - $this->GetY();
    }

    public function get_space_left(): float {
        return $this->space_left();
    }

    /**
     * Rounded rectangle helper (FPDF script style).
     */
    private function RoundedRect( float $x, float $y, float $w, float $h, float $r, string $style = '' ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $k  = $this->k;
        $hp = $this->h;

        $op = 'S';
        if ( 'F' === $style ) {
            $op = 'f';
        } elseif ( 'FD' === $style || 'DF' === $style ) {
            $op = 'B';
        }

        $my_arc = 4 / 3 * ( sqrt( 2 ) - 1 );

        $this->_out( sprintf( '%.2F %.2F m', ( $x + $r ) * $k, ( $hp - $y ) * $k ) );
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out( sprintf( '%.2F %.2F l', $xc * $k, ( $hp - $y ) * $k ) );
        $this->arc( $xc + $r * $my_arc, $yc - $r, $xc + $r, $yc - $r * $my_arc, $xc + $r, $yc );
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out( sprintf( '%.2F %.2F l', ( $x + $w ) * $k, ( $hp - $yc ) * $k ) );
        $this->arc( $xc + $r, $yc + $r * $my_arc, $xc + $r * $my_arc, $yc + $r, $xc, $yc + $r );
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out( sprintf( '%.2F %.2F l', $xc * $k, ( $hp - ( $y + $h ) ) * $k ) );
        $this->arc( $xc - $r * $my_arc, $yc + $r, $xc - $r, $yc + $r * $my_arc, $xc - $r, $yc );
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out( sprintf( '%.2F %.2F l', $x * $k, ( $hp - $yc ) * $k ) );
        $this->arc( $xc - $r, $yc - $r * $my_arc, $xc - $r * $my_arc, $yc - $r, $xc, $yc - $r );
        $this->_out( $op );
    }

    private function arc( float $x1, float $y1, float $x2, float $y2, float $x3, float $y3 ): void {
        $h = $this->h;
        $k = $this->k;
        $this->_out(
            sprintf(
                '%.2F %.2F %.2F %.2F %.2F %.2F c',
                $x1 * $k,
                ( $h - $y1 ) * $k,
                $x2 * $k,
                ( $h - $y2 ) * $k,
                $x3 * $k,
                ( $h - $y3 ) * $k
            )
        );
    }

    /**
     * Compute number of lines a MultiCell of width w will take.
     * Based on official FPDF tutorial.
     */
    private function NbLines( float $w, string $txt ): int { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $cw = &$this->CurrentFont['cw'];
        if ( 0 == $w ) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ( $w - 2 * $this->cMargin ) * 1000 / $this->FontSize;
        $s    = str_replace( "\r", '', $txt );
        $nb   = strlen( $s );
        if ( $nb > 0 && "\n" === $s[ $nb - 1 ] ) {
            $nb--;
        }
        $sep = -1;
        $i   = 0;
        $j   = 0;
        $l   = 0;
        $nl  = 1;
        while ( $i < $nb ) {
            $c = $s[ $i ];
            if ( "\n" === $c ) {
                $i++;
                $sep = -1;
                $j   = $i;
                $l   = 0;
                $nl++;
                continue;
            }
            if ( ' ' === $c ) {
                $sep = $i;
            }
            $l += $cw[ $c ] ?? 0;
            if ( $l > $wmax ) {
                if ( -1 === $sep ) {
                    if ( $i === $j ) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j   = $i;
                $l   = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    /**
     * Convert UTF-8 text to Windows-1252/ISO-8859-1 compatible string for core fonts.
     */
    private static function enc( string $text ): string {
        $text = (string) $text;
        if ( '' === $text ) {
            return '';
        }

        // Preserve line breaks.
        $text = str_replace( array( "\r\n", "\r" ), "\n", $text );

        $converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $text );
        if ( false === $converted ) {
            $converted = @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $text );
        }
        if ( false === $converted ) {
            // Last resort: drop non-ascii.
            $converted = preg_replace( '/[^\x00-\x7F]/', '', $text );
        }
        return (string) $converted;
    }

    /**
     * Return warnings collected during generation (non-fatal).
     *
     * @return string[]
     */
    public function get_warnings(): array {
        return $this->warnings;
    }
}

class AGP_PV_PDF {
    private static string $last_pdf_error = '';

    public static function regenerate_pdf_attachment( int $submission_id ): array {
        $submission = AGP_PV_Email::get_submission( $submission_id );
        if ( ! $submission ) {
            return array(
                'ok' => false,
                'message' => __( 'No se encontró el informe.', 'agrocampo-post-venta' ),
            );
        }

        if ( ! empty( $submission['pdf_attachment_id'] ) ) {
            wp_delete_attachment( (int) $submission['pdf_attachment_id'], true );
            $submission['pdf_attachment_id'] = 0;
        }

        $result = self::ensure_pdf_attachment( $submission_id, $submission );

        if ( empty( $result['status'] ) || 'ready' !== $result['status'] ) {
            return array(
                'ok' => false,
                'message' => $result['message'] ?? __( 'No se pudo regenerar el PDF.', 'agrocampo-post-venta' ),
            );
        }

        return array(
            'ok' => true,
            'message' => '',
            'attachment_id' => (int) ( $result['attachment_id'] ?? 0 ),
        );
    }

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
        if ( empty( $upload_dir['path'] ) || empty( $upload_dir['url'] ) ) {
            self::update_pdf_status( $submission_id, 'failed', __( 'No se pudo obtener el directorio de uploads.', 'agrocampo-post-venta' ) );
            return array(
                'status' => 'failed',
                'message' => __( 'No se pudo obtener el directorio de uploads.', 'agrocampo-post-venta' ),
                'attachment_id' => 0,
                'path' => '',
            );
        }

        if ( ! wp_mkdir_p( $upload_dir['path'] ) ) {
            self::update_pdf_status( $submission_id, 'failed', __( 'No se pudo crear el directorio de uploads.', 'agrocampo-post-venta' ) );
            return array(
                'status' => 'failed',
                'message' => __( 'No se pudo crear el directorio de uploads.', 'agrocampo-post-venta' ),
                'attachment_id' => 0,
                'path' => '',
            );
        }

        $report_id = AGP_PV_DB::get_visible_report_id( $submission );
        $filename = wp_unique_filename( $upload_dir['path'], 'agp-pv-' . ( $report_id > 0 ? $report_id : $submission_id ) . '.pdf' );
        $path     = trailingslashit( $upload_dir['path'] ) . $filename;

        $generated = self::generate_pdf_file( $submission_id, $submission, $path );
        if ( ! $generated ) {
            $err = self::$last_pdf_error ? self::$last_pdf_error : __( 'No se pudo generar el PDF.', 'agrocampo-post-venta' );
            self::update_pdf_status( $submission_id, 'failed', $err );
            return array(
                'status' => 'failed',
                'message' => $err,
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
            'post_title'      => sanitize_file_name( $filename ),
            'post_content'    => '',
            'post_status'     => 'inherit',
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
            'status'        => 'ready',
            'message'       => '',
            'attachment_id' => (int) $attach_id,
            'path'          => $path,
        );
    }

    private static function generate_pdf_file( int $submission_id, array $submission, string $path ): bool {
        self::$last_pdf_error = '';
        try {
            $logo_config = self::get_logo_config();
            $issue_date  = date_i18n( 'd-m-Y' );

            $document = new AGP_PV_PDF_Document();
            $report_id = AGP_PV_DB::get_visible_report_id( $submission );
            $document->configure( $report_id > 0 ? $report_id : $submission_id, $issue_date, $logo_config['path'], (float) $logo_config['width_mm'] );

            // Map labels (avoid one/two).
            $tecnico_label = self::normalize_pdf_value( $submission['tecnico_label'] ?? ( $submission['tecnico'] ?? '' ) );
            $tipo_servicio_label = self::normalize_pdf_value( $submission['tipo_servicio_label'] ?? self::map_tipo_servicio( (string) ( $submission['tipo_servicio'] ?? '' ) ) );
            $tipo_mantencion_label = self::normalize_pdf_value( $submission['tipo_mantencion_label'] ?? self::map_tipo_mantencion( (string) ( $submission['tipo_mantencion'] ?? '' ) ) );

            $document->card_start( __( 'Datos Generales', 'agrocampo-post-venta' ), 16.0 );
            $document->row4( __( 'Técnico', 'agrocampo-post-venta' ), $tecnico_label, __( 'Correo', 'agrocampo-post-venta' ), self::normalize_pdf_value( $submission['email_cliente'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ) );
            $document->row2( __( 'Cliente', 'agrocampo-post-venta' ), self::normalize_pdf_value( $submission['cliente'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ) );
            $document->row2( __( 'Faena / Lugar', 'agrocampo-post-venta' ), self::normalize_pdf_value( $submission['faena_lugar'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ) );
            $document->card_end();

            $document->card_start( __( 'Equipo', 'agrocampo-post-venta' ), 16.0 );
            $document->row4( __( 'Máquina', 'agrocampo-post-venta' ), self::normalize_pdf_value( $submission['maquina'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ), __( 'Modelo', 'agrocampo-post-venta' ), self::normalize_pdf_value( $submission['modelo'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ) );
            $document->row4( __( 'Serie', 'agrocampo-post-venta' ), self::normalize_pdf_value( $submission['serie'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ), __( 'N° Interno', 'agrocampo-post-venta' ), self::normalize_pdf_value( $submission['numero_interno'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ) );
            $document->row4( __( 'Fecha', 'agrocampo-post-venta' ), self::normalize_pdf_value( $submission['fecha'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ), __( 'Horas', 'agrocampo-post-venta' ), self::normalize_pdf_value( $submission['horas'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ) );
            $document->card_end();

            $document->card_start( __( 'Servicio', 'agrocampo-post-venta' ), 16.0 );
            $document->row2( __( 'Tipo de Servicio', 'agrocampo-post-venta' ), (string) $tipo_servicio_label );

            // Mantención.
            if ( ! empty( $tipo_mantencion_label ) && ! empty( $submission['cantidad_horas'] ) ) {
                $document->row4( __( 'Mantención', 'agrocampo-post-venta' ), $tipo_mantencion_label, __( 'Cant. Horas', 'agrocampo-post-venta' ), self::normalize_pdf_value( $submission['cantidad_horas'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ) );
            } elseif ( ! empty( $tipo_mantencion_label ) ) {
                $document->row2( __( 'Tipo de Mantención', 'agrocampo-post-venta' ), $tipo_mantencion_label );
            } elseif ( ! empty( $submission['cantidad_horas'] ) ) {
                $document->row2( __( 'Cantidad de Horas', 'agrocampo-post-venta' ), self::normalize_pdf_value( $submission['cantidad_horas'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ) );
            }

            // Garantía.
            if ( ! empty( $submission['fecha_reparacion'] ) || ! empty( $submission['fecha_cierre'] ) ) {
                $document->row4(
                    __( 'F. Reparación', 'agrocampo-post-venta' ),
                    self::normalize_pdf_value( $submission['fecha_reparacion'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ),
                    __( 'F. Cierre', 'agrocampo-post-venta' ),
                    self::normalize_pdf_value( $submission['fecha_cierre'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) )
                );
            }
            $document->card_end();

            $document->card_start( __( 'Detalle', 'agrocampo-post-venta' ), 20.0 );

            $lub = self::limit_pdf_block_text( self::normalize_pdf_value( $submission['lubricantes'] ?? '', '', true ) );
            $fil = self::limit_pdf_block_text( self::normalize_pdf_value( $submission['filtros_utilizados'] ?? '', '', true ) );
            $com = self::limit_pdf_block_text( self::normalize_pdf_value( $submission['componentes_utilizados'] ?? '', '', true ) );

            if ( '' !== trim( $lub ) ) {
                $rendered_two_cols = false;
                if ( '' !== trim( $fil ) ) {
                    $rendered_two_cols = $document->detail_lists_two_columns(
                        __( 'Lubricantes', 'agrocampo-post-venta' ),
                        $lub,
                        __( 'Filtros Utilizados', 'agrocampo-post-venta' ),
                        $fil
                    );
                }

                if ( ! $rendered_two_cols ) {
                    $document->adaptive_text( __( 'Lubricantes', 'agrocampo-post-venta' ), $lub );
                    if ( '' !== trim( $fil ) ) {
                        $document->adaptive_text( __( 'Filtros Utilizados', 'agrocampo-post-venta' ), $fil );
                    }
                }
            } elseif ( '' !== trim( $fil ) ) {
                $document->adaptive_text( __( 'Filtros Utilizados', 'agrocampo-post-venta' ), $fil );
            }

            if ( '' !== trim( $com ) ) {
                $document->adaptive_text( __( 'Componentes Utilizados', 'agrocampo-post-venta' ), $com );
            }

            $trabajos = self::limit_pdf_block_text( self::normalize_pdf_value( $submission['trabajos_realizados'] ?? '', __( 'No informado', 'agrocampo-post-venta' ), true ) );
            $observaciones = self::limit_pdf_block_text( self::normalize_pdf_value( $submission['observaciones'] ?? '', __( 'No informado', 'agrocampo-post-venta' ), true ) );

            $document->adaptive_text( __( 'Trabajos Realizados', 'agrocampo-post-venta' ), $trabajos );

            // If Observaciones is short, force compact signatures mode so both
            // sections are more likely to fit on the same page before breaking.
            $obs_trimmed = trim( $observaciones );
            $obs_short = strlen( $obs_trimmed ) <= 180 && false === strpos( $obs_trimmed, "\n" );
            $space_left = $document->get_space_left();
            if ( $obs_short && $space_left < 58.0 ) {
                $document->set_signature_compact( true );
            }

            $document->adaptive_text( __( 'Observaciones', 'agrocampo-post-venta' ), $observaciones );
            $document->card_end();

            $document->card_start( __( 'Firmas', 'agrocampo-post-venta' ), 28.0 );
            $document->signature_row(
                array(
                    'label' => __( 'Firma Cliente', 'agrocampo-post-venta' ),
                    'path'  => self::resolve_attachment_path( (int) ( $submission['firma_cliente_id'] ?? 0 ) ),
                ),
                array(
                    'label' => __( 'Firma Técnico', 'agrocampo-post-venta' ),
                    'path'  => self::resolve_attachment_path( (int) ( $submission['firma_tecnico_id'] ?? 0 ) ),
                )
            );
            $document->card_end();

            $document->Output( 'F', $path );

            // Log non-fatal warnings.
            foreach ( $document->get_warnings() as $warning ) {
                self::log( $warning );
            }

            return file_exists( $path );
        } catch ( Exception $e ) {
            self::$last_pdf_error = $e->getMessage();
            self::log( 'PDF generation failed: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Prepare image path to be compatible with FPDF.
     *
     * - FPDF does not support PNG alpha channel. If GD is available, convert PNG to JPG with a white background.
     * - If GD is not available, return original path and let the PDF generator decide (it may omit the image).
     *
     * @return array{path:string,warning:string}
     */
    public static function prepare_image_for_fpdf( string $path, string $kind = 'image' ): array {
        $result = array(
            'path'    => $path,
            'warning' => '',
        );

        if ( ! $path || ! file_exists( $path ) ) {
            return $result;
        }

        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        if ( 'png' !== $ext ) {
            return $result;
        }

        // If GD is available, convert PNG to JPG (removes alpha).
        if ( function_exists( 'imagecreatefrompng' ) && function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagejpeg' ) ) {
            $img = @imagecreatefrompng( $path );
            if ( ! $img ) {
                $result['warning'] = sprintf( 'No se pudo leer PNG para %s.', $kind );
                return $result;
            }

            $w = imagesx( $img );
            $h = imagesy( $img );
            if ( ! $w || ! $h ) {
                imagedestroy( $img );
                $result['warning'] = sprintf( 'PNG inválido para %s.', $kind );
                return $result;
            }

            $bg = imagecreatetruecolor( $w, $h );
            $white = imagecolorallocate( $bg, 255, 255, 255 );
            imagefilledrectangle( $bg, 0, 0, $w, $h, $white );

            imagealphablending( $bg, true );
            imagesavealpha( $bg, false );
            imagecopy( $bg, $img, 0, 0, 0, 0, $w, $h );

            $upload_dir = wp_upload_dir();
            $cache_dir  = trailingslashit( $upload_dir['basedir'] ) . 'agp-pv-cache';
            if ( ! wp_mkdir_p( $cache_dir ) ) {
                imagedestroy( $img );
                imagedestroy( $bg );
                $result['warning'] = sprintf( 'No se pudo crear cache de imágenes para %s.', $kind );
                return $result;
            }

            $hash = md5( $path . ':' . (string) filemtime( $path ) );
            $jpg  = trailingslashit( $cache_dir ) . $kind . '-' . $hash . '.jpg';

            $ok = @imagejpeg( $bg, $jpg, 90 );

            imagedestroy( $img );
            imagedestroy( $bg );

            if ( $ok && file_exists( $jpg ) ) {
                $result['path'] = $jpg;
                $result['warning'] = sprintf( '%s PNG convertido a JPG para compatibilidad FPDF.', ucfirst( $kind ) );
                return $result;
            }

            $result['warning'] = sprintf( 'No se pudo convertir PNG a JPG para %s.', $kind );
            return $result;
        }

        $result['warning'] = sprintf( '%s PNG puede fallar (sin GD para convertir).', ucfirst( $kind ) );
        return $result;
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

        // Optional deeper checks (qpdf/PyPDF2) – keep existing behavior if proc_open exists.
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
        $logo_id  = absint( get_option( 'agp_pv_logo_attachment_id', 0 ) );
        $width_mm = (float) get_option( 'agp_pv_logo_width_mm', 38 );
        $path     = '';

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
                $relative  = substr( $url, strlen( $uploads['baseurl'] ) );
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
            'two' => 'Garantía',
            'Interno' => 'Mantención',
            'Visita-de-Cortesía' => 'Visita de Cortesía',
            'Diagnostico-Técnico' => 'Diagnóstico Técnico',
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

    /**
     * Normaliza valores para salida en PDF.
     *
     * - Limpia placeholders tipo NULL/null.
     * - Retorna fallback cuando no hay dato útil.
     */
    private static function normalize_pdf_value( $value, string $fallback = '', bool $preserve_line_breaks = false ): string {
        if ( is_string( $value ) ) {
            $legacy_address = self::extract_legacy_street_address( $value );
            if ( '' !== $legacy_address ) {
                $value = $legacy_address;
            }
        }

        if ( is_array( $value ) ) {
            if ( isset( $value['street_address'] ) ) {
                $value = $value['street_address'];
            } else {
                $flattened = self::flatten_pdf_values( $value );
                $value = implode( ' / ', $flattened );
            }
        }

        if ( is_object( $value ) ) {
            if ( method_exists( $value, '__toString' ) ) {
                $value = (string) $value;
            } else {
                $value = '';
            }
        }

        if ( $preserve_line_breaks ) {
            $text = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
            $lines = explode( "\n", $text );
            $clean_lines = array();

            foreach ( $lines as $line ) {
                $line = preg_replace( '/[ \t]+/u', ' ', trim( (string) $line ) );
                if ( is_string( $line ) && '' !== $line ) {
                    $clean_lines[] = $line;
                }
            }

            $normalized = trim( implode( "\n", $clean_lines ) );
        } else {
            $normalized = trim( (string) $value );
            $normalized = preg_replace( '/\s+/u', ' ', $normalized );
            $normalized = is_string( $normalized ) ? trim( $normalized ) : '';
        }

        if ( '' === $normalized ) {
            return $fallback;
        }

        $lower = strtolower( preg_replace( '/\s+/u', ' ', $normalized ) );
        if ( in_array( $lower, array( 'null', '(null)', 'n/a', 'na', 'none', '-' ), true ) ) {
            return $fallback;
        }

        return $normalized;
    }
    /**
     * Normaliza bloques de texto para PDF sin truncar contenido.
     */
    private static function limit_pdf_block_text( string $text ): string {
        $text = trim( str_replace( array( "\r\n", "\r" ), "\n", $text ) );
        if ( '' === $text ) {
            return '';
        }

        $lines = explode( "\n", $text );
        $clean_lines = array();

        foreach ( $lines as $line ) {
            $line = trim( preg_replace( '/[ 	]+/u', ' ', (string) $line ) );
            if ( '' === $line ) {
                continue;
            }

            $clean_lines[] = $line;
        }

        return trim( implode( "\n", $clean_lines ) );
    }

    private static function text_length( string $text ): int {
        if ( function_exists( 'mb_strlen' ) ) {
            return (int) mb_strlen( $text );
        }

        return strlen( $text );
    }

    private static function text_substr( string $text, int $start, int $length ): string {
        if ( function_exists( 'mb_substr' ) ) {
            return (string) mb_substr( $text, $start, $length );
        }

        return (string) substr( $text, $start, $length );
    }

    private static function trim_to_last_word( string $text ): string {
        $text = trim( $text );
        if ( '' === $text ) {
            return '';
        }

        if ( preg_match( '/^(.*)\s+[^\s]+$/u', $text, $matches ) ) {
            return trim( (string) $matches[1] );
        }

        return $text;
    }

    /**
     * Extrae dirección desde payload legacy serializado sin deserializar objetos.
     */
    private static function extract_legacy_street_address( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }

        if ( ! preg_match( '/^a:\d+:\{.*\}$/s', $value ) ) {
            return '';
        }

        if ( ! preg_match( '/s:\d+:"street_address";s:\d+:"([^"]*)";/u', $value, $matches ) ) {
            return '';
        }

        $street = trim( wp_strip_all_tags( (string) $matches[1] ) );
        return $street;
    }

    /**
     * @param mixed[] $values
     * @return string[]
     */
    private static function flatten_pdf_values( array $values ): array {
        $flat = array();

        foreach ( $values as $value ) {
            if ( is_array( $value ) ) {
                $flat = array_merge( $flat, self::flatten_pdf_values( $value ) );
                continue;
            }

            $normalized = self::normalize_pdf_value( $value, '' );
            if ( '' !== $normalized ) {
                $flat[] = $normalized;
            }
        }

        return array_values( array_unique( $flat ) );
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
