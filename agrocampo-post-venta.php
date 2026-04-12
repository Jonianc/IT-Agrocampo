<?php
/**
 * Plugin Name: Agrocampo Post Venta
 * Description: Formulario Post Venta standalone con almacenamiento, PDF y correo.
 * Version: 1.22.4
 * Author: Agrocampo
 * Text Domain: agrocampo-post-venta
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AGP_PV_VERSION', '1.22.4' );

define( 'AGP_PV_PLUGIN_FILE', __FILE__ );

define( 'AGP_PV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

define( 'AGP_PV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AGP_PV_PLUGIN_DIR . 'includes/class-agp-pv-db.php';
require_once AGP_PV_PLUGIN_DIR . 'includes/class-agp-pv-email.php';
require_once AGP_PV_PLUGIN_DIR . 'includes/class-agp-pv-pdf.php';
require_once AGP_PV_PLUGIN_DIR . 'includes/class-agp-pv-admin.php';
require_once AGP_PV_PLUGIN_DIR . 'includes/class-agp-pv-ajax.php';
require_once AGP_PV_PLUGIN_DIR . 'includes/class-agp-pv-plugin.php';

register_activation_hook( __FILE__, array( 'AGP_PV_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AGP_PV_Plugin', 'deactivate' ) );

AGP_PV_Plugin::get_instance();
