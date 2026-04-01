<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! function_exists( 'convert_to_screen' ) ) {
    require_once ABSPATH . 'wp-admin/includes/screen.php';
}

if ( function_exists( 'set_current_screen' ) ) {
    set_current_screen( 'agp-pv_page_agp-pv-submissions' );
}

$table = new AGP_PV_Submissions_Table(
    array(
        'manager_page' => 'agp-pv-submissions',
    )
);
$table->prepare_items();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e( 'Gestor de informes técnicos', 'agrocampo-post-venta' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="agp-pv-observations-standalone">
<main class="agp-pv-observations-wrap agp-pv-reports-wrap">
    <header class="agp-pv-observations-header agp-pv-observations-header--compact">
        <div class="agp-pv-observations-header__main">
            <p class="agp-pv-observations-kicker"><?php esc_html_e( 'Gestor visual principal', 'agrocampo-post-venta' ); ?></p>
            <h1><?php esc_html_e( 'Informes técnicos', 'agrocampo-post-venta' ); ?></h1>
            <p><?php esc_html_e( 'Busca, filtra y ejecuta acciones sobre informes enviados.', 'agrocampo-post-venta' ); ?></p>
        </div>
        <div class="agp-pv-observations-header__actions">
            <a class="button button-secondary" href="<?php echo esc_url( AGP_PV_Plugin::observations_standalone_url() ); ?>"><?php esc_html_e( 'Ir a observaciones', 'agrocampo-post-venta' ); ?></a>
            <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=agp-pv-submissions' ) ); ?>"><?php esc_html_e( 'Abrir respaldo admin', 'agrocampo-post-venta' ); ?></a>
        </div>
    </header>

    <section class="agp-pv-observations-panel">
        <form method="get" action="<?php echo esc_url( AGP_PV_Plugin::reports_standalone_url() ); ?>" class="agp-pv-filters">
            <?php $table->render_filters(); ?>
            <?php $table->search_box( __( 'Buscar en informes', 'agrocampo-post-venta' ), 'agp-pv-reports-search' ); ?>
            <?php $table->display(); ?>
        </form>
    </section>
</main>
<?php wp_footer(); ?>
</body>
</html>
