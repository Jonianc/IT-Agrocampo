<?php

if ( ! class_exists( 'FPDF' ) ) {
    class FPDF {
        protected array $pages = array();
        protected int $page = 0;
        protected string $font = 'Helvetica';
        protected int $font_size = 12;
        protected string $font_style = '';
        protected float $x = 10;
        protected float $y = 10;
        protected float $line_height = 5;
        protected float $page_width = 595.28;
        protected float $page_height = 841.89;
        protected float $l_margin = 0;
        protected float $r_margin = 0;
        protected float $t_margin = 0;
        protected float $b_margin = 0;
        protected bool $auto_page_break = true;
        protected float $page_break_trigger = 0;
        protected array $images = array();
        protected array $page_images = array();
        protected int $image_index = 0;

        public function AddPage( string $orientation = 'P', string $size = 'A4' ): void {
            $this->set_page_size( $orientation, $size );
            $this->page++;
            $this->pages[ $this->page ] = '';
            $this->x = $this->l_margin;
            $this->y = $this->t_margin;
            $this->page_images[ $this->page ] = array();
        }

        public function SetFont( string $family, string $style = '', int $size = 12 ): void {
            $this->font = $family;
            $this->font_style = strtoupper( $style );
            $this->font_size = $size;
            $this->line_height = $size * 0.4 + 2;
        }

        public function SetMargins( float $left, float $top, ?float $right = null ): void {
            $this->l_margin = $left;
            $this->t_margin = $top;
            $this->r_margin = ( null === $right ) ? $left : $right;
        }

        public function SetAutoPageBreak( bool $auto, float $margin = 0 ): void {
            $this->auto_page_break = $auto;
            $this->b_margin = $margin;
            $this->page_break_trigger = $this->page_height - $this->b_margin;
        }

        public function SetX( float $x ): void {
            $this->x = $x;
        }

        public function SetY( float $y ): void {
            $this->y = $y;
        }

        public function SetXY( float $x, float $y ): void {
            $this->x = $x;
            $this->y = $y;
        }

        public function GetX(): float {
            return $this->x;
        }

        public function GetY(): float {
            return $this->y;
        }

        public function Ln( float $h = 0 ): void {
            $this->y += ( $h > 0 ) ? $h : $this->line_height;
            $this->x = $this->l_margin;
        }

        public function Cell( float $w, float $h, string $txt = '' ): void {
            $this->text( $this->x, $this->y + $h, $txt );
            $this->x += $w;
        }

        public function MultiCell( float $w, float $h, string $txt = '' ): void {
            $lines = preg_split( "/\r\n|\r|\n/", $txt );
            foreach ( $lines as $line ) {
                $this->text( $this->x, $this->y + $h, $line );
                $this->y += $h;
                $this->x = $this->l_margin;
            }
        }

        protected function text( float $x, float $y, string $txt ): void {
            $escaped = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $txt );
            $y_pos = $this->page_height - $y;
            $this->pages[ $this->page ] .= sprintf( "BT /F1 %d Tf %.2f %.2f Td (%s) Tj ET\n", $this->font_size, $x, $y_pos, $escaped );
            if ( 'B' === $this->font_style ) {
                $this->pages[ $this->page ] .= sprintf( "BT /F1 %d Tf %.2f %.2f Td (%s) Tj ET\n", $this->font_size, $x + 0.2, $y_pos, $escaped );
            }
        }

        public function GetStringWidth( string $txt ): float {
            return (float) ( strlen( $txt ) * ( $this->font_size * 0.5 ) );
        }

        public function Line( float $x1, float $y1, float $x2, float $y2 ): void {
            $y1 = $this->page_height - $y1;
            $y2 = $this->page_height - $y2;
            $this->pages[ $this->page ] .= sprintf( "%.2f %.2f m %.2f %.2f l S\n", $x1, $y1, $x2, $y2 );
        }

        public function Rect( float $x, float $y, float $w, float $h ): void {
            $y = $this->page_height - $y;
            $this->pages[ $this->page ] .= sprintf( "%.2f %.2f %.2f %.2f re S\n", $x, $y - $h, $w, $h );
        }

        public function Image( string $file, float $x, float $y, float $w = 0, float $h = 0 ): void {
            if ( ! file_exists( $file ) ) {
                return;
            }

            $info = getimagesize( $file );
            if ( ! $info ) {
                return;
            }

            $width = (int) $info[0];
            $height = (int) $info[1];
            $mime = $info['mime'] ?? '';

            $img = null;
            if ( 'image/png' === $mime ) {
                $img = imagecreatefrompng( $file );
            } elseif ( 'image/jpeg' === $mime ) {
                $img = imagecreatefromjpeg( $file );
            }

            if ( ! $img ) {
                return;
            }

            $data = '';
            for ( $y_px = 0; $y_px < $height; $y_px++ ) {
                for ( $x_px = 0; $x_px < $width; $x_px++ ) {
                    $color = imagecolorat( $img, $x_px, $y_px );
                    $data .= chr( ( $color >> 16 ) & 0xFF ) . chr( ( $color >> 8 ) & 0xFF ) . chr( $color & 0xFF );
                }
            }
            imagedestroy( $img );

            $data = gzcompress( $data );

            $name = 'I' . ( ++$this->image_index );
            $this->images[ $name ] = array(
                'w' => $width,
                'h' => $height,
                'data' => $data,
            );
            $this->page_images[ $this->page ][ $name ] = true;

            if ( 0 === $w && 0 === $h ) {
                $w = $width;
                $h = $height;
            } elseif ( 0 === $h ) {
                $h = ( $w * $height ) / $width;
            } elseif ( 0 === $w ) {
                $w = ( $h * $width ) / $height;
            }

            $y_pos = $this->page_height - $y - $h;
            $this->pages[ $this->page ] .= sprintf( "q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n", $w, $h, $x, $y_pos, $name );
        }

        public function Output( string $dest = 'I', string $name = '' ): string {
            $objects = array();
            $offsets = array();
            $buffer = "%PDF-1.3\n";

            $objects[] = "1 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";

            foreach ( $this->images as $name => $image ) {
                $obj_id = count( $objects ) + 1;
                $this->images[ $name ]['obj_id'] = $obj_id;
                $length = strlen( $image['data'] );
                $objects[] = $obj_id . " 0 obj << /Type /XObject /Subtype /Image /Width " . $image['w'] . " /Height " . $image['h'] . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . $length . " >> stream\n" . $image['data'] . "\nendstream endobj\n";
            }

            $page_count = count( $this->pages );
            $pages_id = ( $page_count * 2 ) + 2;
            $catalog_id = $pages_id + 1;

            $kids = array();
            foreach ( $this->pages as $index => $content ) {
                $content_obj = count( $objects ) + 1;
                $objects[] = $content_obj . " 0 obj << /Length " . strlen( $content ) . " >> stream\n" . $content . "endstream endobj\n";

                $page_obj = count( $objects ) + 1;
                $xobjects = '';
                if ( ! empty( $this->page_images[ $index ] ) ) {
                    $items = array();
                    foreach ( array_keys( $this->page_images[ $index ] ) as $name ) {
                        if ( isset( $this->images[ $name ]['obj_id'] ) ) {
                            $items[] = '/' . $name . ' ' . $this->images[ $name ]['obj_id'] . ' 0 R';
                        }
                    }
                    if ( $items ) {
                        $xobjects = ' /XObject << ' . implode( ' ', $items ) . ' >>';
                    }
                }

                $objects[] = $page_obj . " 0 obj << /Type /Page /Parent " . $pages_id . " 0 R /MediaBox [0 0 " . $this->page_width . " " . $this->page_height . "] /Resources << /Font << /F1 1 0 R >>" . $xobjects . " >> /Contents " . $content_obj . " 0 R >> endobj\n";
                $kids[] = $page_obj . " 0 R";
            }

            $objects[] = $pages_id . " 0 obj << /Type /Pages /Count " . count( $kids ) . " /Kids [" . implode( ' ', $kids ) . "] >> endobj\n";
            $objects[] = $catalog_id . " 0 obj << /Type /Catalog /Pages " . $pages_id . " 0 R >> endobj\n";

            foreach ( $objects as $obj ) {
                $offsets[] = strlen( $buffer );
                $buffer .= $obj;
            }

            $xref = strlen( $buffer );
            $buffer .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
            foreach ( $offsets as $offset ) {
                $buffer .= sprintf( "%010d 00000 n \n", $offset );
            }

            $buffer .= "trailer << /Size " . ( count( $objects ) + 1 ) . " /Root " . $catalog_id . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

            if ( 'S' === $dest ) {
                return $buffer;
            }

            if ( 'F' === $dest && $name ) {
                file_put_contents( $name, $buffer );
                return '';
            }

            echo $buffer;
            return '';
        }

        protected function set_page_size( string $orientation, string $size ): void {
            $width = 595.28;
            $height = 841.89;
            if ( 'A4' !== strtoupper( $size ) ) {
                $width = 595.28;
                $height = 841.89;
            }

            if ( 'L' === strtoupper( $orientation ) ) {
                $this->page_width = $height;
                $this->page_height = $width;
            } else {
                $this->page_width = $width;
                $this->page_height = $height;
            }

            $this->page_break_trigger = $this->page_height - $this->b_margin;
        }
    }
}
