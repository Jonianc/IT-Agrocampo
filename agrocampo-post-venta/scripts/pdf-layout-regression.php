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

$doc = new AGP_PV_PDF_Document();
$doc->configure(4159, '24-02-2026', '', 38.0);

$general_short = [
    ['label' => 'Técnico', 'value' => 'Juan Castro Meza'],
    ['label' => 'Correo', 'value' => 'alex.perez@garcesfruit.com'],
    ['label' => 'Cliente', 'value' => 'Agricola Garcés spa'],
    ['label' => 'Faena / Lugar', 'value' => 'La estacion'],
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

assert_true(AGP_PV_PDF::is_missing_display_value('No informado'), 'Detecta placeholder clásico No informado.');
assert_true(AGP_PV_PDF::is_missing_display_value('SIN INFORMACIÓN'), 'Detecta placeholder con acentos/case distintos.');
assert_true(AGP_PV_PDF::is_missing_display_value('n/a'), 'Detecta placeholder N/A.');
assert_true(!AGP_PV_PDF::is_missing_display_value('Factura Cliente'), 'No marca como ausente un valor válido.');

fwrite(STDOUT, "\nRegresión PDF OK\n");
