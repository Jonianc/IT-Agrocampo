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
$allowed_service_types = array(
    'one',
    'two',
    'Interno',
    'interno',
    'Visita-de-Cortesía',
    'Diagnostico-Técnico',
    'Entrega-Técnica',
);
$service_type = sanitize_text_field( wp_unslash( $_GET['service_type'] ?? '' ) );
if ( ! in_array( $service_type, $allowed_service_types, true ) ) {
    $service_type = '';
}
$service_type_normalized = in_array( $service_type, array( 'Interno', 'interno' ), true ) ? 'interno' : $service_type;
$email       = sanitize_text_field( wp_unslash( $_GET['email'] ?? '' ) );
$date_from   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['date_from'] ?? '' ) ) ? (string) $_GET['date_from'] : '';
$date_to     = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['date_to'] ?? '' ) ) ? (string) $_GET['date_to'] : '';
$active_filters = array_filter(
    array(
        's'           => $search,
        'mail_status' => $mail_status,
        'pdf_status'  => $pdf_status,
        'service_type' => $service_type,
        'email'       => $email,
        'date_from'   => $date_from,
        'date_to'     => $date_to,
    ),
    static function ( $value ): bool {
        return '' !== (string) $value;
    }
);

$base_filter_args = array(
    's'            => $search,
    'service_type' => $service_type,
    'email'        => $email,
    'date_from'    => $date_from,
    'date_to'      => $date_to,
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

if ( '' !== $service_type_normalized ) {
    if ( 'interno' === $service_type_normalized ) {
        $where_clauses[] = 'tipo_servicio IN (%s, %s)';
        array_push( $where_values, 'Interno', 'interno' );
    } else {
        $where_clauses[] = 'tipo_servicio = %s';
        $where_values[]  = $service_type_normalized;
    }
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

$summary_where_clauses = array();
$summary_where_values  = array();

if ( '' !== $search ) {
    $like                    = '%' . $wpdb->esc_like( $search ) . '%';
    $summary_where_clauses[] = '(tecnico LIKE %s OR cliente LIKE %s OR email_cliente LIKE %s OR serie LIKE %s OR CAST(id AS CHAR) LIKE %s)';
    array_push( $summary_where_values, $like, $like, $like, $like, $like );
}

if ( '' !== $service_type_normalized ) {
    if ( 'interno' === $service_type_normalized ) {
        $summary_where_clauses[] = 'tipo_servicio IN (%s, %s)';
        array_push( $summary_where_values, 'Interno', 'interno' );
    } else {
        $summary_where_clauses[] = 'tipo_servicio = %s';
        $summary_where_values[]  = $service_type_normalized;
    }
}

if ( '' !== $email ) {
    $summary_where_clauses[] = 'email_cliente LIKE %s';
    $summary_where_values[]  = '%' . $wpdb->esc_like( $email ) . '%';
}

if ( '' !== $date_from ) {
    $summary_where_clauses[] = 'DATE(created_at) >= %s';
    $summary_where_values[]  = $date_from;
}

if ( '' !== $date_to ) {
    $summary_where_clauses[] = 'DATE(created_at) <= %s';
    $summary_where_values[]  = $date_to;
}

$summary_where_sql = '';
if ( ! empty( $summary_where_clauses ) ) {
    $summary_where_sql = 'WHERE ' . implode( ' AND ', $summary_where_clauses );
}

$summary_sql = "SELECT
    COUNT(*) AS total_reports,
    SUM(CASE WHEN mail_status = 'pending' THEN 1 ELSE 0 END) AS pending_mail,
    SUM(CASE WHEN pdf_status = 'pending' THEN 1 ELSE 0 END) AS pending_pdf,
    SUM(CASE WHEN pdf_status = 'ready' OR mail_status = 'sent' THEN 1 ELSE 0 END) AS ready_or_sent
    FROM {$table_name} {$summary_where_sql}";
$summary_row = (array) ( empty( $summary_where_values )
    ? $wpdb->get_row( $summary_sql, ARRAY_A )
    : $wpdb->get_row( $wpdb->prepare( $summary_sql, $summary_where_values ), ARRAY_A ) );

$summary_total_reports = isset( $summary_row['total_reports'] ) ? (int) $summary_row['total_reports'] : 0;
$summary_pending_mail  = isset( $summary_row['pending_mail'] ) ? (int) $summary_row['pending_mail'] : 0;
$summary_pending_pdf   = isset( $summary_row['pending_pdf'] ) ? (int) $summary_row['pending_pdf'] : 0;
$summary_ready_or_sent = isset( $summary_row['ready_or_sent'] ) ? (int) $summary_row['ready_or_sent'] : 0;

$count_sql   = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
$total_items = (int) ( empty( $where_values ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $where_values ) ) );
$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );

$list_sql    = "SELECT id, legacy_id, tecnico, cliente, email_cliente, serie, tipo_servicio, tipo_servicio_label, tipo_mantencion_label, mail_status, pdf_status, created_at FROM {$table_name} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
$list_values = array_merge( $where_values, array( $per_page, $offset ) );
$items       = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_values ), ARRAY_A );

$base_url = AGP_PV_Plugin::reports_standalone_url();
$build_reports_url = static function ( array $args = array() ) use ( $base_url, $base_filter_args, $mail_status, $pdf_status ) {
    $query_args = array_filter(
        array(
            's'            => $base_filter_args['s'],
            'mail_status'  => $mail_status,
            'pdf_status'   => $pdf_status,
            'service_type' => $base_filter_args['service_type'],
            'email'        => $base_filter_args['email'],
            'date_from'    => $base_filter_args['date_from'],
            'date_to'      => $base_filter_args['date_to'],
        ),
        static function ( $value ): bool {
            return '' !== (string) $value;
        }
    );

    foreach ( $args as $key => $value ) {
        if ( null === $value || '' === $value ) {
            unset( $query_args[ $key ] );
            continue;
        }

        $query_args[ $key ] = $value;
    }

    return empty( $query_args ) ? $base_url : add_query_arg( $query_args, $base_url );
};

$status_tabs = array(
    array(
        'label' => __( 'Todos', 'agrocampo-post-venta' ),
        'args'  => array( 'mail_status' => null, 'pdf_status' => null, 'paged' => null ),
        'active' => '' === $mail_status && '' === $pdf_status,
        'class' => 'is-total',
    ),
    array(
        'label' => __( 'Correo pendiente', 'agrocampo-post-venta' ),
        'args'  => array( 'mail_status' => 'pending', 'pdf_status' => null, 'paged' => null ),
        'active' => 'pending' === $mail_status && '' === $pdf_status,
        'class' => 'is-pending',
    ),
    array(
        'label' => __( 'Correo enviado', 'agrocampo-post-venta' ),
        'args'  => array( 'mail_status' => 'sent', 'pdf_status' => null, 'paged' => null ),
        'active' => 'sent' === $mail_status && '' === $pdf_status,
        'class' => 'is-reviewed',
    ),
    array(
        'label' => __( 'Correo fallido', 'agrocampo-post-venta' ),
        'args'  => array( 'mail_status' => 'failed', 'pdf_status' => null, 'paged' => null ),
        'active' => 'failed' === $mail_status && '' === $pdf_status,
        'class' => 'is-overdue',
    ),
    array(
        'label' => __( 'PDF pendiente', 'agrocampo-post-venta' ),
        'args'  => array( 'mail_status' => null, 'pdf_status' => 'pending', 'paged' => null ),
        'active' => '' === $mail_status && 'pending' === $pdf_status,
        'class' => 'is-pending',
    ),
    array(
        'label' => __( 'PDF listo', 'agrocampo-post-venta' ),
        'args'  => array( 'mail_status' => null, 'pdf_status' => 'ready', 'paged' => null ),
        'active' => '' === $mail_status && 'ready' === $pdf_status,
        'class' => 'is-reviewed',
    ),
    array(
        'label' => __( 'PDF fallido', 'agrocampo-post-venta' ),
        'args'  => array( 'mail_status' => null, 'pdf_status' => 'failed', 'paged' => null ),
        'active' => '' === $mail_status && 'failed' === $pdf_status,
        'class' => 'is-overdue',
    ),
);

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
    <?php
    if ( '' !== $date_from && '' !== $date_to ) {
        $reports_context_text = sprintf(
            __( 'Período activo: %1$s a %2$s · Filtros activos: %3$d', 'agrocampo-post-venta' ),
            wp_date( 'd/m/Y', strtotime( $date_from ) ),
            wp_date( 'd/m/Y', strtotime( $date_to ) ),
            count( $active_filters )
        );
    } elseif ( '' !== $date_from ) {
        $reports_context_text = sprintf(
            __( 'Período activo: desde %1$s · Filtros activos: %2$d', 'agrocampo-post-venta' ),
            wp_date( 'd/m/Y', strtotime( $date_from ) ),
            count( $active_filters )
        );
    } elseif ( '' !== $date_to ) {
        $reports_context_text = sprintf(
            __( 'Período activo: hasta %1$s · Filtros activos: %2$d', 'agrocampo-post-venta' ),
            wp_date( 'd/m/Y', strtotime( $date_to ) ),
            count( $active_filters )
        );
    } else {
        $reports_context_text = sprintf(
            __( 'Período activo: todos los registros · Filtros activos: %d', 'agrocampo-post-venta' ),
            count( $active_filters )
        );
    }
    ?>
    <section class="agp-pv-shell-header">
        <div class="agp-pv-shell-header__title">
            <span class="agp-pv-shell-header__eyebrow"><?php esc_html_e( 'Post venta', 'agrocampo-post-venta' ); ?></span>
            <h1><?php esc_html_e( 'Informes técnicos', 'agrocampo-post-venta' ); ?></h1>
            <p class="agp-pv-shell-header__hint"><?php echo esc_html( $reports_context_text ); ?></p>
        </div>

        <div class="agp-pv-shell-header__aside">
            <div class="agp-pv-summary-grid" aria-label="<?php esc_attr_e( 'Resumen de informes', 'agrocampo-post-venta' ); ?>">
                <article class="agp-pv-summary-card is-total">
                    <span class="agp-pv-summary-card__label"><?php esc_html_e( 'Total informes', 'agrocampo-post-venta' ); ?></span>
                    <strong class="agp-pv-summary-card__value"><?php echo esc_html( (string) $summary_total_reports ); ?></strong>
                </article>
                <article class="agp-pv-summary-card is-pending">
                    <span class="agp-pv-summary-card__label"><?php esc_html_e( 'Correos pendientes', 'agrocampo-post-venta' ); ?></span>
                    <strong class="agp-pv-summary-card__value"><?php echo esc_html( (string) $summary_pending_mail ); ?></strong>
                </article>
                <article class="agp-pv-summary-card is-pending">
                    <span class="agp-pv-summary-card__label"><?php esc_html_e( 'PDFs pendientes', 'agrocampo-post-venta' ); ?></span>
                    <strong class="agp-pv-summary-card__value"><?php echo esc_html( (string) $summary_pending_pdf ); ?></strong>
                </article>
                <article class="agp-pv-summary-card is-reviewed">
                    <span class="agp-pv-summary-card__label"><?php esc_html_e( 'PDFs listos o correos enviados', 'agrocampo-post-venta' ); ?></span>
                    <strong class="agp-pv-summary-card__value"><?php echo esc_html( (string) $summary_ready_or_sent ); ?></strong>
                </article>
            </div>
            <a class="agp-pv-tertiary-link" href="<?php echo esc_url( AGP_PV_Plugin::observations_standalone_url() ); ?>"><?php esc_html_e( 'Ir a observaciones', 'agrocampo-post-venta' ); ?></a>
        </div>
    </section>

    <section class="agp-pv-panel agp-pv-filters-panel agp-pv-reports-filters-panel">
        <?php if ( is_array( $active_notice ) ) : ?>
            <div class="agp-pv-notice <?php echo esc_attr( $active_notice['class'] ); ?>"><?php echo esc_html( (string) $active_notice['message'] ); ?></div>
        <?php endif; ?>
        <div class="agp-pv-panel__heading"><?php esc_html_e( 'Filtros', 'agrocampo-post-venta' ); ?></div>
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

                    <label class="agp-pv-field" for="agp-pv-filter-service-type"><span class="agp-pv-field__label"><?php esc_html_e( 'Tipo de servicio', 'agrocampo-post-venta' ); ?></span>
                        <select id="agp-pv-filter-service-type" name="service_type">
                            <option value=""><?php esc_html_e( 'Todos', 'agrocampo-post-venta' ); ?></option>
                            <option value="one" <?php selected( $service_type, 'one' ); ?>><?php esc_html_e( 'Factura Cliente', 'agrocampo-post-venta' ); ?></option>
                            <option value="two" <?php selected( $service_type, 'two' ); ?>><?php esc_html_e( 'Garantía', 'agrocampo-post-venta' ); ?></option>
                            <option value="interno" <?php selected( $service_type_normalized, 'interno' ); ?>><?php esc_html_e( 'Mantención', 'agrocampo-post-venta' ); ?></option>
                            <option value="Visita-de-Cortesía" <?php selected( $service_type, 'Visita-de-Cortesía' ); ?>><?php esc_html_e( 'Visita de Cortesía', 'agrocampo-post-venta' ); ?></option>
                            <option value="Diagnostico-Técnico" <?php selected( $service_type, 'Diagnostico-Técnico' ); ?>><?php esc_html_e( 'Diagnóstico Técnico', 'agrocampo-post-venta' ); ?></option>
                            <option value="Entrega-Técnica" <?php selected( $service_type, 'Entrega-Técnica' ); ?>><?php esc_html_e( 'Entrega Técnica', 'agrocampo-post-venta' ); ?></option>
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
    </section>

    <nav class="agp-pv-status-tabs" aria-label="<?php esc_attr_e( 'Filtros rápidos por estado de informes', 'agrocampo-post-venta' ); ?>">
        <?php foreach ( $status_tabs as $tab ) : ?>
            <?php
            $tab_classes = array( 'agp-pv-status-tab', $tab['class'] );
            if ( ! empty( $tab['active'] ) ) {
                $tab_classes[] = 'is-active';
            }
            ?>
            <a class="<?php echo esc_attr( implode( ' ', $tab_classes ) ); ?>" href="<?php echo esc_url( $build_reports_url( $tab['args'] ) ); ?>">
                <span><?php echo esc_html( $tab['label'] ); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="agp-pv-panel agp-pv-table-panel">
        <div class="agp-pv-observations-table-scroll" role="region" aria-label="<?php esc_attr_e( 'Tabla de informes', 'agrocampo-post-venta' ); ?>" tabindex="0">
            <table class="agp-pv-observations-table agp-pv-standalone-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Cliente', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Técnico', 'agrocampo-post-venta' ); ?></th>
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
                        <td colspan="9" class="agp-pv-empty-cell">
                            <div class="agp-pv-empty-state">
                                <strong><?php esc_html_e( 'No hay informes para los filtros seleccionados.', 'agrocampo-post-venta' ); ?></strong>
                                <span><?php esc_html_e( 'Prueba ajustando el rango de fechas o limpiando filtros para ampliar los resultados.', 'agrocampo-post-venta' ); ?></span>
                                <?php if ( ! empty( $active_filters ) ) : ?>
                                    <span><a class="agp-pv-secondary-link" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Limpiar filtros', 'agrocampo-post-venta' ); ?></a></span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $items as $item ) : ?>
                        <tr>
                            <td data-label="<?php esc_attr_e( 'ID', 'agrocampo-post-venta' ); ?>"><span class="agp-pv-id-chip">#<?php echo esc_html( (string) AGP_PV_DB::get_visible_report_id( $item ) ); ?></span></td>
                            <td data-label="<?php esc_attr_e( 'Cliente', 'agrocampo-post-venta' ); ?>"><?php echo esc_html( (string) $item['cliente'] ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Técnico', 'agrocampo-post-venta' ); ?>"><?php echo esc_html( (string) $item['tecnico'] ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Serie', 'agrocampo-post-venta' ); ?>"><?php echo esc_html( (string) $item['serie'] ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Tipo de servicio', 'agrocampo-post-venta' ); ?>">
                                <?php echo esc_html( $resolve_service_type_label( $item ) ); ?>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Correo', 'agrocampo-post-venta' ); ?>">
                                <?php
                                $mail_state       = (string) $item['mail_status'];
                                $mail_state_label = $mail_status_labels[ $mail_state ] ?? $mail_state;
                                ?>
                                <span class="agp-pv-status-badge agp-pv-status-badge--mail agp-pv-status-badge--<?php echo esc_attr( sanitize_html_class( $mail_state ) ); ?>">
                                    <?php echo esc_html( $mail_state_label ); ?>
                                </span>
                            </td>
                            <td data-label="<?php esc_attr_e( 'PDF', 'agrocampo-post-venta' ); ?>">
                                <?php
                                $pdf_state       = (string) $item['pdf_status'];
                                $pdf_state_label = $pdf_status_labels[ $pdf_state ] ?? $pdf_state;
                                ?>
                                <span class="agp-pv-status-badge agp-pv-status-badge--pdf agp-pv-status-badge--<?php echo esc_attr( sanitize_html_class( $pdf_state ) ); ?>">
                                    <?php echo esc_html( $pdf_state_label ); ?>
                                </span>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Fecha', 'agrocampo-post-venta' ); ?>"><?php echo esc_html( mysql2date( 'd/m/Y H:i', (string) $item['created_at'] ) ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Acciones', 'agrocampo-post-venta' ); ?>">
                                <?php
                                $submission_id = absint( $item['id'] );
                                $redirect_to   = $build_reports_url(
                                    array(
                                        'paged' => $current_page > 1 ? $current_page : null,
                                    )
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
                    $redirect_to   = $build_reports_url(
                        array(
                            'paged' => $current_page > 1 ? $current_page : null,
                        )
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

        <div class="agp-pv-table-footer">
            <div class="agp-pv-table-footer__meta">
                <?php
                if ( $total_items > 0 ) {
                    $from = $offset + 1;
                    $to   = min( $offset + $per_page, $total_items );
                    printf(
                        /* translators: 1: first item number, 2: last item number, 3: total items. */
                        esc_html__( 'Mostrando %1$s a %2$s de %3$s informes', 'agrocampo-post-venta' ),
                        esc_html( (string) $from ),
                        esc_html( (string) $to ),
                        esc_html( (string) $total_items )
                    );
                } else {
                    esc_html_e( 'Sin resultados.', 'agrocampo-post-venta' );
                }
                ?>
            </div>

            <?php if ( $total_pages > 1 ) : ?>
                <nav class="agp-pv-pagination" aria-label="<?php esc_attr_e( 'Paginación', 'agrocampo-post-venta' ); ?>">
                    <?php if ( $current_page > 1 ) : ?>
                        <a class="agp-pv-page-link is-nav" href="<?php echo esc_url( $build_reports_url( array( 'paged' => $current_page - 1 ) ) ); ?>"><?php esc_html_e( 'Anterior', 'agrocampo-post-venta' ); ?></a>
                    <?php endif; ?>

                    <?php
                    $start_page = max( 1, $current_page - 2 );
                    $end_page   = min( $total_pages, $current_page + 2 );
                    if ( $start_page > 1 ) {
                        echo '<a class="agp-pv-page-link" href="' . esc_url( $build_reports_url( array( 'paged' => 1 ) ) ) . '">1</a>';
                        if ( $start_page > 2 ) {
                            echo '<span class="agp-pv-page-dots">…</span>';
                        }
                    }

                    for ( $page = $start_page; $page <= $end_page; $page++ ) {
                        $page_classes = 'agp-pv-page-link';
                        if ( $page === $current_page ) {
                            $page_classes .= ' is-current';
                        }
                        echo '<a class="' . esc_attr( $page_classes ) . '" href="' . esc_url( $build_reports_url( array( 'paged' => $page ) ) ) . '">' . esc_html( (string) $page ) . '</a>';
                    }

                    if ( $end_page < $total_pages ) {
                        if ( $end_page < $total_pages - 1 ) {
                            echo '<span class="agp-pv-page-dots">…</span>';
                        }
                        echo '<a class="agp-pv-page-link" href="' . esc_url( $build_reports_url( array( 'paged' => $total_pages ) ) ) . '">' . esc_html( (string) $total_pages ) . '</a>';
                    }
                    ?>

                    <?php if ( $current_page < $total_pages ) : ?>
                        <a class="agp-pv-page-link is-nav" href="<?php echo esc_url( $build_reports_url( array( 'paged' => $current_page + 1 ) ) ); ?>"><?php esc_html_e( 'Siguiente', 'agrocampo-post-venta' ); ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
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
