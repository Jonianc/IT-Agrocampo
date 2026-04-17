<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
}

global $wpdb;

$table_name   = AGP_PV_DB::table_name();
$per_page     = 20;
$current_page = max( 1, absint( $_GET['paged'] ?? 1 ) );
$offset       = ( $current_page - 1 ) * $per_page;

$search      = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
$mail_status = sanitize_key( wp_unslash( $_GET['mail_status'] ?? '' ) );
$pdf_status  = sanitize_key( wp_unslash( $_GET['pdf_status'] ?? '' ) );
$email       = sanitize_text_field( wp_unslash( $_GET['email'] ?? '' ) );
$date_from   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['date_from'] ?? '' ) ) ? (string) $_GET['date_from'] : '';
$date_to     = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['date_to'] ?? '' ) ) ? (string) $_GET['date_to'] : '';
$active_filters = array_filter(
    array(
        's'           => $search,
        'mail_status' => $mail_status,
        'pdf_status'  => $pdf_status,
        'email'       => $email,
        'date_from'   => $date_from,
        'date_to'     => $date_to,
    ),
    static function ( $value ): bool {
        return '' !== (string) $value;
    }
);

$where_clauses = array();
$where_values  = array();

if ( '' !== $search ) {
    $like            = '%' . $wpdb->esc_like( $search ) . '%';
    $where_clauses[] = '(tecnico LIKE %s OR cliente LIKE %s OR email_cliente LIKE %s OR serie LIKE %s OR CAST(id AS CHAR) LIKE %s)';
    array_push( $where_values, $like, $like, $like, $like, $like );
}

if ( in_array( $mail_status, array( 'pending', 'sent', 'failed' ), true ) ) {
    $where_clauses[] = 'mail_status = %s';
    $where_values[]  = $mail_status;
}

if ( in_array( $pdf_status, array( 'pending', 'ready', 'failed' ), true ) ) {
    $where_clauses[] = 'pdf_status = %s';
    $where_values[]  = $pdf_status;
}

if ( '' !== $email ) {
    $where_clauses[] = 'email_cliente LIKE %s';
    $where_values[]  = '%' . $wpdb->esc_like( $email ) . '%';
}

if ( '' !== $date_from ) {
    $where_clauses[] = 'DATE(created_at) >= %s';
    $where_values[]  = $date_from;
}

if ( '' !== $date_to ) {
    $where_clauses[] = 'DATE(created_at) <= %s';
    $where_values[]  = $date_to;
}

$where_sql = '';
if ( ! empty( $where_clauses ) ) {
    $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
}

$count_sql   = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
$total_items = (int) ( empty( $where_values ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $where_values ) ) );
$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );

$list_sql    = "SELECT id, legacy_id, tecnico, cliente, email_cliente, serie, tipo_servicio, mail_status, pdf_status, created_at FROM {$table_name} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
$list_values = array_merge( $where_values, array( $per_page, $offset ) );
$items       = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_values ), ARRAY_A );

$base_url = AGP_PV_Plugin::reports_standalone_url();

$mail_status_labels = array(
    'pending' => __( 'Pendiente', 'agrocampo-post-venta' ),
    'sent'    => __( 'Enviado', 'agrocampo-post-venta' ),
    'failed'  => __( 'Falló', 'agrocampo-post-venta' ),
);

$pdf_status_labels = array(
    'pending' => __( 'Pendiente', 'agrocampo-post-venta' ),
    'ready'   => __( 'Listo', 'agrocampo-post-venta' ),
    'failed'  => __( 'Falló', 'agrocampo-post-venta' ),
);

$service_type_labels = array(
    'one'                => __( 'Factura Cliente', 'agrocampo-post-venta' ),
    'two'                => __( 'Garantía', 'agrocampo-post-venta' ),
    'Interno'            => __( 'Mantención', 'agrocampo-post-venta' ),
    'interno'            => __( 'Mantención', 'agrocampo-post-venta' ),
    'Visita-de-Cortesía' => __( 'Visita de Cortesía', 'agrocampo-post-venta' ),
    'Diagnostico-Técnico' => __( 'Diagnóstico Técnico', 'agrocampo-post-venta' ),
    'Entrega-Técnica'    => __( 'Entrega Técnica', 'agrocampo-post-venta' ),
);

$resolve_service_type_label = static function ( array $item ) use ( $service_type_labels ): string {
    $service_type = (string) ( $item['tipo_servicio'] ?? '' );
    $custom_label = sanitize_text_field( (string) ( $item['tipo_servicio_label'] ?? '' ) );

    if ( '' !== $custom_label ) {
        return $custom_label;
    }

    $mantencion_label = sanitize_text_field( (string) ( $item['tipo_mantencion_label'] ?? '' ) );
    if ( in_array( $service_type, array( 'Interno', 'interno' ), true ) && '' !== $mantencion_label ) {
        return sprintf( __( 'Mantención — %s', 'agrocampo-post-venta' ), $mantencion_label );
    }

    $mapped_label = $service_type_labels[ $service_type ] ?? $service_type;

    return '' !== $mapped_label ? $mapped_label : '—';
};

$reports_notice = sanitize_key( wp_unslash( $_GET['agp_pv_notice'] ?? '' ) );
$reports_notice_message = sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? '' ) );

$reports_notice_map = array(
    'mail_sent'       => array(
        'class'   => 'agp-pv-notice-success',
        'message' => __( 'Correo reenviado correctamente.', 'agrocampo-post-venta' ),
    ),
    'mail_failed'     => array(
        'class'   => 'agp-pv-notice-error',
        'message' => '' !== $reports_notice_message ? $reports_notice_message : __( 'No se pudo reenviar el correo.', 'agrocampo-post-venta' ),
    ),
    'pdf_regenerated' => array(
        'class'   => 'agp-pv-notice-success',
        'message' => __( 'PDF regenerado correctamente.', 'agrocampo-post-venta' ),
    ),
    'pdf_failed'      => array(
        'class'   => 'agp-pv-notice-error',
        'message' => '' !== $reports_notice_message ? $reports_notice_message : __( 'No se pudo regenerar el PDF.', 'agrocampo-post-venta' ),
    ),
);

$active_notice = $reports_notice_map[ $reports_notice ] ?? null;
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
            <h1><?php esc_html_e( 'Informes técnicos', 'agrocampo-post-venta' ); ?></h1>
            <p><?php esc_html_e( 'Consulta informes, aplica filtros y ejecuta acciones rápidas.', 'agrocampo-post-venta' ); ?></p>
        </div>
        <div class="agp-pv-observations-header__actions" role="navigation" aria-label="<?php esc_attr_e( 'Acciones de navegación de informes', 'agrocampo-post-venta' ); ?>">
            <a class="button button-secondary" href="<?php echo esc_url( AGP_PV_Plugin::observations_standalone_url() ); ?>"><?php esc_html_e( 'Ir a observaciones', 'agrocampo-post-venta' ); ?></a>
            <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=agp-pv-submissions' ) ); ?>"><?php esc_html_e( 'Abrir respaldo admin', 'agrocampo-post-venta' ); ?></a>
        </div>
    </header>

    <section class="agp-pv-observations-panel">
        <?php if ( is_array( $active_notice ) ) : ?>
            <div class="agp-pv-notice <?php echo esc_attr( $active_notice['class'] ); ?>"><?php echo esc_html( (string) $active_notice['message'] ); ?></div>
        <?php endif; ?>
        <form method="get" action="<?php echo esc_url( $base_url ); ?>" class="agp-pv-filters">
            <div class="agp-pv-toolbar">
                <div class="agp-pv-filter-row">
                    <label class="agp-pv-field" for="agp-pv-filter-mail"><span class="agp-pv-field__label"><?php esc_html_e( 'Estado correo', 'agrocampo-post-venta' ); ?></span>
                        <select id="agp-pv-filter-mail" name="mail_status">
                            <option value=""><?php esc_html_e( 'Todos', 'agrocampo-post-venta' ); ?></option>
                            <option value="pending" <?php selected( $mail_status, 'pending' ); ?>><?php esc_html_e( 'Pendiente', 'agrocampo-post-venta' ); ?></option>
                            <option value="sent" <?php selected( $mail_status, 'sent' ); ?>><?php esc_html_e( 'Enviado', 'agrocampo-post-venta' ); ?></option>
                            <option value="failed" <?php selected( $mail_status, 'failed' ); ?>><?php esc_html_e( 'Falló', 'agrocampo-post-venta' ); ?></option>
                        </select>
                    </label>

                    <label class="agp-pv-field" for="agp-pv-filter-pdf"><span class="agp-pv-field__label"><?php esc_html_e( 'Estado PDF', 'agrocampo-post-venta' ); ?></span>
                        <select id="agp-pv-filter-pdf" name="pdf_status">
                            <option value=""><?php esc_html_e( 'Todos', 'agrocampo-post-venta' ); ?></option>
                            <option value="pending" <?php selected( $pdf_status, 'pending' ); ?>><?php esc_html_e( 'Pendiente', 'agrocampo-post-venta' ); ?></option>
                            <option value="ready" <?php selected( $pdf_status, 'ready' ); ?>><?php esc_html_e( 'Listo', 'agrocampo-post-venta' ); ?></option>
                            <option value="failed" <?php selected( $pdf_status, 'failed' ); ?>><?php esc_html_e( 'Falló', 'agrocampo-post-venta' ); ?></option>
                        </select>
                    </label>

                    <label class="agp-pv-field agp-pv-field--wide" for="agp-pv-filter-email"><span class="agp-pv-field__label"><?php esc_html_e( 'Correo', 'agrocampo-post-venta' ); ?></span>
                        <input type="text" id="agp-pv-filter-email" name="email" value="<?php echo esc_attr( $email ); ?>" placeholder="cliente@correo.cl">
                    </label>

                    <label class="agp-pv-field agp-pv-field--date" for="agp-pv-filter-date-from"><span class="agp-pv-field__label"><?php esc_html_e( 'Desde', 'agrocampo-post-venta' ); ?></span>
                        <input type="date" id="agp-pv-filter-date-from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
                    </label>

                    <label class="agp-pv-field agp-pv-field--date" for="agp-pv-filter-date-to"><span class="agp-pv-field__label"><?php esc_html_e( 'Hasta', 'agrocampo-post-venta' ); ?></span>
                        <input type="date" id="agp-pv-filter-date-to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
                    </label>

                    <label class="agp-pv-field agp-pv-field--wide" for="agp-pv-search"><span class="agp-pv-field__label"><?php esc_html_e( 'Buscar', 'agrocampo-post-venta' ); ?></span>
                        <input type="search" id="agp-pv-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Técnico, cliente, correo, serie o ID', 'agrocampo-post-venta' ); ?>">
                    </label>

                    <div class="agp-pv-field agp-pv-field--actions">
                        <span class="agp-pv-field__label agp-pv-field__label--ghost"><?php esc_html_e( 'Acciones', 'agrocampo-post-venta' ); ?></span>
                        <div class="agp-pv-filter-actions">
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Aplicar filtros', 'agrocampo-post-venta' ); ?></button>
                            <a class="button button-secondary" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Limpiar', 'agrocampo-post-venta' ); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="agp-pv-reports-kpi">
            <div class="agp-pv-reports-kpi__item">
                <span class="agp-pv-reports-kpi__label"><?php esc_html_e( 'Resultados', 'agrocampo-post-venta' ); ?></span>
                <strong class="agp-pv-reports-kpi__value"><?php echo esc_html( number_format_i18n( $total_items ) ); ?></strong>
            </div>
            <div class="agp-pv-reports-kpi__item">
                <span class="agp-pv-reports-kpi__label"><?php esc_html_e( 'Filtros activos', 'agrocampo-post-venta' ); ?></span>
                <strong class="agp-pv-reports-kpi__value"><?php echo esc_html( number_format_i18n( count( $active_filters ) ) ); ?></strong>
            </div>
        </div>
        <p><strong><?php echo esc_html( sprintf( _n( '%d informe', '%d informes', $total_items, 'agrocampo-post-venta' ), $total_items ) ); ?></strong></p>

        <div class="agp-pv-reports-table-wrap" aria-label="<?php esc_attr_e( 'Tabla de informes', 'agrocampo-post-venta' ); ?>">
            <table class="wp-list-table widefat striped table-view-list">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Técnico', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Cliente', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Correo cliente', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Serie', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Tipo de servicio', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Correo', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'PDF', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Fecha', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Acciones', 'agrocampo-post-venta' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ( empty( $items ) ) : ?>
                    <tr>
                        <td colspan="10">
                            <div class="agp-pv-reports-empty">
                                <p class="agp-pv-reports-empty__title"><?php esc_html_e( 'No hay informes para los filtros seleccionados.', 'agrocampo-post-venta' ); ?></p>
                                <p class="agp-pv-reports-empty__hint"><?php esc_html_e( 'Prueba ajustando el rango de fechas o limpiando filtros para ampliar los resultados.', 'agrocampo-post-venta' ); ?></p>
                                <?php if ( ! empty( $active_filters ) ) : ?>
                                    <p class="agp-pv-reports-empty__actions"><a class="button button-secondary" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Limpiar filtros', 'agrocampo-post-venta' ); ?></a></p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $items as $item ) : ?>
                        <tr>
                            <td><?php echo esc_html( (string) AGP_PV_DB::get_visible_report_id( $item ) ); ?></td>
                            <td><?php echo esc_html( (string) $item['tecnico'] ); ?></td>
                            <td><?php echo esc_html( (string) $item['cliente'] ); ?></td>
                            <td><?php echo esc_html( (string) $item['email_cliente'] ); ?></td>
                            <td><?php echo esc_html( (string) $item['serie'] ); ?></td>
                            <td>
                                <?php echo esc_html( $resolve_service_type_label( $item ) ); ?>
                            </td>
                            <td>
                                <?php
                                $mail_state       = (string) $item['mail_status'];
                                $mail_state_label = $mail_status_labels[ $mail_state ] ?? $mail_state;
                                ?>
                                <span class="agp-pv-status-badge agp-pv-status-badge--mail agp-pv-status-badge--<?php echo esc_attr( sanitize_html_class( $mail_state ) ); ?>">
                                    <?php echo esc_html( $mail_state_label ); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $pdf_state       = (string) $item['pdf_status'];
                                $pdf_state_label = $pdf_status_labels[ $pdf_state ] ?? $pdf_state;
                                ?>
                                <span class="agp-pv-status-badge agp-pv-status-badge--pdf agp-pv-status-badge--<?php echo esc_attr( sanitize_html_class( $pdf_state ) ); ?>">
                                    <?php echo esc_html( $pdf_state_label ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( mysql2date( 'd/m/Y H:i', (string) $item['created_at'] ) ); ?></td>
                            <td>
                                <?php
                                $submission_id = absint( $item['id'] );
                                $redirect_to   = add_query_arg(
                                    array_filter(
                                        array(
                                            's' => $search,
                                            'mail_status' => $mail_status,
                                            'pdf_status' => $pdf_status,
                                            'email' => $email,
                                            'date_from' => $date_from,
                                            'date_to' => $date_to,
                                            'paged' => $current_page > 1 ? $current_page : null,
                                        ),
                                        static function ( $value ) {
                                            return null !== $value && '' !== (string) $value;
                                        }
                                    ),
                                    $base_url
                                );
                                $view_pdf_url  = wp_nonce_url(
                                    admin_url( 'admin.php?page=agp-pv-submissions&view=' . $submission_id ),
                                    'agp_pv_view_pdf_' . $submission_id
                                );
                                $resend_url    = wp_nonce_url(
                                    admin_url(
                                        'admin-post.php?action=agp_pv_resend_email&submission_id=' . $submission_id . '&manager_page=agp-pv-submissions&redirect_to=' . rawurlencode( $redirect_to )
                                    ),
                                    'agp_pv_resend_email_' . $submission_id
                                );
                                $regenerate_url = wp_nonce_url(
                                    admin_url(
                                        'admin-post.php?action=agp_pv_regenerate_pdf&submission_id=' . $submission_id . '&manager_page=agp-pv-submissions&redirect_to=' . rawurlencode( $redirect_to )
                                    ),
                                    'agp_pv_regenerate_pdf_' . $submission_id
                                );
                                ?>
                                <div class="agp-pv-reports-row-actions">
                                    <a class="agp-pv-report-action agp-pv-report-action--primary" href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ver PDF', 'agrocampo-post-venta' ); ?></a>
                                    <a class="agp-pv-report-action" href="<?php echo esc_url( $resend_url ); ?>"><?php esc_html_e( 'Reenviar correo', 'agrocampo-post-venta' ); ?></a>
                                    <a class="agp-pv-report-action" href="<?php echo esc_url( $regenerate_url ); ?>"><?php esc_html_e( 'Regenerar PDF', 'agrocampo-post-venta' ); ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="agp-pv-reports-cards" aria-label="<?php esc_attr_e( 'Listado de informes en tarjetas', 'agrocampo-post-venta' ); ?>">
            <?php if ( empty( $items ) ) : ?>
                <div class="agp-pv-reports-empty agp-pv-reports-empty--cards">
                    <p class="agp-pv-reports-empty__title"><?php esc_html_e( 'No hay informes para los filtros seleccionados.', 'agrocampo-post-venta' ); ?></p>
                    <p class="agp-pv-reports-empty__hint"><?php esc_html_e( 'Prueba ajustando el rango de fechas o limpiando filtros para ampliar los resultados.', 'agrocampo-post-venta' ); ?></p>
                    <?php if ( ! empty( $active_filters ) ) : ?>
                        <p class="agp-pv-reports-empty__actions"><a class="button button-secondary" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Limpiar filtros', 'agrocampo-post-venta' ); ?></a></p>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <?php foreach ( $items as $item ) : ?>
                    <?php
                    $mail_state       = (string) $item['mail_status'];
                    $mail_state_label = $mail_status_labels[ $mail_state ] ?? $mail_state;

                    $pdf_state       = (string) $item['pdf_status'];
                    $pdf_state_label = $pdf_status_labels[ $pdf_state ] ?? $pdf_state;

                    $submission_id = absint( $item['id'] );
                    $redirect_to   = add_query_arg(
                        array_filter(
                            array(
                                's' => $search,
                                'mail_status' => $mail_status,
                                'pdf_status' => $pdf_status,
                                'email' => $email,
                                'date_from' => $date_from,
                                'date_to' => $date_to,
                                'paged' => $current_page > 1 ? $current_page : null,
                            ),
                            static function ( $value ) {
                                return null !== $value && '' !== (string) $value;
                            }
                        ),
                        $base_url
                    );
                    $view_pdf_url  = wp_nonce_url(
                        admin_url( 'admin.php?page=agp-pv-submissions&view=' . $submission_id ),
                        'agp_pv_view_pdf_' . $submission_id
                    );
                    $resend_url    = wp_nonce_url(
                        admin_url(
                            'admin-post.php?action=agp_pv_resend_email&submission_id=' . $submission_id . '&manager_page=agp-pv-submissions&redirect_to=' . rawurlencode( $redirect_to )
                        ),
                        'agp_pv_resend_email_' . $submission_id
                    );
                    $regenerate_url = wp_nonce_url(
                        admin_url(
                            'admin-post.php?action=agp_pv_regenerate_pdf&submission_id=' . $submission_id . '&manager_page=agp-pv-submissions&redirect_to=' . rawurlencode( $redirect_to )
                        ),
                        'agp_pv_regenerate_pdf_' . $submission_id
                    );
                    ?>
                    <article class="agp-pv-report-card">
                        <header class="agp-pv-report-card__header">
                            <span class="agp-pv-report-card__id-label"><?php esc_html_e( 'ID', 'agrocampo-post-venta' ); ?></span>
                            <strong class="agp-pv-report-card__id"><?php echo esc_html( (string) AGP_PV_DB::get_visible_report_id( $item ) ); ?></strong>
                            <span class="agp-pv-report-card__date"><?php echo esc_html( mysql2date( 'd/m/Y H:i', (string) $item['created_at'] ) ); ?></span>
                        </header>
                        <dl class="agp-pv-report-card__meta">
                            <div class="agp-pv-report-card__meta-row">
                                <dt><?php esc_html_e( 'Cliente', 'agrocampo-post-venta' ); ?></dt>
                                <dd><?php echo esc_html( (string) $item['cliente'] ); ?></dd>
                            </div>
                            <div class="agp-pv-report-card__meta-row">
                                <dt><?php esc_html_e( 'Técnico', 'agrocampo-post-venta' ); ?></dt>
                                <dd><?php echo esc_html( (string) $item['tecnico'] ); ?></dd>
                            </div>
                            <div class="agp-pv-report-card__meta-row">
                                <dt><?php esc_html_e( 'Serie', 'agrocampo-post-venta' ); ?></dt>
                                <dd><?php echo esc_html( (string) $item['serie'] ); ?></dd>
                            </div>
                            <div class="agp-pv-report-card__meta-row">
                                <dt><?php esc_html_e( 'Tipo de servicio', 'agrocampo-post-venta' ); ?></dt>
                                <dd><?php echo esc_html( $resolve_service_type_label( $item ) ); ?></dd>
                            </div>
                            <div class="agp-pv-report-card__meta-row agp-pv-report-card__meta-row--status">
                                <dt><?php esc_html_e( 'Estado correo', 'agrocampo-post-venta' ); ?></dt>
                                <dd>
                                    <span class="agp-pv-status-badge agp-pv-status-badge--mail agp-pv-status-badge--<?php echo esc_attr( sanitize_html_class( $mail_state ) ); ?>">
                                        <?php echo esc_html( $mail_state_label ); ?>
                                    </span>
                                </dd>
                            </div>
                            <div class="agp-pv-report-card__meta-row agp-pv-report-card__meta-row--status">
                                <dt><?php esc_html_e( 'Estado PDF', 'agrocampo-post-venta' ); ?></dt>
                                <dd>
                                    <span class="agp-pv-status-badge agp-pv-status-badge--pdf agp-pv-status-badge--<?php echo esc_attr( sanitize_html_class( $pdf_state ) ); ?>">
                                        <?php echo esc_html( $pdf_state_label ); ?>
                                    </span>
                                </dd>
                            </div>
                        </dl>
                        <div class="agp-pv-reports-row-actions agp-pv-reports-row-actions--card">
                            <a class="agp-pv-report-action agp-pv-report-action--primary" href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ver PDF', 'agrocampo-post-venta' ); ?></a>
                            <a class="agp-pv-report-action" href="<?php echo esc_url( $resend_url ); ?>"><?php esc_html_e( 'Reenviar correo', 'agrocampo-post-venta' ); ?></a>
                            <a class="agp-pv-report-action" href="<?php echo esc_url( $regenerate_url ); ?>"><?php esc_html_e( 'Regenerar PDF', 'agrocampo-post-venta' ); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo wp_kses_post(
                        paginate_links(
                            array(
                                'base' => add_query_arg( 'paged', '%#%', $base_url ),
                                'format' => '',
                                'current' => $current_page,
                                'total' => $total_pages,
                                'add_args' => array_filter(
                                    array(
                                        's' => $search,
                                        'mail_status' => $mail_status,
                                        'pdf_status' => $pdf_status,
                                        'email' => $email,
                                        'date_from' => $date_from,
                                        'date_to' => $date_to,
                                    )
                                ),
                                'prev_text' => __( '«', 'agrocampo-post-venta' ),
                                'next_text' => __( '»', 'agrocampo-post-venta' ),
                            )
                        )
                    );
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php if ( is_array( $active_notice ) ) : ?>
    <script>
    (function () {
        if (!window.history || !window.history.replaceState || !window.URL) {
            return;
        }

        var url = new URL(window.location.href);
        if (!url.searchParams.has('agp_pv_notice') && !url.searchParams.has('agp_pv_notice_message')) {
            return;
        }

        url.searchParams.delete('agp_pv_notice');
        url.searchParams.delete('agp_pv_notice_message');
        window.history.replaceState({}, document.title, url.toString());
    }());
    </script>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
