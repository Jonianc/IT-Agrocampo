#!/usr/bin/env php
<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('AGP_PV_PLUGIN_DIR')) {
    define('AGP_PV_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string {
        return $text;
    }
}

if (!function_exists('_n')) {
    function _n(string $single, string $plural, int $number, string $domain = ''): string {
        return 1 === $number ? $single : $plural;
    }
}

if (!function_exists('remove_accents')) {
    function remove_accents(string $text): string {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (false !== $converted) {
                return $converted;
            }
        }

        return $text;
    }
}

require_once dirname(__DIR__) . '/includes/class-agp-pv-pdf.php';

function assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "[PASS] {$message}\n");
}

function make_doc(): AGP_PV_PDF_Document {
    $doc = new AGP_PV_PDF_Document();
    $doc->configure(4159, '24-02-2026', '', 38.0);
    return $doc;
}

$doc = make_doc();

$general_short = [
    ['label' => 'Técnico', 'value' => 'Juan Castro Meza'],
    ['label' => 'Correo', 'value' => 'alex.perez@garcesfruit.com'],
    ['label' => 'Cliente', 'value' => 'Agricola Garcés spa'],
    ['label' => 'Faena / Lugar', 'value' => 'La estación'],
];

$equipment_short = [
    ['label' => 'Máquina', 'value' => 'Tractor'],
    ['label' => 'Modelo', 'value' => '4275'],
    ['label' => 'Serie', 'value' => '9AGT0012VMV008942'],
    ['label' => 'N° Interno', 'value' => '1'],
    ['label' => 'Fecha', 'value' => '2026/02/23'],
    ['label' => 'Horas', 'value' => '2150'],
];

$rendered_short = $doc->render_intro_cards_two_up($general_short, $equipment_short);
assert_true($rendered_short, '2-up se renderiza cuando el contenido es corto.');

$doc->AddPage('P', 'A4');

$general_long = [
    ['label' => 'Técnico', 'value' => 'Juan Castro Meza con un nombre extremadamente largo para forzar wrapping en múltiples líneas dentro de un ancho reducido'],
    ['label' => 'Correo', 'value' => 'correo.largo+con.sufijos@dominio-muy-extenso-ejemplo.cl'],
    ['label' => 'Cliente', 'value' => 'Cliente con razón social extremadamente extensa para validar fallback seguro sin solapes'],
    ['label' => 'Faena / Lugar', 'value' => 'Faena ubicada en una localización con descripción larga y detallada para superar límites del layout 2-up'],
];

$equipment_long = [
    ['label' => 'Máquina', 'value' => 'Modelo de máquina con denominación comercial demasiado extensa para una sola línea'],
    ['label' => 'Modelo', 'value' => 'X-4275-ULTRA-MAX-PREMIUM'],
    ['label' => 'Serie', 'value' => 'SERIE-EXTREMADAMENTE-LARGA-1234567890-ABCDEFGHIJ-9876543210'],
    ['label' => 'N° Interno', 'value' => 'INT-0000000000000001'],
    ['label' => 'Fecha', 'value' => '2026/02/23'],
    ['label' => 'Horas', 'value' => '2150.5'],
];

$rendered_long = $doc->render_intro_cards_two_up($general_long, $equipment_long);
assert_true(!$rendered_long, '2-up hace fallback a apilado cuando el contenido crece.');

$short_text = "Chequeo general\nOK";
$long_text = str_repeat("Observación con contenido extenso para probar estimadores y overflow controlado. ", 24);

$short_h = $doc->estimate_adaptive_text_height($short_text);
$long_h = $doc->estimate_adaptive_text_height($long_text);
assert_true($long_h > $short_h, 'estimate_adaptive_text_height crece con contenido largo.');

$row_short_h = $doc->estimate_row2_value_height('Dato corto');
$row_long_h = $doc->estimate_row2_value_height($long_text);
assert_true($row_long_h > $row_short_h, 'estimate_row2_value_height detecta wrapping en valores largos.');

$box_short_h = $doc->estimate_box_text_height($short_text);
$box_long_h = $doc->estimate_box_text_height($long_text);
assert_true($box_long_h > $box_short_h, 'estimate_box_text_height aumenta en texto largo.');

$signatures_no_images = $doc->estimate_signature_section_height(false, true);
$signatures_with_images = $doc->estimate_signature_section_height(false, false);
assert_true($signatures_with_images > $signatures_no_images, 'signature_section_height reserva más espacio con imágenes de firmas.');

$compact_doc = make_doc();
$normal_signature_h = $compact_doc->estimate_signature_section_height(false, false);
$compact_doc->set_signature_compact(true);
$compact_signature_h = $compact_doc->estimate_signature_section_height(true, false);
assert_true($compact_signature_h < $normal_signature_h, 'signature_section_height reduce altura en modo compacto.');

$compact_density_doc = make_doc();
$normal_card_end = $compact_density_doc->estimate_card_end_height();
$compact_density_doc->set_compact_density(true);
$compact_card_end = $compact_density_doc->estimate_card_end_height();
assert_true($compact_card_end < $normal_card_end, 'estimate_card_end_height baja con compact_density.');

assert_true(AGP_PV_PDF::is_missing_display_value('No informado'), 'Detecta placeholder clásico No informado.');
assert_true(AGP_PV_PDF::is_missing_display_value('SIN INFORMACIÓN'), 'Detecta placeholder con acentos/case distintos.');
assert_true(AGP_PV_PDF::is_missing_display_value('n/a'), 'Detecta placeholder N/A.');
assert_true(AGP_PV_PDF::is_missing_display_value(' null '), 'Detecta placeholder null con espacios.');
assert_true(!AGP_PV_PDF::is_missing_display_value('Factura Cliente'), 'No marca como ausente un valor válido.');

$doc->section_title('Estrés observaciones');
$doc->card_start('Observaciones', 20.0);
$doc->adaptive_text('Observaciones', $long_text);
$doc->card_end();
$doc->card_start('Firmas', 28.0);
$doc->signature_row(['label' => 'Firma Cliente', 'path' => ''], ['label' => 'Firma Técnico', 'path' => '']);
$doc->card_end();
assert_true($doc->PageNo() >= 1, 'Render de observaciones extensas + firmas no produce excepción.');

fwrite(STDOUT, "\nRegresión PDF extendida OK\n");
