<?php

if ( ! class_exists( 'FPDF' ) ) {
    class FPDF {
        protected array $pages = array();
        protected int $page = 0;
        protected string $font = 'Helvetica';
        protected int $font_size = 12;
        protected float $x = 10;
        protected float $y = 10;
        protected float $line_height = 5;

        public function AddPage(): void {
            $this->page++;
            $this->pages[ $this->page ] = "";
            $this->x = 10;
            $this->y = 10;
        }

        public function SetFont( string $family, string $style = '', int $size = 12 ): void {
            $this->font = $family;
            $this->font_size = $size;
            $this->line_height = $size * 0.4 + 2;
        }

        public function Ln( float $h = 0 ): void {
            $this->y += ( $h > 0 ) ? $h : $this->line_height;
            $this->x = 10;
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
                $this->x = 10;
            }
        }

        protected function text( float $x, float $y, string $txt ): void {
            $escaped = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $txt );
            $this->pages[ $this->page ] .= sprintf( "BT /F1 %d Tf %.2f %.2f Td (%s) Tj ET\n", $this->font_size, $x, 842 - $y, $escaped );
        }

        public function Output( string $dest = 'S' ): string {
            $objects = array();
            $offsets = array();
            $buffer = "%PDF-1.3\n";

            $objects[] = "1 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";

            $kids = array();
            foreach ( $this->pages as $index => $content ) {
                $content_obj = count( $objects ) + 1;
                $objects[] = $content_obj . " 0 obj << /Length " . strlen( $content ) . " >> stream\n" . $content . "endstream endobj\n";

                $page_obj = count( $objects ) + 1;
                $objects[] = $page_obj . " 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 1 0 R >> >> /Contents " . $content_obj . " 0 R >> endobj\n";
                $kids[] = $page_obj . " 0 R";
            }

            $objects[] = "2 0 obj << /Type /Pages /Count " . count( $kids ) . " /Kids [" . implode( ' ', $kids ) . "] >> endobj\n";
            $objects[] = ( count( $objects ) + 1 ) . " 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";

            foreach ( $objects as $obj ) {
                $offsets[] = strlen( $buffer );
                $buffer .= $obj;
            }

            $xref = strlen( $buffer );
            $buffer .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
            foreach ( $offsets as $offset ) {
                $buffer .= sprintf( "%010d 00000 n \n", $offset );
            }

            $buffer .= "trailer << /Size " . ( count( $objects ) + 1 ) . " /Root " . ( count( $objects ) ) . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

            if ( 'S' === $dest ) {
                return $buffer;
            }

            echo $buffer;
            return '';
        }
    }
}
