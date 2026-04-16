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
    private string $header_title = '';
    private string $footer_text = '';
    private string $it_label_template = '';

    /** @var array{placeholder_gray:int,min_body_font:float,min_title_font:float,min_line_height:float} */
    private array $readability = array(
        'placeholder_gray' => 95,
        'min_body_font' => 10.0,
        'min_title_font' => 10.8,
        'min_line_height' => 4.4,
    );

    private bool $compact_density = false;
    private bool $force_signature_compact = false;

    /** @var string[] */
    private array $warnings = array();

    public function configure( int $report_id, string $issue_date, string $logo_path, float $logo_width_mm ): void {
        $this->report_id      = $report_id;
        $this->issue_date     = $issue_date;
        $this->logo_path      = $logo_path;
        $this->logo_width_mm  = $logo_width_mm > 0 ? $logo_width_mm : 38.0;

        $branding = AGP_PV_PDF::get_branding_config();
        $this->header_title = (string) $branding['header_title'];
        $this->footer_text = (string) $branding['footer_text'];
        $this->it_label_template = (string) $branding['it_label_template'];

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

        // Title (center) with subtitle for better visual hierarchy.
        $this->SetFont( 'Helvetica', 'B', 16 );
        $title_text = '' !== $this->header_title ? $this->header_title : __( 'INFORME TÉCNICO', 'agrocampo-post-venta' );
        $title      = self::enc( $title_text );
        $title_w    = $this->GetStringWidth( $title );
        $center_x   = ( $this->w - $title_w ) / 2;
        $title_y    = $start_y + 1.8;
        $this->SetXY( $center_x, $title_y );
        $this->Cell( $title_w, 7.0, $title, 0, 0, 'C' );
        $this->SetFont( 'Helvetica', '', 9 );
        $this->SetXY( $center_x, $title_y + 6.0 );
        $this->Cell( $title_w, 3.8, self::enc( __( 'Reporte de Post Venta', 'agrocampo-post-venta' ) ), 0, 0, 'C' );

        // Right block (IT + date).
        $this->SetFont( 'Helvetica', 'B', 10 );
        $right_w = 38;
        $right_h = 12.0;
        $right_x = $this->w - $this->rMargin - $right_w;
        $this->RoundedRect( $right_x, $start_y, $right_w, $right_h, 2.5, 'D' );
        $this->SetXY( $right_x + 2.2, $start_y + 1.8 );
        $it_template = '' !== $this->it_label_template ? $this->it_label_template : __( 'IT: %d', 'agrocampo-post-venta' );
        if ( false === strpos( $it_template, '%' ) ) {
            $it_template .= ' %d';
        }
        $this->Cell( $right_w - 4.4, 3.8, self::enc( sprintf( $it_template, $this->report_id ) ), 0, 2, 'L' );
        $this->SetFont( 'Helvetica', '', 8 );
        $this->SetX( $right_x + 2.2 );
        $this->Cell( $right_w - 4.4, 3.6, self::enc( $this->issue_date ), 0, 0, 'L' );

        // Separator line.
        $header_h = max( $logo_h, 18.6 );
        $line_y   = $start_y + $header_h + 4;
        $this->Line( $this->lMargin, $line_y, $this->w - $this->rMargin, $line_y );
        $this->SetY( $line_y + 5.0 );
    }

    public function Footer(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $this->SetY( -14 );
        $this->Line( $this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY() );
        $this->SetY( -11.5 );
        $this->SetFont( 'Helvetica', '', 8.9 );
        $footer_source = '' !== $this->footer_text ? $this->footer_text : __( 'Talca • Linares • Parral   |   +56 9 9748 5650', 'agrocampo-post-venta' );
        $footer_text = self::enc( $footer_source );
        $page_text   = self::enc( sprintf( __( 'Página %d', 'agrocampo-post-venta' ), $this->PageNo() ) );
        $this->SetX( $this->lMargin );
        $this->Cell( 0, 4.8, $footer_text, 0, 0, 'L' );
        $this->SetX( $this->lMargin );
        $this->Cell( $this->w - $this->lMargin - $this->rMargin, 4.8, $page_text, 0, 0, 'R' );
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
        $header_h = $this->compact_density ? 10.2 : 11.5;
        $this->ensure_space( $header_h + max( 8.0, $min_content_height ) );

        $this->card_x = $this->lMargin;
        $this->card_y = $this->GetY();
        $this->card_w = $this->w - $this->lMargin - $this->rMargin;
        $this->card_open = true;

        $title_top = $this->compact_density ? 2.7 : 3.4;
        $title_h   = $this->compact_density ? 4.8 : 5.4;
        $line_gap  = $this->compact_density ? 0.45 : 0.6;
        $body_gap  = $this->compact_density ? 1.5 : 2.4;

        $this->SetXY( $this->card_x + 3, $this->card_y + $title_top );
        $this->SetFont( 'Helvetica', 'B', max( $this->compact_density ? 11.2 : 12, $this->readability['min_title_font'] ) );
        $this->Cell( $this->card_w - 6, $title_h, self::enc( $title ), 0, 1, 'L' );

        $line_y = $this->GetY() + $line_gap;
        $this->Line( $this->card_x + 2.8, $line_y, $this->card_x + $this->card_w - 2.8, $line_y );
        $this->SetXY( $this->card_x + 3, $line_y + $body_gap );
    }

    public function card_end( float $bottom_padding = 2.2 ): void {
        if ( ! $this->card_open ) {
            return;
        }

        $effective_bottom = $this->compact_density ? max( 1.3, $bottom_padding - 0.7 ) : $bottom_padding;
        $after_gap = $this->compact_density ? 1.1 : 2.2;

        $end_y = $this->GetY() + $effective_bottom;
        $this->RoundedRect( $this->card_x, $this->card_y, $this->card_w, $end_y - $this->card_y, 3.2, 'D' );
        $this->SetXY( $this->lMargin, $end_y + $after_gap );
        $this->card_open = false;
    }

    /**
     * Two-column row (label/value), with wrapping.
     */
    public function row2( string $label, string $value ): void {
        $label_w = $this->label_width_mm;
        $gap     = $this->gap_mm;
        $value_w = $this->w - $this->lMargin - $this->rMargin - $label_w - $gap;

        $line_h = max( $this->compact_density ? 4.4 : 5.3, $this->readability['min_line_height'] );

        $label = self::enc( $label );
        $value = self::enc( $value );

        $nb     = max( 1, $this->NbLines( $value_w, $value ) );
        $row_h  = $line_h * $nb;

        $this->ensure_space( $row_h + ( $this->compact_density ? 0.9 : 1.5 ) );

        $x = $this->GetX();
        $y = $this->GetY();

        // Label.
        $this->SetFont( 'Helvetica', 'B', max( $this->compact_density ? 9.8 : 10.2, $this->readability['min_body_font'] ) );
        $this->Cell( $label_w, $row_h, $label, 0, 0, 'L' );

        // Value.
        $value_is_placeholder = AGP_PV_PDF::is_missing_display_value( $value );
        if ( $value_is_placeholder ) {
            $this->SetTextColor( $this->readability['placeholder_gray'], $this->readability['placeholder_gray'], $this->readability['placeholder_gray'] );
        }
        $this->SetFont( 'Helvetica', '', max( $this->compact_density ? 9.9 : 10.2, $this->readability['min_body_font'] ) );
        $this->SetXY( $x + $label_w + $gap, $y );
        $this->MultiCell( $value_w, $line_h, $value, 0, 'L' );
        if ( $value_is_placeholder ) {
            $this->SetTextColor( 20, 20, 20 );
        }

        $this->SetXY( $x, $y + $row_h + ( $this->compact_density ? 0.9 : 1.4 ) );
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

        $line_h = max( $this->compact_density ? 4.2 : 5.0, $this->readability['min_line_height'] );

        $label1 = self::enc( $label1 );
        $value1 = self::enc( $value1 );
        $label2 = self::enc( $label2 );
        $value2 = self::enc( $value2 );

        $nb1   = max( 1, $this->NbLines( $value_w, $value1 ) );
        $nb2   = max( 1, $this->NbLines( $value_w, $value2 ) );
        $row_h = $line_h * max( $nb1, $nb2 );

        $this->ensure_space( $row_h + ( $this->compact_density ? 0.8 : 1.2 ) );

        $x0 = $this->lMargin;
        $y0 = $this->GetY();

        // Left pair.
        $this->SetFont( 'Helvetica', 'B', max( $this->compact_density ? 9.8 : 10.2, $this->readability['min_body_font'] ) );
        $this->SetXY( $x0, $y0 );
        $this->Cell( $label_w, $row_h, $label1, 0, 0, 'L' );

        $value1_is_placeholder = AGP_PV_PDF::is_missing_display_value( $value1 );
        if ( $value1_is_placeholder ) {
            $this->SetTextColor( $this->readability['placeholder_gray'], $this->readability['placeholder_gray'], $this->readability['placeholder_gray'] );
        }
        $this->SetFont( 'Helvetica', '', max( $this->compact_density ? 9.9 : 10.2, $this->readability['min_body_font'] ) );
        $this->SetXY( $x0 + $label_w + $gap, $y0 );
        $this->MultiCell( $value_w, $line_h, $value1, 0, 'L' );
        if ( $value1_is_placeholder ) {
            $this->SetTextColor( 20, 20, 20 );
        }

        // Right pair.
        $x1 = $x0 + $pair_w + $pair_gap;
        $this->SetFont( 'Helvetica', 'B', max( $this->compact_density ? 9.8 : 10.2, $this->readability['min_body_font'] ) );
        $this->SetXY( $x1, $y0 );
        $this->Cell( $label_w, $row_h, $label2, 0, 0, 'L' );

        $value2_is_placeholder = AGP_PV_PDF::is_missing_display_value( $value2 );
        if ( $value2_is_placeholder ) {
            $this->SetTextColor( $this->readability['placeholder_gray'], $this->readability['placeholder_gray'], $this->readability['placeholder_gray'] );
        }
        $this->SetFont( 'Helvetica', '', max( $this->compact_density ? 9.9 : 10.2, $this->readability['min_body_font'] ) );
        $this->SetXY( $x1 + $label_w + $gap, $y0 );
        $this->MultiCell( $value_w, $line_h, $value2, 0, 'L' );
        if ( $value2_is_placeholder ) {
            $this->SetTextColor( 20, 20, 20 );
        }

        $this->SetXY( $x0, $y0 + $row_h + ( $this->compact_density ? 0.8 : 1.2 ) );
    }

    /**
     * Render Datos Generales + Equipo side-by-side when content is short.
     * Returns true when rendered in 2-up mode; false to allow stacked fallback.
     */
    public function render_intro_cards_two_up( array $general_rows, array $equipment_rows ): bool {
        $usable_w = $this->w - $this->lMargin - $this->rMargin;
        $gap = 4.8;
        $card_w = ( $usable_w - $gap ) / 2;

        $label_w = 26.0;
        $value_gap = 2.0;
        $value_w = $card_w - 6.0 - $label_w - $value_gap;

        if ( $value_w <= 24.0 ) {
            return false;
        }

        $title_top = 2.6;
        $title_h = 4.8;
        $line_gap = 0.6;
        $body_gap = 1.7;
        $line_h = 4.2;
        $row_gap = 1.0;
        $bottom_pad = 1.9;
        $after_gap = 2.0;

        $general_h = $this->estimate_two_up_card_height( $general_rows, $value_w, $line_h, $row_gap, $title_top, $title_h, $line_gap, $body_gap, $bottom_pad );
        $equipment_h = $this->estimate_two_up_card_height( $equipment_rows, $value_w, $line_h, $row_gap, $title_top, $title_h, $line_gap, $body_gap, $bottom_pad );
        $max_h = max( $general_h, $equipment_h );

        if ( $max_h > 56.0 ) {
            return false;
        }

        $this->ensure_space( $max_h + $after_gap );

        $y0 = $this->GetY();
        $x_left = $this->lMargin;
        $x_right = $x_left + $card_w + $gap;

        $this->draw_two_up_card( $x_left, $y0, $card_w, __( 'Datos Generales', 'agrocampo-post-venta' ), $general_rows, $label_w, $value_gap, $value_w, $title_top, $title_h, $line_gap, $body_gap, $line_h, $row_gap, $max_h );
        $this->draw_two_up_card( $x_right, $y0, $card_w, __( 'Equipo', 'agrocampo-post-venta' ), $equipment_rows, $label_w, $value_gap, $value_w, $title_top, $title_h, $line_gap, $body_gap, $line_h, $row_gap, $max_h );

        $this->SetXY( $this->lMargin, $y0 + $max_h + $after_gap );
        return true;
    }

    private function estimate_two_up_card_height(
        array $rows,
        float $value_w,
        float $line_h,
        float $row_gap,
        float $title_top,
        float $title_h,
        float $line_gap,
        float $body_gap,
        float $bottom_pad
    ): float {
        $height = $title_top + $title_h + $line_gap + $body_gap;

        foreach ( $rows as $row ) {
            $value = self::enc( (string) ( $row['value'] ?? '' ) );
            $nb = max( 1, $this->NbLines( $value_w, $value ) );
            if ( $nb > 2 ) {
                return 999.0;
            }

            $height += ( $line_h * $nb ) + $row_gap;
        }

        return $height + $bottom_pad;
    }

    private function draw_two_up_card(
        float $x,
        float $y,
        float $w,
        string $title,
        array $rows,
        float $label_w,
        float $value_gap,
        float $value_w,
        float $title_top,
        float $title_h,
        float $line_gap,
        float $body_gap,
        float $line_h,
        float $row_gap,
        float $card_h
    ): void {
        $this->SetFont( 'Helvetica', 'B', 11.4 );
        $this->SetXY( $x + 2.6, $y + $title_top );
        $this->Cell( $w - 5.2, $title_h, self::enc( $title ), 0, 0, 'L' );

        $line_y = $y + $title_top + $title_h + $line_gap;
        $this->Line( $x + 2.4, $line_y, $x + $w - 2.4, $line_y );

        $cursor_y = $line_y + $body_gap;
        foreach ( $rows as $row ) {
            $label = self::enc( (string) ( $row['label'] ?? '' ) );
            $value = self::enc( (string) ( $row['value'] ?? '' ) );
            $nb = max( 1, $this->NbLines( $value_w, $value ) );
            $row_h = $line_h * $nb;

            $this->SetFont( 'Helvetica', 'B', 9.7 );
            $this->SetXY( $x + 2.6, $cursor_y );
            $this->Cell( $label_w, $row_h, $label, 0, 0, 'L' );

            $value_is_placeholder = AGP_PV_PDF::is_missing_display_value( $value );
            if ( $value_is_placeholder ) {
                $this->SetTextColor( 115, 115, 115 );
            }
            $this->SetFont( 'Helvetica', '', 9.6 );
            $this->SetXY( $x + 2.6 + $label_w + $value_gap, $cursor_y );
            $this->MultiCell( $value_w, $line_h, $value, 0, 'L' );
            if ( $value_is_placeholder ) {
                $this->SetTextColor( 20, 20, 20 );
            }

            $cursor_y += $row_h + $row_gap;
        }

        $this->RoundedRect( $x, $y, $w, $card_h, 2.8, 'D' );
    }

    /**
     * Full-width bordered box with text.
     */
    public function box_text( string $title, string $text ): void {
        $title = self::enc( $title );
        $text  = self::enc( $text );

        $this->ensure_space( 14 );

        $title_h = $this->compact_density ? 5.0 : 6.3;
        $this->SetFont( 'Helvetica', 'B', $this->compact_density ? 9.9 : 10.4 );
        $this->Cell( 0, $title_h, $title, 0, 1, 'L' );
        $this->SetFont( 'Helvetica', '', $this->compact_density ? 9.7 : 10.1 );

        $box_w     = $this->w - $this->lMargin - $this->rMargin;
        $line_h    = $this->compact_density ? 4.1 : 4.8;
        $padding   = $this->compact_density ? 1.3 : 2.1;
        $inner_w   = $box_w - ( 2 * $padding );
        $nb        = max( 1, $this->NbLines( $inner_w, $text ) );
        $text_h    = $nb * $line_h;
        $box_h     = $text_h + ( 2 * $padding );

        $this->ensure_space( $box_h + ( $this->compact_density ? 1.2 : 2.2 ) );

        $x = $this->lMargin;
        $y = $this->GetY();

        $this->Rect( $x, $y, $box_w, $box_h );
        $this->SetXY( $x + $padding, $y + $padding );
        $this->MultiCell( $inner_w, $line_h, $text, 0, 'L' );

        $this->SetXY( $this->lMargin, $y + $box_h + ( $this->compact_density ? 1.3 : 3.2 ) );
    }
    /**
     * Render text as compact row or boxed block depending on length/content.
     */
    public function adaptive_text( string $title, string $text ): void {
        $trimmed = trim( $text );
        if ( '' === $trimmed ) {
            return;
        }

        $is_placeholder = AGP_PV_PDF::is_missing_display_value( $trimmed );
        $is_short_single_line = strlen( $trimmed ) <= 90 && false === strpos( $trimmed, "\n" );

        if ( $is_placeholder || $is_short_single_line ) {
            $this->row2( $title, $trimmed );
            return;
        }

        $this->box_text( $title, $trimmed );
    }

    public function lubricants_columns_text( string $title, string $text ): void {
        $trimmed = trim( $text );
        if ( '' === $trimmed ) {
            return;
        }

        $lines = array();
        foreach ( explode( "\n", $trimmed ) as $line ) {
            $line = trim( (string) $line );
            if ( '' === $line ) {
                continue;
            }
            $lines[] = $line;
        }

        if ( count( $lines ) <= 4 ) {
            $this->box_text( $title, implode( "\n", $lines ) );
            return;
        }

        $usable_w = $this->w - $this->lMargin - $this->rMargin;
        $gap      = 6.0;
        $col_w    = ( $usable_w - $gap ) / 2;
        $title_h  = $this->compact_density ? 5.0 : 6.3;
        $padding  = $this->compact_density ? 1.3 : 1.9;
        $line_h   = $this->compact_density ? 3.9 : 4.4;
        $line_gap = $this->compact_density ? 0.3 : 0.5;
        $inner_w  = $col_w - ( 2 * $padding );

        $left_lines  = array_slice( $lines, 0, (int) ceil( count( $lines ) / 2 ) );
        $right_lines = array_slice( $lines, count( $left_lines ) );

        $left_text_h = 0.0;
        foreach ( $left_lines as $left_line ) {
            $encoded = self::enc( $left_line );
            $left_text_h += max( 1, $this->NbLines( $inner_w, $encoded ) ) * $line_h;
            $left_text_h += $line_gap;
        }
        if ( $left_text_h > 0 ) {
            $left_text_h -= $line_gap;
        }

        $right_text_h = 0.0;
        foreach ( $right_lines as $right_line ) {
            $encoded = self::enc( $right_line );
            $right_text_h += max( 1, $this->NbLines( $inner_w, $encoded ) ) * $line_h;
            $right_text_h += $line_gap;
        }
        if ( $right_text_h > 0 ) {
            $right_text_h -= $line_gap;
        }

        $box_h = max( $line_h, $left_text_h, $right_text_h ) + ( 2 * $padding );
        $row_h = $title_h + $box_h + ( $this->compact_density ? 1.2 : 2.0 );
        $this->ensure_space( $row_h + ( $this->compact_density ? 1.0 : 1.5 ) );

        $x = $this->lMargin;
        $y = $this->GetY();

        $this->SetFont( 'Helvetica', 'B', $this->compact_density ? 9.9 : 10.4 );
        $this->SetXY( $x, $y );
        $this->Cell( $usable_w, $title_h, self::enc( $title ), 0, 1, 'L' );

        $left_x  = $x;
        $right_x = $x + $col_w + $gap;
        $box_y   = $y + $title_h;

        $this->Rect( $left_x, $box_y, $col_w, $box_h );
        $this->Rect( $right_x, $box_y, $col_w, $box_h );

        $this->SetFont( 'Helvetica', '', $this->compact_density ? 9.7 : 10.1 );

        $cursor_y = $box_y + $padding;
        foreach ( $left_lines as $left_line ) {
            $encoded = self::enc( $left_line );
            $this->SetXY( $left_x + $padding, $cursor_y );
            $this->MultiCell( $inner_w, $line_h, $encoded, 0, 'L' );
            $cursor_y = $this->GetY() + $line_gap;
        }

        $cursor_y = $box_y + $padding;
        foreach ( $right_lines as $right_line ) {
            $encoded = self::enc( $right_line );
            $this->SetXY( $right_x + $padding, $cursor_y );
            $this->MultiCell( $inner_w, $line_h, $encoded, 0, 'L' );
            $cursor_y = $this->GetY() + $line_gap;
        }

        $this->SetXY( $this->lMargin, $y + $row_h );
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

    public function set_compact_density( bool $compact ): void {
        $this->compact_density = $compact;
    }

    public function is_compact_density(): bool {
        return $this->compact_density;
    }

    public function signature_row( array $left, array $right ): void {
        $gap       = 6.5;
        $box_h     = 27.0;
        $tail_gap  = 5.2;
        $box_w     = ( $this->w - $this->lMargin - $this->rMargin - $gap ) / 2;
        $left_has_image = ! empty( $left['path'] ) && file_exists( (string) $left['path'] );
        $right_has_image = ! empty( $right['path'] ) && file_exists( (string) $right['path'] );
        $no_images = ! $left_has_image && ! $right_has_image;

        $space_left = $this->space_left();
        if ( $this->force_signature_compact || ( $space_left < 36.0 && $space_left >= 30.0 ) ) {
            // Compact mode to avoid pushing "Firmas" alone to a new page.
            $box_h    = 22.0;
            $tail_gap = 3.8;
        }

        if ( $this->compact_density ) {
            $box_h = min( $box_h, 19.0 );
            $tail_gap = min( $tail_gap, 2.8 );
        }

        if ( $no_images ) {
            $box_h = min( $box_h, $this->compact_density ? 12.5 : 15.0 );
            $tail_gap = min( $tail_gap, $this->compact_density ? 1.6 : 2.2 );
        }
        $tail_gap = max( $tail_gap, 5.2 );

        $this->ensure_space( $box_h + 10.0 );

        $y = $this->GetY();
        $x = $this->lMargin;

        $this->signature_box( (string) $left['label'], (string) $left['path'], (string) ( $left['name'] ?? '' ), $x, $y, $box_w, $box_h );
        $this->signature_box( (string) $right['label'], (string) $right['path'], (string) ( $right['name'] ?? '' ), $x + $box_w + $gap, $y, $box_w, $box_h );

        $this->SetXY( $this->lMargin, $y + $box_h + $tail_gap );
        $this->force_signature_compact = false;
    }

    public function signature_row_three( array $left, array $center, array $right ): void {
        $gap       = 4.0;
        $box_h     = 27.0;
        $tail_gap  = 5.2;
        $box_w     = ( $this->w - $this->lMargin - $this->rMargin - ( 2 * $gap ) ) / 3;
        $left_has_image = ! empty( $left['path'] ) && file_exists( (string) $left['path'] );
        $center_has_image = ! empty( $center['path'] ) && file_exists( (string) $center['path'] );
        $right_has_image = ! empty( $right['path'] ) && file_exists( (string) $right['path'] );
        $no_images = ! $left_has_image && ! $center_has_image && ! $right_has_image;

        $space_left = $this->space_left();
        if ( $this->force_signature_compact || ( $space_left < 36.0 && $space_left >= 30.0 ) ) {
            $box_h    = 22.0;
            $tail_gap = 3.8;
        }

        if ( $this->compact_density ) {
            $box_h = min( $box_h, 19.0 );
            $tail_gap = min( $tail_gap, 2.8 );
        }

        if ( $no_images ) {
            $box_h = min( $box_h, $this->compact_density ? 12.5 : 15.0 );
            $tail_gap = min( $tail_gap, $this->compact_density ? 1.6 : 2.2 );
        }
        $tail_gap = max( $tail_gap, 5.2 );

        $this->ensure_space( $box_h + 10.0 );

        $y = $this->GetY();
        $x = $this->lMargin;

        $this->signature_box( (string) $left['label'], (string) $left['path'], (string) ( $left['name'] ?? '' ), $x, $y, $box_w, $box_h );
        $this->signature_box( (string) $center['label'], (string) $center['path'], (string) ( $center['name'] ?? '' ), $x + $box_w + $gap, $y, $box_w, $box_h );
        $this->signature_box( (string) $right['label'], (string) $right['path'], (string) ( $right['name'] ?? '' ), $x + ( 2 * ( $box_w + $gap ) ), $y, $box_w, $box_h );

        $this->SetXY( $this->lMargin, $y + $box_h + $tail_gap );
        $this->force_signature_compact = false;
    }

    private function signature_box( string $label, string $path, string $name, float $x, float $y, float $w, float $h ): void {
        $label = self::enc( $label );
        $name  = self::enc( $name );

        $this->SetFont( 'Helvetica', 'B', 10 );
        $this->SetXY( $x, $y );
        $this->Cell( $w, 5.5, $label, 0, 0, 'L' );

        $box_y = $y + 6;
        $this->Rect( $x, $box_y, $w, $h );

        $this->SetFont( 'Helvetica', '', 9.5 );

        $has_rendered_image = false;

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
                        $has_rendered_image = true;
                    } catch ( Exception $e ) {
                        $this->warnings[] = 'Firma omitida: ' . $e->getMessage();
                    }
                }
            } elseif ( ! empty( $prepared['warning'] ) ) {
                $this->warnings[] = $prepared['warning'];
            }
        }

        if ( ! $has_rendered_image ) {
            $this->SetXY( $x, $box_y + ( $h / 2 ) - 2 );
            $this->Cell( $w, 4.2, self::enc( __( 'Firma no disponible', 'agrocampo-post-venta' ) ), 0, 0, 'C' );
        }

        $this->SetFont( 'Helvetica', '', 8.7 );
        $this->SetXY( $x, $box_y + $h + 1.1 );
        $this->Cell( $w, 3.8, $name, 0, 0, 'C' );
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

    public function estimate_adaptive_text_height( $text ): float {
        $trimmed = trim( (string) $text );
        if ( '' === $trimmed ) {
            return 0.0;
        }

        $is_placeholder = AGP_PV_PDF::is_missing_display_value( $trimmed );
        $is_short_single_line = strlen( $trimmed ) <= 90 && false === strpos( $trimmed, "\n" );

        $row_h = $this->estimate_row2_value_height( $trimmed );
        if ( $is_placeholder || $is_short_single_line ) {
            return $row_h;
        }

        $box_h = $this->estimate_box_text_height( $trimmed );
        return max( $row_h, $box_h );
    }

    public function estimate_row2_value_height( $value ): float {
        $value = (string) $value;
        $label_w = $this->label_width_mm;
        $gap     = $this->gap_mm;
        $value_w = $this->w - $this->lMargin - $this->rMargin - $label_w - $gap;
        $line_h  = $this->compact_density ? 4.4 : 5.3;
        $nb      = max( 1, $this->NbLines( $value_w, self::enc( $value ) ) );
        $row_h   = $line_h * $nb;
        $after   = $this->compact_density ? 0.9 : 1.4;
        return $row_h + $after;
    }

    public function estimate_box_text_height( $text ): float {
        $trimmed = trim( (string) $text );
        if ( '' === $trimmed ) {
            return 0.0;
        }

        $box_w = $this->w - $this->lMargin - $this->rMargin;
        $padding = $this->compact_density ? 1.3 : 2.1;
        $inner_w = $box_w - ( 2 * $padding );
        $line_h = $this->compact_density ? 4.1 : 4.8;
        $nb = max( 1, $this->NbLines( $inner_w, self::enc( $trimmed ) ) );
        $box_h = ( $nb * $line_h ) + ( 2 * $padding );
        $title_h = $this->compact_density ? 5.0 : 6.3;
        $after_h = $this->compact_density ? 1.3 : 3.2;
        return $title_h + $box_h + $after_h;
    }

    public function estimate_card_end_height(): float {
        $bottom = $this->compact_density ? 1.4 : 2.2;
        $after  = $this->compact_density ? 0.9 : 1.5;
        return $bottom + $after;
    }

    public function estimate_signature_section_height( bool $force_compact, bool $no_images, int $row_count = 1 ): float {
        $header_h = $this->compact_density ? 10.2 : 11.5;
        $body_top = $this->compact_density ? 1.3 : 2.1;
        $box_h = 27.0;
        $tail_gap = 5.2;

        if ( $force_compact ) {
            $box_h = 22.0;
            $tail_gap = 3.8;
        }
        if ( $this->compact_density ) {
            $box_h = min( $box_h, 19.0 );
            $tail_gap = min( $tail_gap, 2.8 );
        }
        if ( $no_images ) {
            $box_h = min( $box_h, $this->compact_density ? 12.5 : 15.0 );
            $tail_gap = min( $tail_gap, $this->compact_density ? 1.6 : 2.2 );
        }
        $tail_gap = max( $tail_gap, 5.2 );

        // signature_row places label + 6mm top before box.
        $row_h = 6.0 + $box_h + $tail_gap;
        $rows = max( 1, $row_count );
        return $header_h + $body_top + ( $row_h * $rows ) + $this->estimate_card_end_height();
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

    private static function now_ms(): float {
        if ( function_exists( 'hrtime' ) ) {
            return hrtime( true ) / 1000000;
        }

        return microtime( true ) * 1000;
    }

    private static function persist_pdf_metrics( int $submission_id, array $metrics ): void {
        AGP_PV_DB::update_pdf_metrics( $submission_id, $metrics );
    }

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
        $started_ms = self::now_ms();

        if ( ! class_exists( 'FPDF' ) ) {
            self::update_pdf_status( $submission_id, 'failed', __( 'No se encontró FPDF para generar el PDF.', 'agrocampo-post-venta' ) );
            self::persist_pdf_metrics(
                $submission_id,
                array(
                    'elapsed_ms' => (int) round( self::now_ms() - $started_ms ),
                    'size_bytes' => 0,
                    'page_count' => 0,
                    'warnings' => array(),
                )
            );

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
                self::persist_pdf_metrics(
                    $submission_id,
                    array(
                        'elapsed_ms' => 0,
                        'size_bytes' => (int) filesize( $existing_path ),
                        'page_count' => 0,
                        'warnings' => array(),
                    )
                );

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
            self::persist_pdf_metrics(
                $submission_id,
                array(
                    'elapsed_ms' => (int) round( self::now_ms() - $started_ms ),
                    'size_bytes' => 0,
                    'page_count' => 0,
                    'warnings' => array(),
                )
            );
            return array(
                'status' => 'failed',
                'message' => __( 'No se pudo obtener el directorio de uploads.', 'agrocampo-post-venta' ),
                'attachment_id' => 0,
                'path' => '',
            );
        }

        if ( ! wp_mkdir_p( $upload_dir['path'] ) ) {
            self::update_pdf_status( $submission_id, 'failed', __( 'No se pudo crear el directorio de uploads.', 'agrocampo-post-venta' ) );
            self::persist_pdf_metrics(
                $submission_id,
                array(
                    'elapsed_ms' => (int) round( self::now_ms() - $started_ms ),
                    'size_bytes' => 0,
                    'page_count' => 0,
                    'warnings' => array(),
                )
            );
            return array(
                'status' => 'failed',
                'message' => __( 'No se pudo crear el directorio de uploads.', 'agrocampo-post-venta' ),
                'attachment_id' => 0,
                'path' => '',
            );
        }

        $filename = wp_unique_filename( $upload_dir['path'], self::build_pdf_filename( $submission_id, $submission ) );
        $path     = trailingslashit( $upload_dir['path'] ) . $filename;

        $generated = self::generate_pdf_file( $submission_id, $submission, $path );
        if ( empty( $generated['ok'] ) ) {
            $err = self::$last_pdf_error ? self::$last_pdf_error : __( 'No se pudo generar el PDF.', 'agrocampo-post-venta' );
            self::update_pdf_status( $submission_id, 'failed', $err );
            self::persist_pdf_metrics(
                $submission_id,
                array(
                    'elapsed_ms' => (int) ( $generated['elapsed_ms'] ?? round( self::now_ms() - $started_ms ) ),
                    'size_bytes' => 0,
                    'page_count' => 0,
                    'warnings' => $generated['warnings'] ?? array(),
                )
            );

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
            self::persist_pdf_metrics(
                $submission_id,
                array(
                    'elapsed_ms' => (int) ( $generated['elapsed_ms'] ?? round( self::now_ms() - $started_ms ) ),
                    'size_bytes' => (int) ( $validation['size'] ?? 0 ),
                    'page_count' => (int) ( $generated['page_count'] ?? 0 ),
                    'warnings' => $generated['warnings'] ?? array(),
                )
            );

            return array(
                'status' => 'failed',
                'message' => $validation['message'] ?? __( 'Validación de PDF fallida.', 'agrocampo-post-venta' ),
                'attachment_id' => 0,
                'path' => $path,
            );
        }

        $attachment = array(
            'post_mime_type' => 'application/pdf',
            'post_title'      => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ),
            'post_content'    => '',
            'post_status'     => 'inherit',
        );

        $attach_id = wp_insert_attachment( $attachment, $path );
        if ( ! $attach_id ) {
            self::update_pdf_status( $submission_id, 'failed', __( 'No se pudo registrar el PDF en la biblioteca de medios.', 'agrocampo-post-venta' ) );
            self::persist_pdf_metrics(
                $submission_id,
                array(
                    'elapsed_ms' => (int) ( $generated['elapsed_ms'] ?? round( self::now_ms() - $started_ms ) ),
                    'size_bytes' => (int) ( $validation['size'] ?? 0 ),
                    'page_count' => (int) ( $generated['page_count'] ?? 0 ),
                    'warnings' => $generated['warnings'] ?? array(),
                )
            );

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
        self::persist_pdf_metrics(
            $submission_id,
            array(
                'elapsed_ms' => (int) ( $generated['elapsed_ms'] ?? round( self::now_ms() - $started_ms ) ),
                'size_bytes' => (int) ( $validation['size'] ?? 0 ),
                'page_count' => (int) ( $generated['page_count'] ?? 0 ),
                'warnings' => $generated['warnings'] ?? array(),
            )
        );

        return array(
            'status'        => 'ready',
            'message'       => '',
            'attachment_id' => (int) $attach_id,
            'path'          => $path,
        );
    }

    /**
     * Build deterministic/safe PDF filename.
     */
    private static function build_pdf_filename( int $submission_id, array $submission ): string {
        $report_id = AGP_PV_DB::get_visible_report_id( $submission );
        $report_ref = $report_id > 0 ? $report_id : $submission_id;

        $modelo = self::filename_segment( (string) ( $submission['modelo'] ?? '' ), 32, 'MODELO' );
        $serie = self::filename_segment( (string) ( $submission['serie'] ?? '' ), 28, '' );
        $interno = self::filename_segment( (string) ( $submission['numero_interno'] ?? '' ), 28, 'INTERNO' );
        $serie_o_interno = '' !== $serie ? $serie : $interno;
        $cliente = self::filename_segment( (string) ( $submission['cliente'] ?? '' ), 36, 'CLIENTE' );

        $filename = sprintf( 'IT_%s_%s_%s_%s', (string) $report_ref, $modelo, $serie_o_interno, $cliente );
        $filename = self::sanitize_pdf_filename( $filename );
        if ( '' === $filename ) {
            $filename = 'IT_' . (string) $report_ref;
        }

        return $filename . '.pdf';
    }

    private static function filename_segment( string $value, int $max_length = 40, string $fallback = '' ): string {
        $value = self::normalize_pdf_value( $value, '' );
        $value = remove_accents( $value );
        $value = preg_replace( '/[^A-Za-z0-9]+/', '-', $value ) ?? '';
        $value = trim( $value, '-_.' );
        if ( '' === $value ) {
            return $fallback;
        }

        $max_length = max( 4, $max_length );
        if ( function_exists( 'mb_substr' ) ) {
            return (string) mb_substr( $value, 0, $max_length );
        }

        return substr( $value, 0, $max_length );
    }

    private static function sanitize_pdf_filename( string $value ): string {
        $value = remove_accents( $value );
        $value = preg_replace( '/[^A-Za-z0-9_.-]+/', '-', $value ) ?? '';
        $value = preg_replace( '/-+/', '-', $value ) ?? '';

        return trim( (string) $value, '-_.' );
    }

    /**
     * Validate that a path points to a readable PDF suitable for email attachment.
     *
     * @return array{valid:bool,message:string}
     */
    public static function validate_pdf_attachment_path( string $path ): array {
        $path = trim( $path );
        if ( '' === $path ) {
            return array(
                'valid' => false,
                'message' => __( 'Ruta de PDF vacía.', 'agrocampo-post-venta' ),
            );
        }

        if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
            return array(
                'valid' => false,
                'message' => __( 'El PDF no existe o no se puede leer.', 'agrocampo-post-venta' ),
            );
        }

        $validation = self::validate_pdf_file( $path );
        if ( empty( $validation['valid'] ) ) {
            return array(
                'valid' => false,
                'message' => (string) ( $validation['message'] ?? __( 'PDF inválido para adjuntar.', 'agrocampo-post-venta' ) ),
            );
        }

        $mime = '';
        if ( function_exists( 'wp_check_filetype_and_ext' ) ) {
            $ft = wp_check_filetype_and_ext( $path, basename( $path ), array( 'pdf' => 'application/pdf' ) );
            if ( is_array( $ft ) && ! empty( $ft['type'] ) ) {
                $mime = (string) $ft['type'];
            }
        }

        if ( '' === $mime && function_exists( 'finfo_open' ) ) {
            $fi = finfo_open( FILEINFO_MIME_TYPE );
            if ( false !== $fi ) {
                $detected = finfo_file( $fi, $path );
                if ( is_string( $detected ) ) {
                    $mime = $detected;
                }
                finfo_close( $fi );
            }
        }

        if ( '' !== $mime && ! in_array( $mime, array( 'application/pdf', 'application/x-pdf' ), true ) ) {
            return array(
                'valid' => false,
                'message' => __( 'El archivo adjunto no tiene MIME de PDF válido.', 'agrocampo-post-venta' ),
            );
        }

        return array(
            'valid' => true,
            'message' => '',
        );
    }

    private static function generate_pdf_file( int $submission_id, array $submission, string $path ): array {
        self::$last_pdf_error = '';
        $started_ms = self::now_ms();

        try {
            $logo_config = self::get_logo_config();
            $issue_date  = self::normalize_pdf_date( date_i18n( 'Y-m-d' ), __( 'No informado', 'agrocampo-post-venta' ) );

            $document = new AGP_PV_PDF_Document();
            $report_id = AGP_PV_DB::get_visible_report_id( $submission );
            $document->configure( $report_id > 0 ? $report_id : $submission_id, $issue_date, $logo_config['path'], (float) $logo_config['width_mm'] );

            // Map labels (avoid one/two).
            $tecnico_label = self::normalize_pdf_value( $submission['tecnico_label'] ?? ( $submission['tecnico'] ?? '' ) );
            $tipo_servicio_label = self::normalize_pdf_value( $submission['tipo_servicio_label'] ?? self::map_tipo_servicio( (string) ( $submission['tipo_servicio'] ?? '' ) ) );
            $tipo_mantencion_label = self::normalize_pdf_value( $submission['tipo_mantencion_label'] ?? self::map_tipo_mantencion( (string) ( $submission['tipo_mantencion'] ?? '' ) ) );

            $general_rows = array(
                array(
                    'label' => __( 'Técnico', 'agrocampo-post-venta' ),
                    'value' => $tecnico_label,
                ),
                array(
                    'label' => __( 'Correo', 'agrocampo-post-venta' ),
                    'value' => self::normalize_pdf_value( $submission['email_cliente'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ),
                ),
                array(
                    'label' => __( 'Cliente', 'agrocampo-post-venta' ),
                    'value' => self::normalize_entity_case( self::normalize_pdf_value( $submission['cliente'] ?? '', '' ) ),
                ),
                array(
                    'label' => __( 'Faena / Lugar', 'agrocampo-post-venta' ),
                    'value' => self::normalize_entity_case( self::normalize_pdf_value( $submission['faena_lugar'] ?? '', '' ) ),
                ),
            );

            $equipment_rows = array(
                array(
                    'label' => __( 'Máquina', 'agrocampo-post-venta' ),
                    'value' => self::normalize_entity_case( self::normalize_pdf_value( $submission['maquina'] ?? '', '' ) ),
                ),
                array(
                    'label' => __( 'Modelo', 'agrocampo-post-venta' ),
                    'value' => self::normalize_pdf_value( $submission['modelo'] ?? '', '' ),
                ),
                array(
                    'label' => __( 'Serie', 'agrocampo-post-venta' ),
                    'value' => self::normalize_pdf_value( $submission['serie'] ?? '', '' ),
                ),
                array(
                    'label' => __( 'N° Interno', 'agrocampo-post-venta' ),
                    'value' => self::normalize_internal_number( $submission['numero_interno'] ?? '' ),
                ),
                array(
                    'label' => __( 'Fecha servicio', 'agrocampo-post-venta' ),
                    'value' => self::normalize_pdf_date( $submission['fecha'] ?? '', '' ),
                ),
                array(
                    'label' => __( 'Horas', 'agrocampo-post-venta' ),
                    'value' => self::normalize_pdf_value( $submission['horas'] ?? '', '' ),
                ),
            );

            $general_rows = self::filter_visible_rows( $general_rows, array( __( 'Correo', 'agrocampo-post-venta' ) ) );
            $equipment_rows = self::filter_visible_rows( $equipment_rows );

            $rendered_intro_two_up = ! empty( $general_rows ) && ! empty( $equipment_rows ) && $document->render_intro_cards_two_up( $general_rows, $equipment_rows );
            self::log_layout_event(
                'intro_layout',
                array(
                    'submission_id' => $submission_id,
                    'report_id' => $report_id > 0 ? $report_id : $submission_id,
                    'mode' => $rendered_intro_two_up ? 'two_up' : 'stacked',
                    'general_rows' => count( $general_rows ),
                    'equipment_rows' => count( $equipment_rows ),
                )
            );

            if ( ! $rendered_intro_two_up ) {
                if ( ! empty( $general_rows ) ) {
                    $document->card_start( __( 'Datos Generales', 'agrocampo-post-venta' ), 16.0 );
                    foreach ( $general_rows as $row ) {
                        $document->row2( (string) $row['label'], (string) $row['value'] );
                    }
                    $document->card_end();
                }

                if ( ! empty( $equipment_rows ) ) {
                    $document->card_start( __( 'Equipo', 'agrocampo-post-venta' ), 16.0 );
                    foreach ( $equipment_rows as $row ) {
                        $document->row2( (string) $row['label'], (string) $row['value'] );
                    }
                    $document->card_end();
                }
            }

            $cantidad_horas = self::normalize_pdf_value( $submission['cantidad_horas'] ?? '', '' );
            $fecha_reparacion = self::normalize_pdf_date( $submission['fecha_reparacion'] ?? '', '' );
            $fecha_cierre = self::normalize_pdf_date( $submission['fecha_cierre'] ?? '', '' );
            $estado_maquina = self::normalize_pdf_value( AGP_PV_Plugin::get_machine_status_label( (string) ( $submission['estado_maquina'] ?? '' ) ), '' );
            $fecha_contacto = self::normalize_pdf_date( $submission['fecha_contacto'] ?? '', '' );
            $mantencion_val = self::normalize_pdf_value( $tipo_mantencion_label, '' );

            $service_pairs = array(
                array( __( 'Tipo de Servicio', 'agrocampo-post-venta' ), (string) $tipo_servicio_label ),
                array( __( 'Tipo de Mantención', 'agrocampo-post-venta' ), (string) $mantencion_val ),
                array( __( 'Horas de servicio', 'agrocampo-post-venta' ), $cantidad_horas ),
                array( __( 'Estado de la máquina', 'agrocampo-post-venta' ), $estado_maquina ),
                array( __( 'Fecha a contactar', 'agrocampo-post-venta' ), $fecha_contacto ),
                array( __( 'Fecha reparación', 'agrocampo-post-venta' ), $fecha_reparacion ),
                array( __( 'Fecha cierre', 'agrocampo-post-venta' ), $fecha_cierre ),
            );

            $visible_service_pairs = self::filter_visible_pairs( $service_pairs );

            self::log_layout_event(
                'service_visibility',
                array(
                    'submission_id' => $submission_id,
                    'visible_pairs' => count( $visible_service_pairs ),
                    'total_pairs' => count( $service_pairs ),
                    'render_mode' => 'filtered_pairs',
                )
            );

            if ( ! empty( $visible_service_pairs ) ) {
                $document->card_start( __( 'Servicio', 'agrocampo-post-venta' ), 16.0 );
                foreach ( $visible_service_pairs as $pair ) {
                    $document->row2( (string) $pair[0], (string) $pair[1] );
                }
                $document->card_end();
            }

            $lub = self::limit_pdf_block_text( self::build_pdf_lubricants_text( $submission ) );
            $fil = self::format_supply_lines( self::limit_pdf_block_text( self::normalize_pdf_value( $submission['filtros_utilizados'] ?? '', '', true ) ) );
            $com = self::format_supply_lines( self::limit_pdf_block_text( self::normalize_pdf_value( $submission['componentes_utilizados'] ?? '', '', true ) ) );
            $trabajos = self::limit_pdf_block_text( self::normalize_pdf_value( $submission['trabajos_realizados'] ?? '', '', true ) );
            $observaciones = self::limit_pdf_block_text( self::normalize_pdf_value( $submission['observaciones'] ?? '', '', true ) );

            $detail_rows = array(
                array( __( 'Lubricantes', 'agrocampo-post-venta' ), $lub ),
                array( __( 'Filtros Utilizados', 'agrocampo-post-venta' ), $fil ),
                array( __( 'Componentes Utilizados', 'agrocampo-post-venta' ), $com ),
                array( __( 'Trabajos Realizados', 'agrocampo-post-venta' ), $trabajos ),
                array( __( 'Observaciones', 'agrocampo-post-venta' ), $observaciones ),
            );

            $visible_detail_rows = self::filter_visible_pairs( $detail_rows );

            if ( ! empty( $visible_detail_rows ) ) {
                $document->card_start( __( 'Detalle', 'agrocampo-post-venta' ), 20.0 );
                $lubricants_label = __( 'Lubricantes', 'agrocampo-post-venta' );
                foreach ( $visible_detail_rows as $detail_row ) {
                    $row_label = (string) $detail_row[0];
                    $row_text  = (string) $detail_row[1];
                    if ( $row_label === $lubricants_label ) {
                        $document->lubricants_columns_text( $row_label, $row_text );
                        continue;
                    }
                    $document->adaptive_text( $row_label, $row_text );
                }
            }

            $thresholds = self::get_layout_thresholds();
            $firma_cliente_path = self::resolve_attachment_path( (int) ( $submission['firma_cliente_id'] ?? 0 ) );
            $firma_tecnico_path = self::resolve_attachment_path( (int) ( $submission['firma_tecnico_id'] ?? 0 ) );
            $firma_jefe_taller_path = self::resolve_attachment_path( (int) ( $submission['firma_jefe_taller_id'] ?? 0 ) );
            $nombre_cliente = self::normalize_entity_case( self::normalize_pdf_value( $submission['cliente'] ?? '', __( 'No informado', 'agrocampo-post-venta' ) ) );
            $nombre_tecnico = self::normalize_entity_case( self::normalize_pdf_value( $submission['tecnico_label'] ?? ( $submission['tecnico'] ?? '' ), __( 'No informado', 'agrocampo-post-venta' ) ) );
            $branding = self::get_branding_config();
            $nombre_jefe_taller = self::normalize_entity_case( self::normalize_pdf_value( (string) ( $branding['jefe_taller_nombre'] ?? '' ), __( 'No informado', 'agrocampo-post-venta' ) ) );
            $is_garantia = 'two' === (string) ( $submission['tipo_servicio'] ?? '' );
            $show_jefe_signature = $is_garantia || ( '' !== $firma_jefe_taller_path );
            $signatures_no_images = ! $firma_cliente_path && ! $firma_tecnico_path && ! $firma_jefe_taller_path;
            $signature_row_count = 1;

            if ( ! empty( $visible_detail_rows ) ) {
                $needed_for_signatures = $document->estimate_card_end_height() + $document->estimate_signature_section_height( false, $signatures_no_images, $signature_row_count ) + (float) $thresholds['preflight_extra_padding_mm'];
                if ( $document->get_space_left() < $needed_for_signatures ) {
                    $document->set_compact_density( true );
                    $document->set_signature_compact( true );
                }

                $document->card_end();
            }

            $signature_card_min_height = $signatures_no_images ? max( 18.0, (float) $thresholds['signatures_card_min_height'] - 10.0 ) : (float) $thresholds['signatures_card_min_height'];
            $document->card_start( __( 'Firmas', 'agrocampo-post-venta' ), $signature_card_min_height );
            if ( $show_jefe_signature ) {
                $document->signature_row_three(
                    array(
                        'label' => __( 'Firma Cliente', 'agrocampo-post-venta' ),
                        'path'  => $firma_cliente_path,
                        'name'  => $nombre_cliente,
                    ),
                    array(
                        'label' => __( 'Firma Técnico', 'agrocampo-post-venta' ),
                        'path'  => $firma_tecnico_path,
                        'name'  => $nombre_tecnico,
                    ),
                    array(
                        'label' => __( 'Firma Jefe de Taller', 'agrocampo-post-venta' ),
                        'path'  => $firma_jefe_taller_path,
                        'name'  => $nombre_jefe_taller,
                    )
                );
            } else {
                $document->signature_row(
                    array(
                        'label' => __( 'Firma Cliente', 'agrocampo-post-venta' ),
                        'path'  => $firma_cliente_path,
                        'name'  => $nombre_cliente,
                    ),
                    array(
                        'label' => __( 'Firma Técnico', 'agrocampo-post-venta' ),
                        'path'  => $firma_tecnico_path,
                        'name'  => $nombre_tecnico,
                    )
                );
            }
            $document->card_end();

            $document->Output( 'F', $path );

            $warnings = $document->get_warnings();

            // Log non-fatal warnings.
            foreach ( $warnings as $warning ) {
                self::log( $warning );
            }

            return array(
                'ok' => file_exists( $path ),
                'elapsed_ms' => (int) round( self::now_ms() - $started_ms ),
                'page_count' => $document->PageNo(),
                'warnings' => $warnings,
            );
        } catch ( Exception $e ) {
            self::$last_pdf_error = $e->getMessage();
            self::log( 'PDF generation failed: ' . $e->getMessage() );

            return array(
                'ok' => false,
                'elapsed_ms' => (int) round( self::now_ms() - $started_ms ),
                'page_count' => 0,
                'warnings' => array(),
            );
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


    /**
     * Configurable branding used by PDF header/footer.
     *
     * @return array{header_title:string,footer_text:string,it_label_template:string,jefe_taller_nombre:string}
     */
    public static function get_branding_config(): array {
        $header_title = (string) get_option( 'agp_pv_pdf_header_title', __( 'INFORME TÉCNICO', 'agrocampo-post-venta' ) );
        $footer_text = (string) get_option( 'agp_pv_pdf_footer_text', __( 'Talca • Linares • Parral   |   +56 9 9748 5650', 'agrocampo-post-venta' ) );
        $it_label_template = (string) get_option( 'agp_pv_pdf_it_label_template', __( 'IT: %d', 'agrocampo-post-venta' ) );
        $jefe_taller_nombre = (string) get_option( 'agp_pv_jefe_taller_nombre', '' );

        $header_title = sanitize_text_field( $header_title );
        $footer_text = sanitize_text_field( $footer_text );
        $it_label_template = sanitize_text_field( $it_label_template );
        $jefe_taller_nombre = sanitize_text_field( $jefe_taller_nombre );

        if ( '' === $header_title ) {
            $header_title = __( 'INFORME TÉCNICO', 'agrocampo-post-venta' );
        }
        if ( '' === $footer_text ) {
            $footer_text = __( 'Talca • Linares • Parral   |   +56 9 9748 5650', 'agrocampo-post-venta' );
        }
        if ( '' === $it_label_template ) {
            $it_label_template = __( 'IT: %d', 'agrocampo-post-venta' );
        }

        if ( function_exists( 'mb_substr' ) ) {
            $header_title = mb_substr( $header_title, 0, 70 );
            $footer_text = mb_substr( $footer_text, 0, 110 );
            $it_label_template = mb_substr( $it_label_template, 0, 40 );
            $jefe_taller_nombre = mb_substr( $jefe_taller_nombre, 0, 80 );
        } else {
            $header_title = substr( $header_title, 0, 70 );
            $footer_text = substr( $footer_text, 0, 110 );
            $it_label_template = substr( $it_label_template, 0, 40 );
            $jefe_taller_nombre = substr( $jefe_taller_nombre, 0, 80 );
        }

        return array(
            'header_title' => $header_title,
            'footer_text' => $footer_text,
            'it_label_template' => $it_label_template,
            'jefe_taller_nombre' => $jefe_taller_nombre,
        );
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
            'one' => __( 'Factura Cliente', 'agrocampo-post-venta' ),
            'two' => __( 'Garantía', 'agrocampo-post-venta' ),
            'Interno' => __( 'Mantención', 'agrocampo-post-venta' ),
            'Visita-de-Cortesía' => __( 'Visita de Cortesía', 'agrocampo-post-venta' ),
            'Diagnostico-Técnico' => __( 'Diagnóstico Técnico', 'agrocampo-post-venta' ),
            'Entrega-Técnica' => __( 'Entrega Técnica', 'agrocampo-post-venta' ),
        );

        return $map[ $value ] ?? $value;
    }

    private static function map_tipo_mantencion( string $value ): string {
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

    /**
     * @param array<int,array{label:string,value:string}> $rows
     * @param string[] $labels_keep_missing
     * @return array<int,array{label:string,value:string}>
     */
    private static function filter_visible_rows( array $rows, array $labels_keep_missing = array() ): array {
        $filtered = array();
        foreach ( $rows as $row ) {
            $label = (string) ( $row['label'] ?? '' );
            $value = (string) ( $row['value'] ?? '' );
            $allow_missing = in_array( $label, $labels_keep_missing, true );
            if ( ! $allow_missing && ! self::is_renderable_value( $value ) ) {
                continue;
            }

            $filtered[] = array(
                'label' => $label,
                'value' => $value,
            );
        }

        return $filtered;
    }

    /**
     * @param array<int,array{0:string,1:string}> $pairs
     * @return array<int,array{0:string,1:string}>
     */
    private static function filter_visible_pairs( array $pairs ): array {
        $filtered = array();

        foreach ( $pairs as $pair ) {
            $value = (string) ( $pair[1] ?? '' );
            if ( ! self::is_renderable_value( $value ) ) {
                continue;
            }

            $filtered[] = array( (string) $pair[0], $value );
        }

        return $filtered;
    }

    private static function is_renderable_value( string $value ): bool {
        return ! self::is_missing_display_value( trim( $value ) );
    }

    private static function normalize_pdf_date( $value, string $fallback = '' ): string {
        $raw = self::normalize_pdf_value( $value, '' );
        if ( '' === $raw ) {
            return $fallback;
        }

        $raw = trim( $raw );

        if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches ) ) {
            return sprintf( '%02d/%02d/%04d', (int) $matches[3], (int) $matches[2], (int) $matches[1] );
        }

        if ( preg_match( '/^(\d{2})-(\d{2})-(\d{4})$/', $raw, $matches ) ) {
            return sprintf( '%02d/%02d/%04d', (int) $matches[1], (int) $matches[2], (int) $matches[3] );
        }

        if ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $matches ) ) {
            $first  = (int) $matches[1];
            $second = (int) $matches[2];
            $year   = (int) $matches[3];

            if ( $first > 12 && $second <= 12 ) {
                return sprintf( '%02d/%02d/%04d', $first, $second, $year );
            }

            // Formularios legacy pueden guardar MM/DD/YYYY cuando el segundo componente excede 12.
            if ( $first <= 12 && $second > 12 ) {
                return sprintf( '%02d/%02d/%04d', $second, $first, $year );
            }

            // Si la fecha es ambigua (ambos <= 12), se preserva DD/MM/YYYY para evitar intercambios erróneos.
            return sprintf( '%02d/%02d/%04d', $first, $second, $year );
        }

        $timestamp = strtotime( $raw );
        if ( false !== $timestamp ) {
            return gmdate( 'd/m/Y', $timestamp );
        }

        return $raw;
    }

    private static function normalize_internal_number( $value ): string {
        $normalized = self::normalize_pdf_value( $value, '' );
        if ( '' === $normalized ) {
            return '';
        }

        $token = strtolower( preg_replace( '/[^a-z0-9]/i', '', $normalized ) );
        if ( '' === $token || preg_match( '/^0+$/', $token ) ) {
            return '';
        }

        return $normalized;
    }

    private static function normalize_entity_case( string $value ): string {
        if ( self::is_missing_display_value( $value ) ) {
            return $value;
        }

        if ( function_exists( 'mb_convert_case' ) ) {
            return mb_convert_case( mb_strtolower( $value, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
        }

        return ucwords( strtolower( $value ) );
    }

    private static function format_supply_lines( string $text ): string {
        $normalized = self::normalize_pdf_value( $text, '', true );
        if ( self::is_missing_display_value( $normalized ) ) {
            return '';
        }

        $lines = explode( "\n", $normalized );
        $formatted = array();

        foreach ( $lines as $line ) {
            $line = trim( (string) $line );
            if ( '' === $line ) {
                continue;
            }

            if ( preg_match( '/^(\d+)\s*[xX]?\s+(.+)$/u', $line, $matches ) ) {
                $formatted[] = trim( $matches[2] ) . ' (x' . (int) $matches[1] . ')';
                continue;
            }

            if ( preg_match( '/^(.+?)\s+(\d+)$/u', $line, $matches ) ) {
                $formatted[] = trim( $matches[1] ) . ' (x' . (int) $matches[2] . ')';
                continue;
            }

            $formatted[] = $line;
        }

        if ( empty( $formatted ) ) {
            return '';
        }

        return implode( "\n", $formatted );
    }

    private static function build_pdf_lubricants_text( array $submission ): string {
        $raw_json = isset( $submission['lubricantes_json'] ) ? trim( (string) $submission['lubricantes_json'] ) : '';
        if ( '' !== $raw_json ) {
            $decoded = json_decode( $raw_json, true );
            if ( is_array( $decoded ) ) {
                $json_lines = self::build_pdf_lubricants_lines_from_json_items( $decoded );
                if ( ! empty( $json_lines ) ) {
                    return implode( "\n", $json_lines );
                }
            }
        }

        $legacy_text = self::normalize_pdf_value( AGP_PV_Plugin::resolve_lubricants_text_from_submission( $submission ), '', true );
        if ( self::is_missing_display_value( $legacy_text ) ) {
            return '';
        }

        return implode( "\n", self::build_pdf_lubricants_lines_from_legacy_text( $legacy_text ) );
    }

    /**
     * @param array<int,mixed> $items
     * @return array<int,string>
     */
    private static function build_pdf_lubricants_lines_from_json_items( array $items ): array {
        $lines = array();

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $product  = sanitize_text_field( (string) ( $item['product'] ?? '' ) );
            $quantity = sanitize_text_field( (string) ( $item['quantity'] ?? '' ) );

            if ( '' === $product ) {
                continue;
            }

            $lines[] = self::format_pdf_lubricant_line( $product, $quantity );
        }

        return $lines;
    }

    /**
     * @return array<int,string>
     */
    private static function build_pdf_lubricants_lines_from_legacy_text( string $legacy_text ): array {
        $lines = array();

        foreach ( explode( "\n", $legacy_text ) as $line ) {
            $line = trim( (string) $line );
            if ( '' === $line ) {
                continue;
            }

            if ( preg_match( '/^(?:(.+?)\s-\s)?(.+?)(?:\s\((.*)\))?$/u', $line, $matches ) ) {
                $product = isset( $matches[2] ) ? trim( (string) $matches[2] ) : '';
                $meta    = isset( $matches[3] ) ? trim( (string) $matches[3] ) : '';

                if ( '' !== $product ) {
                    $qty_part = '';
                    if ( '' !== $meta && preg_match( '/(?:^|;\s*)Cantidad:\s*([^;]+)/u', $meta, $qty_matches ) ) {
                        $qty_part = trim( (string) ( $qty_matches[1] ?? '' ) );
                    }

                    $lines[] = self::format_pdf_lubricant_line( $product, $qty_part );
                    continue;
                }
            }

            $clean_line = preg_replace( '/\s*\([^)]*\)\s*$/u', '', $line );
            $clean_line = is_string( $clean_line ) ? trim( $clean_line ) : '';
            if ( '' === $clean_line ) {
                continue;
            }

            $lines[] = self::format_pdf_lubricant_line( $clean_line, '' );
        }

        return $lines;
    }

    private static function format_pdf_lubricant_line( string $product, string $quantity ): string {
        $product_clean = sanitize_text_field( trim( $product ) );
        $qty_clean = self::normalize_lubricant_quantity_for_pdf( $quantity );

        return $product_clean . ' - CANTIDAD = "' . $qty_clean . '"';
    }

    private static function normalize_lubricant_quantity_for_pdf( string $quantity ): string {
        $quantity = sanitize_text_field( trim( $quantity ) );
        if ( '' === $quantity ) {
            return '';
        }

        if ( preg_match( '/(\d+(?:[.,]\d+)?)/u', $quantity, $matches ) ) {
            return (string) $matches[1];
        }

        return '';
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

        if ( self::is_missing_display_value( $normalized ) ) {
            return $fallback;
        }

        return $normalized;
    }

    public static function is_missing_display_value( string $value ): bool {
        $token = self::normalize_missing_token( $value );
        return '' === $token || in_array( $token, self::missing_value_tokens(), true );
    }

    private static function normalize_missing_token( string $value ): string {
        $normalized = strtolower( trim( preg_replace( '/\s+/u', ' ', (string) $value ) ) );
        if ( function_exists( 'remove_accents' ) ) {
            $normalized = strtolower( remove_accents( $normalized ) );
        }

        return str_replace( '_', ' ', $normalized );
    }

    /**
     * Canonical placeholder tokens considered as "dato ausente" in display.
     *
     * @return string[]
     */
    private static function missing_value_tokens(): array {
        return array(
            'no informado',
            'no informada',
            'sin informar',
            'sin informacion',
            'n/i',
            'n.d.',
            'n/d',
            'null',
            '(null)',
            'n/a',
            'na',
            'none',
            '-',
            '',
        );
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

    /**
     * Layout thresholds used by PDF preflight decisions.
     *
     * @return array<string,mixed>
     */
    public static function get_layout_thresholds(): array {
        $thresholds = array(
            'observaciones_short_max_chars' => 220,
            'observaciones_short_allow_newline' => false,
            'force_signature_compact_when_compact_density' => true,
            'signatures_card_min_height' => 28.0,
            'preflight_extra_padding_mm' => 0.0,
        );

        if ( function_exists( 'apply_filters' ) ) {
            $filtered = apply_filters( 'agp_pv_pdf_layout_thresholds', $thresholds );
            if ( is_array( $filtered ) ) {
                $thresholds = array_merge( $thresholds, $filtered );
            }
        }

        $thresholds['observaciones_short_max_chars'] = max( 40, (int) $thresholds['observaciones_short_max_chars'] );
        $thresholds['observaciones_short_allow_newline'] = (bool) $thresholds['observaciones_short_allow_newline'];
        $thresholds['force_signature_compact_when_compact_density'] = (bool) $thresholds['force_signature_compact_when_compact_density'];
        $thresholds['signatures_card_min_height'] = max( 18.0, (float) $thresholds['signatures_card_min_height'] );
        $thresholds['preflight_extra_padding_mm'] = max( 0.0, (float) $thresholds['preflight_extra_padding_mm'] );

        return $thresholds;
    }

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

    private static function is_layout_logging_enabled(): bool {
        return ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || (bool) get_option( 'agp_pv_debug' ) || (bool) get_option( 'agp_pv_pdf_layout_debug' );
    }

    /**
     * Structured debug logs for PDF layout decisions.
     */
    private static function log_layout_event( string $event, array $context ): void {
        if ( ! self::is_layout_logging_enabled() ) {
            return;
        }

        $payload = array_merge(
            array(
                'event' => $event,
                'ts' => gmdate( 'c' ),
            ),
            $context
        );

        $encoded = wp_json_encode( $payload );
        if ( false === $encoded || '' === $encoded ) {
            self::log( 'layout_event=' . $event );
            return;
        }

        self::log( 'layout_event=' . $encoded );
    }

    private static function log( string $message ): void {
        if ( ! self::is_layout_logging_enabled() ) {
            return;
        }

        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[agp_pv_pdf] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
}
