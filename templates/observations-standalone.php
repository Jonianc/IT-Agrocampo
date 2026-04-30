<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table = AGP_PV_DB::table_name();
$public_access_context = AGP_PV_Plugin::get_public_observations_request_access_context();
$is_public_read_only = ! empty( $public_access_context['is_public_read_only'] );

$search            = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$review_status_raw = isset( $_GET['review_status'] ) ? sanitize_key( wp_unslash( $_GET['review_status'] ) ) : '';
$review_status     = '' !== $review_status_raw ? AGP_PV_Plugin::normalize_observation_review_filter( $review_status_raw ) : '';
$current_page      = isset( $_GET['pg'] ) ? max( 1, absint( wp_unslash( $_GET['pg'] ) ) ) : 1;
$per_page          = 20;
$window_days       = AGP_PV_Plugin::get_observations_report_window_days();
$overdue_days      = AGP_PV_Plugin::get_observations_overdue_days();
$overdue_cutoff    = AGP_PV_Plugin::get_observations_overdue_cutoff_mysql();

$sanitize_date = static function ( $value ): string {
    $value = is_string( $value ) ? trim( $value ) : '';

    if ( '' === $value || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat( 'Y-m-d', $value );

    return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
};

$days = isset( $_GET['days'] ) ? absint( wp_unslash( $_GET['days'] ) ) : $window_days;
if ( $days < 1 ) {
    $days = $window_days;
}
$days = min( 3650, $days );

$date_from    = isset( $_GET['from'] ) ? $sanitize_date( wp_unslash( $_GET['from'] ) ) : '';
$date_to      = isset( $_GET['to'] ) ? $sanitize_date( wp_unslash( $_GET['to'] ) ) : '';
$range_active = '' !== $date_from || '' !== $date_to;

$build_period_clauses = static function ( string $column ) use ( $range_active, $date_from, $date_to, $days ): array {
    if ( $range_active ) {
        $clauses = array();
        $values  = array();

        if ( '' !== $date_from ) {
            $clauses[] = "{$column} >= %s";
            $values[]  = $date_from . ' 00:00:00';
        }

        if ( '' !== $date_to ) {
            $clauses[] = "{$column} <= %s";
            $values[]  = $date_to . ' 23:59:59';
        }

        return array( $clauses, $values );
    }

    return array(
        array( "{$column} >= %s" ),
        array( current_datetime()->modify( '-' . $days . ' days' )->format( 'Y-m-d H:i:s' ) ),
    );
};

list( $created_period_clauses, $created_period_values )   = $build_period_clauses( 'created_at' );
list( $reviewed_period_clauses, $reviewed_period_values ) = $build_period_clauses( 'reviewed_at' );

$created_period_sql  = implode( ' AND ', $created_period_clauses );
$reviewed_period_sql = implode( ' AND ', $reviewed_period_clauses );

$pending_sql        = AGP_PV_Plugin::effective_pending_observation_sql();
$strict_pending_sql = AGP_PV_Plugin::strict_pending_observation_sql();
$reviewed_sql       = AGP_PV_Plugin::reviewed_observation_sql();

$all_period_sql      = "(({$pending_sql}) AND ({$created_period_sql})) OR (({$reviewed_sql}) AND ({$reviewed_period_sql}))";
$pending_period_sql  = "({$pending_sql}) AND ({$created_period_sql})";
$strict_period_sql   = "({$strict_pending_sql}) AND ({$created_period_sql})";
$reviewed_filter_sql = "({$reviewed_sql}) AND ({$reviewed_period_sql})";
$overdue_period_sql  = "({$pending_period_sql}) AND created_at <= %s";

$common_clauses = AGP_PV_Plugin::observation_presence_sql_clauses();
$common_values  = array();

if ( '' !== $search ) {
    $like             = '%' . $wpdb->esc_like( $search ) . '%';
    $common_clauses[] = '(tecnico LIKE %s OR cliente LIKE %s OR observaciones LIKE %s OR serie LIKE %s)';
    array_push( $common_values, $like, $like, $like, $like );
}

$common_where = 'WHERE ' . implode( ' AND ', $common_clauses );

$filtered_clauses = $common_clauses;
$filtered_values  = $common_values;

if ( 'not_reviewed' === $review_status ) {
    $filtered_clauses[] = $pending_period_sql;
    $filtered_values    = array_merge( $filtered_values, $created_period_values );
} elseif ( 'strict_pending_review' === $review_status ) {
    $filtered_clauses[] = $strict_period_sql;
    $filtered_values    = array_merge( $filtered_values, $created_period_values );
} elseif ( 'reviewed' === $review_status ) {
    $filtered_clauses[] = $reviewed_filter_sql;
    $filtered_values    = array_merge( $filtered_values, $reviewed_period_values );
} elseif ( 'overdue_pending' === $review_status ) {
    $filtered_clauses[] = $overdue_period_sql;
    $filtered_values    = array_merge( $filtered_values, $created_period_values, array( $overdue_cutoff ) );
} else {
    $filtered_clauses[] = $all_period_sql;
    $filtered_values    = array_merge( $filtered_values, $created_period_values, $reviewed_period_values );
}

$filtered_where = 'WHERE ' . implode( ' AND ', $filtered_clauses );

$summary_sql = "
    SELECT
        SUM(CASE WHEN ({$all_period_sql}) THEN 1 ELSE 0 END) AS total_count,
        SUM(CASE WHEN ({$pending_period_sql}) THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN ({$reviewed_filter_sql}) THEN 1 ELSE 0 END) AS reviewed_count,
        SUM(CASE WHEN ({$overdue_period_sql}) THEN 1 ELSE 0 END) AS overdue_count
    FROM {$table}
    {$common_where}
";
$summary_values = array_merge(
    $created_period_values,
    $reviewed_period_values,
    $created_period_values,
    $reviewed_period_values,
    $created_period_values,
    array( $overdue_cutoff ),
    $common_values
);
$summary_query  = $wpdb->prepare( $summary_sql, $summary_values );
$summary        = (array) $wpdb->get_row( $summary_query, ARRAY_A );

$total_count    = isset( $summary['total_count'] ) ? (int) $summary['total_count'] : 0;
$pending_count  = isset( $summary['pending_count'] ) ? (int) $summary['pending_count'] : 0;
$reviewed_count = isset( $summary['reviewed_count'] ) ? (int) $summary['reviewed_count'] : 0;
$overdue_count  = isset( $summary['overdue_count'] ) ? (int) $summary['overdue_count'] : 0;

$count_sql   = "SELECT COUNT(*) FROM {$table} {$filtered_where}";
$count_query = $wpdb->prepare( $count_sql, $filtered_values );
$row_count   = (int) $wpdb->get_var( $count_query );
$total_pages = max( 1, (int) ceil( $row_count / $per_page ) );
$current_page = min( $current_page, $total_pages );
$offset = ( $current_page - 1 ) * $per_page;

$rows_sql = "
    SELECT id, legacy_id, tecnico, cliente, observaciones, review_status, reviewed_by, reviewed_at, created_at
    FROM {$table}
    {$filtered_where}
    ORDER BY (legacy_id > 0) DESC, legacy_id DESC, id DESC
    LIMIT %d OFFSET %d
";
$rows_values = array_merge( $filtered_values, array( $per_page, $offset ) );
$rows_query  = $wpdb->prepare( $rows_sql, $rows_values );
$rows        = $wpdb->get_results( $rows_query, ARRAY_A );

$notice = isset( $_GET['agp_pv_notice'] ) ? sanitize_key( wp_unslash( $_GET['agp_pv_notice'] ) ) : '';

$status_tabs = array(
    ''                => array(
        'label' => __( 'Todas', 'agrocampo-post-venta' ),
        'count' => $total_count,
    ),
    'not_reviewed'    => array(
        'label' => __( 'Pendientes', 'agrocampo-post-venta' ),
        'count' => $pending_count,
    ),
    'reviewed'        => array(
        'label' => __( 'Resueltas', 'agrocampo-post-venta' ),
        'count' => $reviewed_count,
    ),
    'overdue_pending' => array(
        'label' => __( 'Vencidas', 'agrocampo-post-venta' ),
        'count' => $overdue_count,
    ),
);

$base_url = AGP_PV_Plugin::observations_standalone_url();

$build_url = static function ( array $args = array() ) use ( $base_url, $search, $review_status, $review_status_raw, $days, $date_from, $date_to ) {
    $query_args = array();

    if ( '' !== $search ) {
        $query_args['s'] = $search;
    }

    if ( '' !== $review_status ) {
        $query_args['review_status'] = $review_status;
    } elseif ( 'pending_review' === $review_status_raw ) {
        $query_args['review_status'] = 'pending_review';
    }

    if ( $days > 0 ) {
        $query_args['days'] = $days;
    }

    if ( '' !== $date_from ) {
        $query_args['from'] = $date_from;
    }

    if ( '' !== $date_to ) {
        $query_args['to'] = $date_to;
    }

    foreach ( $args as $key => $value ) {
        if ( null === $value || '' === $value ) {
            unset( $query_args[ $key ] );
            continue;
        }

        $query_args[ $key ] = $value;
    }

    return empty( $query_args ) ? $base_url : add_query_arg( $query_args, $base_url );
};

$current_view_url = $build_url(
    array(
        'pg'              => $current_page > 1 ? $current_page : null,
        'agp_pv_notice'   => null,
    )
);

$export_query_args = array(
    'action'        => 'agp_pv_export_observations_report',
    'export_source' => 'standalone',
);

if ( '' !== $search ) {
    $export_query_args['s'] = $search;
}

if ( '' !== $review_status ) {
    $export_query_args['review_status'] = $review_status;
} elseif ( 'pending_review' === $review_status_raw ) {
    $export_query_args['review_status'] = 'pending_review';
}

if ( $days > 0 ) {
    $export_query_args['days'] = $days;
}

if ( '' !== $date_from ) {
    $export_query_args['from'] = $date_from;
}

if ( '' !== $date_to ) {
    $export_query_args['to'] = $date_to;
}

$export_report_url = wp_nonce_url( add_query_arg( $export_query_args, admin_url( 'admin-post.php' ) ), 'agp_pv_export_observations_report' );

$now               = current_datetime();
$today_date        = $now->format( 'Y-m-d' );
$this_month_start  = $now->modify( 'first day of this month' )->format( 'Y-m-d' );
$last_month_start  = $now->modify( 'first day of last month' )->format( 'Y-m-d' );
$last_month_end    = $now->modify( 'last day of last month' )->format( 'Y-m-d' );

$current_preset = 'custom';
if ( $range_active ) {
    if ( $date_from === $today_date && $date_to === $today_date ) {
        $current_preset = 'today';
    } elseif ( $date_from === $this_month_start && $date_to === $today_date ) {
        $current_preset = 'this_month';
    } elseif ( $date_from === $last_month_start && $date_to === $last_month_end ) {
        $current_preset = 'last_month';
    }
} else {
    if ( 7 === $days ) {
        $current_preset = '7';
    } elseif ( 15 === $days ) {
        $current_preset = '15';
    } elseif ( 30 === $days ) {
        $current_preset = '30';
    }
}

$preset_links = array(
    'today'      => array(
        'label' => __( 'Hoy', 'agrocampo-post-venta' ),
        'args'  => array( 'from' => $today_date, 'to' => $today_date, 'pg' => null ),
    ),
    '7'          => array(
        'label' => __( '7 días', 'agrocampo-post-venta' ),
        'args'  => array( 'days' => 7, 'from' => null, 'to' => null, 'pg' => null ),
    ),
    '15'         => array(
        'label' => __( '15 días', 'agrocampo-post-venta' ),
        'args'  => array( 'days' => 15, 'from' => null, 'to' => null, 'pg' => null ),
    ),
    '30'         => array(
        'label' => __( '30 días', 'agrocampo-post-venta' ),
        'args'  => array( 'days' => 30, 'from' => null, 'to' => null, 'pg' => null ),
    ),
    'this_month' => array(
        'label' => __( 'Este mes', 'agrocampo-post-venta' ),
        'args'  => array( 'from' => $this_month_start, 'to' => $today_date, 'pg' => null ),
    ),
    'last_month' => array(
        'label' => __( 'Mes pasado', 'agrocampo-post-venta' ),
        'args'  => array( 'from' => $last_month_start, 'to' => $last_month_end, 'pg' => null ),
    ),
);

if ( $range_active ) {
    if ( '' !== $date_from && '' !== $date_to ) {
        $period_text = sprintf(
            /* translators: 1: from date, 2: to date. */
            __( 'Período activo: %1$s a %2$s. Pendientes/Vencidas filtran por creación y Resueltas por fecha de revisión.', 'agrocampo-post-venta' ),
            wp_date( 'd/m/Y', strtotime( $date_from ) ),
            wp_date( 'd/m/Y', strtotime( $date_to ) )
        );
    } elseif ( '' !== $date_from ) {
        $period_text = sprintf(
            __( 'Período activo: desde %1$s. Pendientes/Vencidas filtran por creación y Resueltas por fecha de revisión.', 'agrocampo-post-venta' ),
            wp_date( 'd/m/Y', strtotime( $date_from ) )
        );
    } else {
        $period_text = sprintf(
            __( 'Período activo: hasta %1$s. Pendientes/Vencidas filtran por creación y Resueltas por fecha de revisión.', 'agrocampo-post-venta' ),
            wp_date( 'd/m/Y', strtotime( $date_to ) )
        );
    }
} else {
    $period_text = sprintf(
        __( 'Período activo: últimos %1$d días. Pendientes/Vencidas filtran por creación y Resueltas por fecha de revisión. Se consideran vencidas desde %2$d días.', 'agrocampo-post-venta' ),
        $days,
        $overdue_days
    );
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ( $is_public_read_only ) : ?>
        <meta name="robots" content="noindex,nofollow">
    <?php endif; ?>
    <?php wp_head(); ?>
</head>
<body class="agp-pv-observations-standalone">
<main class="agp-pv-observations-wrap">
    <?php if ( $is_public_read_only ) : ?>
        <?php
        $public_notice = __( 'Acceso temporal público activo — solo lectura.', 'agrocampo-post-venta' );
        if ( '' !== (string) $public_access_context['expires_in_human'] ) {
            $public_notice .= ' ' . sprintf( __( 'Vence en %s.', 'agrocampo-post-venta' ), (string) $public_access_context['expires_in_human'] );
        }
        ?>
        <div class="agp-pv-notice agp-pv-notice-public"><?php echo esc_html( $public_notice ); ?></div>
    <?php endif; ?>

    <section class="agp-pv-shell-header">
        <div class="agp-pv-shell-header__title">
            <span class="agp-pv-shell-header__eyebrow"><?php esc_html_e( 'Post venta', 'agrocampo-post-venta' ); ?></span>
            <h1><?php esc_html_e( 'Observaciones', 'agrocampo-post-venta' ); ?></h1>
            <p class="agp-pv-shell-header__hint"><?php echo esc_html( $period_text ); ?></p>
        </div>

        <div class="agp-pv-shell-header__aside">
            <div class="agp-pv-summary-grid" aria-label="<?php esc_attr_e( 'Resumen de observaciones', 'agrocampo-post-venta' ); ?>">
                <article class="agp-pv-summary-card is-total">
                    <span class="agp-pv-summary-card__label"><?php esc_html_e( 'Total', 'agrocampo-post-venta' ); ?></span>
                    <strong class="agp-pv-summary-card__value"><?php echo esc_html( (string) $total_count ); ?></strong>
                </article>
                <article class="agp-pv-summary-card is-pending">
                    <span class="agp-pv-summary-card__label"><?php esc_html_e( 'Pendientes', 'agrocampo-post-venta' ); ?></span>
                    <strong class="agp-pv-summary-card__value"><?php echo esc_html( (string) $pending_count ); ?></strong>
                </article>
                <article class="agp-pv-summary-card is-reviewed">
                    <span class="agp-pv-summary-card__label"><?php esc_html_e( 'Resueltas', 'agrocampo-post-venta' ); ?></span>
                    <strong class="agp-pv-summary-card__value"><?php echo esc_html( (string) $reviewed_count ); ?></strong>
                </article>
                <article class="agp-pv-summary-card is-overdue">
                    <span class="agp-pv-summary-card__label"><?php esc_html_e( 'Vencidas', 'agrocampo-post-venta' ); ?></span>
                    <strong class="agp-pv-summary-card__value"><?php echo esc_html( (string) $overdue_count ); ?></strong>
                </article>
            </div>
            <?php if ( ! $is_public_read_only ) : ?>
                <a class="agp-pv-tertiary-link" href="<?php echo esc_url( AGP_PV_Plugin::reports_standalone_url() ); ?>"><?php esc_html_e( 'Ir a informes', 'agrocampo-post-venta' ); ?></a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ( 'review_marked' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-success"><?php esc_html_e( 'Informe marcado como revisado.', 'agrocampo-post-venta' ); ?></div>
    <?php elseif ( 'review_unmarked' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-success"><?php esc_html_e( 'La observación volvió a pendiente.', 'agrocampo-post-venta' ); ?></div>
    <?php elseif ( 'review_failed' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-error"><?php esc_html_e( 'No se pudo actualizar el estado de revisión.', 'agrocampo-post-venta' ); ?></div>
    <?php elseif ( 'review_unmark_failed' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-error"><?php esc_html_e( 'No se pudo actualizar la observación.', 'agrocampo-post-venta' ); ?></div>
    <?php elseif ( 'observation_appended' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-success"><?php esc_html_e( 'Observación agregada correctamente al informe revisado.', 'agrocampo-post-venta' ); ?></div>
    <?php elseif ( 'observation_append_failed' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-error"><?php esc_html_e( 'No se pudo agregar la observación.', 'agrocampo-post-venta' ); ?></div>
    <?php elseif ( 'export_observations_failed' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-error"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['agp_pv_notice_message'] ?? __( 'No se pudo generar el reporte XLSX.', 'agrocampo-post-venta' ) ) ) ); ?></div>
    <?php endif; ?>

    <section class="agp-pv-panel agp-pv-filters-panel">
        <div class="agp-pv-panel__heading"><?php esc_html_e( 'Filtros', 'agrocampo-post-venta' ); ?></div>

        <div class="agp-pv-period-presets" aria-label="<?php esc_attr_e( 'Períodos rápidos', 'agrocampo-post-venta' ); ?>">
            <?php foreach ( $preset_links as $preset_key => $preset ) : ?>
                <?php $preset_class = 'agp-pv-period-preset' . ( $current_preset === $preset_key ? ' is-active' : '' ); ?>
                <a class="<?php echo esc_attr( $preset_class ); ?>" href="<?php echo esc_url( $build_url( $preset['args'] ) ); ?>"><?php echo esc_html( $preset['label'] ); ?></a>
            <?php endforeach; ?>
            <span class="agp-pv-period-preset is-custom <?php echo esc_attr( 'custom' === $current_preset ? 'is-active' : '' ); ?>"><?php esc_html_e( 'Personalizado', 'agrocampo-post-venta' ); ?></span>
        </div>

        <?php $filters_form_classes = array( 'agp-pv-observations-filters' );
        if ( $range_active ) {
            $filters_form_classes[] = 'has-active-range';
        }
        ?>
        <form method="get" class="<?php echo esc_attr( implode( ' ', $filters_form_classes ) ); ?>">
            <label>
                <span><?php esc_html_e( 'Buscar', 'agrocampo-post-venta' ); ?></span>
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="cliente, técnico, serie...">
            </label>
            <label>
                <span><?php esc_html_e( 'Estado', 'agrocampo-post-venta' ); ?></span>
                <select name="review_status">
                    <option value=""><?php esc_html_e( 'Todos', 'agrocampo-post-venta' ); ?></option>
                    <option value="not_reviewed" <?php selected( $review_status, 'not_reviewed' ); ?>><?php esc_html_e( 'No revisado', 'agrocampo-post-venta' ); ?></option>
                    <option value="strict_pending_review" <?php selected( $review_status, 'strict_pending_review' ); ?>><?php esc_html_e( 'Pendiente de revisión', 'agrocampo-post-venta' ); ?></option>
                    <option value="reviewed" <?php selected( $review_status, 'reviewed' ); ?>><?php esc_html_e( 'Revisado', 'agrocampo-post-venta' ); ?></option>
                    <option value="overdue_pending" <?php selected( $review_status, 'overdue_pending' ); ?>><?php esc_html_e( 'Pendiente vencida', 'agrocampo-post-venta' ); ?></option>
                </select>
            </label>
            <label class="agp-pv-days-field <?php echo esc_attr( $range_active ? 'is-inactive' : '' ); ?>">
                <span><?php esc_html_e( 'Últimos días', 'agrocampo-post-venta' ); ?></span>
                <input type="number" name="days" min="1" max="3650" value="<?php echo esc_attr( (string) $days ); ?>" aria-describedby="agp-pv-days-help">
                <?php if ( $range_active ) : ?>
                    <small id="agp-pv-days-help" class="agp-pv-field-hint"><?php esc_html_e( 'Sin efecto mientras haya rango activo.', 'agrocampo-post-venta' ); ?></small>
                <?php endif; ?>
            </label>
            <label>
                <span><?php esc_html_e( 'Desde', 'agrocampo-post-venta' ); ?></span>
                <input type="date" name="from" value="<?php echo esc_attr( $date_from ); ?>">
            </label>
            <label>
                <span><?php esc_html_e( 'Hasta', 'agrocampo-post-venta' ); ?></span>
                <input type="date" name="to" value="<?php echo esc_attr( $date_to ); ?>">
            </label>
            <div class="agp-pv-filters-actions">
                <button type="submit"><?php esc_html_e( 'Filtrar', 'agrocampo-post-venta' ); ?></button>
                <a class="agp-pv-secondary-link" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Limpiar', 'agrocampo-post-venta' ); ?></a>
                <?php if ( ! $is_public_read_only ) : ?>
                    <a class="agp-pv-secondary-link agp-pv-report-link" href="<?php echo esc_url( $export_report_url ); ?>"><?php esc_html_e( 'Descargar reporte', 'agrocampo-post-venta' ); ?></a>
                <?php endif; ?>
            </div>
        </form>
        <div class="agp-pv-filters-help"><?php esc_html_e( 'Si completas Desde/Hasta, el rango tiene prioridad sobre Últimos días. Pendientes y Vencidas filtran por fecha de creación; Resueltas por fecha de revisión. Limpiar restablece todos los filtros.', 'agrocampo-post-venta' ); ?></div>
    </section>

    <nav class="agp-pv-status-tabs" aria-label="<?php esc_attr_e( 'Filtros rápidos por estado', 'agrocampo-post-venta' ); ?>">
        <?php foreach ( $status_tabs as $tab_status => $tab ) : ?>
            <?php
            $tab_classes = array( 'agp-pv-status-tab' );
            if ( $review_status === $tab_status ) {
                $tab_classes[] = 'is-active';
            }
            if ( 'not_reviewed' === $tab_status ) {
                $tab_classes[] = 'is-pending';
            } elseif ( 'reviewed' === $tab_status ) {
                $tab_classes[] = 'is-reviewed';
            } elseif ( 'overdue_pending' === $tab_status ) {
                $tab_classes[] = 'is-overdue';
            } else {
                $tab_classes[] = 'is-total';
            }
            ?>
            <a class="<?php echo esc_attr( implode( ' ', $tab_classes ) ); ?>" href="<?php echo esc_url( $build_url( array( 'review_status' => $tab_status, 'pg' => null ) ) ); ?>">
                <span><?php echo esc_html( $tab['label'] ); ?></span>
                <strong><?php echo esc_html( (string) $tab['count'] ); ?></strong>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="agp-pv-panel agp-pv-table-panel">
        <div class="agp-pv-observations-table-scroll" role="region" aria-label="<?php esc_attr_e( 'Tabla de informes con observaciones', 'agrocampo-post-venta' ); ?>" tabindex="0">
            <table class="agp-pv-observations-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Cliente', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Técnico', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Observación', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Estado', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Resuelto por', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Fecha resolución', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'PDF', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Acción', 'agrocampo-post-venta' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr>
                        <td colspan="9" class="agp-pv-empty-cell">
                            <div class="agp-pv-empty-state">
                                <strong><?php esc_html_e( 'No hay observaciones para esta búsqueda.', 'agrocampo-post-venta' ); ?></strong>
                                <span><?php esc_html_e( 'Prueba con otro estado o cambia el período.', 'agrocampo-post-venta' ); ?></span>
                            </div>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $rows as $row ) : ?>
                        <?php
                        $submission_id = absint( $row['id'] ?? 0 );
                        $visible_id    = AGP_PV_DB::get_visible_report_id( $row );
                        $user_id       = absint( $row['reviewed_by'] ?? 0 );
                        $reviewer      = '—';
                        if ( $user_id > 0 ) {
                            $user     = get_user_by( 'id', $user_id );
                            $reviewer = $user ? $user->display_name : '#' . $user_id;
                        }

                        $reviewed_at = (string) ( $row['reviewed_at'] ?? '' );
                        if ( '' !== $reviewed_at && '0000-00-00 00:00:00' !== $reviewed_at ) {
                            $reviewed_at = mysql2date( 'd/m/Y H:i', $reviewed_at );
                        } else {
                            $reviewed_at = '—';
                        }

                        $status           = (string) ( $row['review_status'] ?? '' );
                        $is_reviewed      = AGP_PV_Plugin::is_reviewed_observation_status( $status );
                        $is_overdue       = AGP_PV_Plugin::is_overdue_observation_row( $row );
                        $has_history_note = $is_reviewed && AGP_PV_Plugin::has_appended_observation_notes( (string) ( $row['observaciones'] ?? '' ) );
                        $observation_notes = $is_reviewed ? AGP_PV_Plugin::get_observation_notes( $submission_id ) : array();
                        $status_label     = $is_reviewed ? __( 'Resuelta', 'agrocampo-post-venta' ) : ( $is_overdue ? __( 'Vencida', 'agrocampo-post-venta' ) : __( 'Pendiente', 'agrocampo-post-venta' ) );
                        $status_class     = $is_reviewed ? 'is-reviewed' : ( $is_overdue ? 'is-overdue' : 'is-pending' );
                        $row_classes      = array( 'agp-pv-table-row', $status_class );
                        if ( $has_history_note ) {
                            $row_classes[] = 'has-updated-note';
                        }

                        $observaciones = AGP_PV_Plugin::normalize_observation_text( (string) ( $row['observaciones'] ?? '' ) );

                        $view_pdf_url = wp_nonce_url(
                            admin_url( 'admin-post.php?action=agp_pv_view_pdf&submission_id=' . $submission_id ),
                            'agp_pv_view_pdf_' . $submission_id
                        );
                        ?>
                        <tr class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
                            <td data-label="<?php esc_attr_e( 'ID', 'agrocampo-post-venta' ); ?>"><span class="agp-pv-id-chip">#<?php echo esc_html( (string) $visible_id ); ?></span></td>
                            <td data-label="<?php esc_attr_e( 'Cliente', 'agrocampo-post-venta' ); ?>"><?php echo esc_html( (string) ( $row['cliente'] ?? '' ) ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Técnico', 'agrocampo-post-venta' ); ?>"><?php echo esc_html( (string) ( $row['tecnico'] ?? '' ) ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Observación', 'agrocampo-post-venta' ); ?>">
                                <?php if ( '' !== $observaciones ) : ?>
                                    <div class="agp-pv-observation-full"><?php echo nl2br( esc_html( $observaciones ) ); ?></div>
                                <?php else : ?>
                                    <span class="agp-pv-empty-action">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Estado', 'agrocampo-post-venta' ); ?>">
                                <div class="agp-pv-status-stack">
                                    <span class="agp-pv-status-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                                    <?php if ( $has_history_note || ! empty( $observation_notes ) ) : ?>
                                        <span class="agp-pv-status-pill is-note-update"><?php esc_html_e( 'Nueva observación', 'agrocampo-post-venta' ); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Resuelto por', 'agrocampo-post-venta' ); ?>"><?php echo esc_html( (string) $reviewer ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Fecha resolución', 'agrocampo-post-venta' ); ?>"><?php echo esc_html( $reviewed_at ); ?></td>
                            <td data-label="<?php esc_attr_e( 'PDF', 'agrocampo-post-venta' ); ?>">
                                <?php if ( ! $is_public_read_only ) : ?>
                                    <a class="agp-pv-icon-link" href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ver PDF', 'agrocampo-post-venta' ); ?></a>
                                <?php else : ?>
                                    <span class="agp-pv-empty-action">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Acción', 'agrocampo-post-venta' ); ?>">
                                <?php if ( $is_public_read_only ) : ?>
                                    <span class="agp-pv-empty-action"><?php esc_html_e( 'Solo lectura', 'agrocampo-post-venta' ); ?></span>
                                <?php elseif ( $submission_id > 0 ) : ?>
                                    <div class="agp-pv-observation-actions">
                                        <?php if ( ! $is_reviewed ) : ?>
                                            <form method="post" class="agp-pv-inline-action agp-pv-inline-action--row">
                                                <?php wp_nonce_field( 'agp_pv_front_mark_reviewed' ); ?>
                                                <input type="hidden" name="agp_pv_front_action" value="mark_reviewed">
                                                <input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $submission_id ); ?>">
                                                <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $current_view_url ); ?>">
                                                <button type="submit"><?php esc_html_e( 'Marcar resuelto', 'agrocampo-post-venta' ); ?></button>
                                            </form>
                                        <?php else : ?>
                                            <form method="post" class="agp-pv-inline-action agp-pv-inline-action--row" data-agp-confirm-pending>
                                                <?php wp_nonce_field( 'agp_pv_front_unmark_reviewed' ); ?>
                                                <input type="hidden" name="agp_pv_front_action" value="unmark_reviewed">
                                                <input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $submission_id ); ?>">
                                                <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $current_view_url ); ?>">
                                                <button type="submit"><?php esc_html_e( 'Desmarcar resuelto', 'agrocampo-post-venta' ); ?></button>
                                            </form>
                                            <?php $note_dialog_id = 'agp-pv-note-dialog-' . $submission_id; ?>
                                            <button type="button" class="agp-pv-action-button agp-pv-action-button--secondary" data-agp-dialog-open="<?php echo esc_attr( $note_dialog_id ); ?>"><?php esc_html_e( 'Comentarios', 'agrocampo-post-venta' ); ?></button>
                                            <dialog id="<?php echo esc_attr( $note_dialog_id ); ?>" class="agp-pv-dialog agp-pv-dialog--note" aria-labelledby="<?php echo esc_attr( $note_dialog_id . '-title' ); ?>">
                                                <div class="agp-pv-dialog__surface">
                                                    <div class="agp-pv-dialog__header">
                                                        <div>
                                                            <span class="agp-pv-dialog__eyebrow"><?php esc_html_e( 'Informe revisado', 'agrocampo-post-venta' ); ?></span>
                                                            <h2 id="<?php echo esc_attr( $note_dialog_id . '-title' ); ?>"><?php echo esc_html( sprintf( __( 'Comentarios de #%s', 'agrocampo-post-venta' ), $visible_id ) ); ?></h2>
                                                        </div>
                                                        <button type="button" class="agp-pv-dialog__close" data-agp-dialog-close><?php esc_html_e( 'Cerrar', 'agrocampo-post-venta' ); ?></button>
                                                    </div>
                                                    <div class="agp-pv-inline-note-list">
                                                        <?php if ( empty( $observation_notes ) ) : ?>
                                                            <p><?php esc_html_e( 'No hay comentarios adicionales.', 'agrocampo-post-venta' ); ?></p>
                                                        <?php else : ?>
                                                            <?php foreach ( $observation_notes as $note_row ) : ?>
                                                                <?php
                                                                $note_id         = absint( $note_row['id'] ?? 0 );
                                                                $note_author_id  = absint( $note_row['created_by'] ?? 0 );
                                                                $note_author_obj = $note_author_id > 0 ? get_userdata( $note_author_id ) : false;
                                                                $note_author     = $note_author_obj instanceof WP_User ? $note_author_obj->display_name : __( 'Usuario', 'agrocampo-post-venta' );
                                                                $note_date_raw   = (string) ( $note_row['created_at'] ?? '' );
                                                                $note_date       = '' !== $note_date_raw ? mysql2date( 'd/m/Y H:i', $note_date_raw ) : '—';
                                                                ?>
                                                                <div class="agp-pv-inline-note-item">
                                                                    <div><strong><?php echo esc_html( (string) $note_author ); ?></strong> · <?php echo esc_html( (string) $note_date ); ?></div>
                                                                    <div><?php echo nl2br( esc_html( (string) ( $note_row['note_text'] ?? '' ) ) ); ?></div>
                                                                    <form method="post" class="agp-pv-inline-note-form agp-pv-dialog__form">
                                                                        <?php wp_nonce_field( 'agp_pv_front_edit_observation_note' ); ?>
                                                                        <input type="hidden" name="agp_pv_front_action" value="edit_observation_note">
                                                                        <input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $submission_id ); ?>">
                                                                        <input type="hidden" name="note_id" value="<?php echo esc_attr( (string) $note_id ); ?>">
                                                                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $current_view_url ); ?>">
                                                                        <textarea name="observation_note" rows="3" required><?php echo esc_textarea( (string) ( $note_row['note_text'] ?? '' ) ); ?></textarea>
                                                                        <button type="submit"><?php esc_html_e( 'Editar', 'agrocampo-post-venta' ); ?></button>
                                                                    </form>
                                                                    <form method="post" class="agp-pv-inline-note-form agp-pv-dialog__form" onsubmit="return window.confirm('<?php echo esc_js( __( '¿Seguro que quieres borrar este comentario?', 'agrocampo-post-venta' ) ); ?>');">
                                                                        <?php wp_nonce_field( 'agp_pv_front_delete_observation_note' ); ?>
                                                                        <input type="hidden" name="agp_pv_front_action" value="delete_observation_note">
                                                                        <input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $submission_id ); ?>">
                                                                        <input type="hidden" name="note_id" value="<?php echo esc_attr( (string) $note_id ); ?>">
                                                                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $current_view_url ); ?>">
                                                                        <button type="submit"><?php esc_html_e( 'Borrar', 'agrocampo-post-venta' ); ?></button>
                                                                    </form>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <form method="post" class="agp-pv-inline-note-form agp-pv-dialog__form">
                                                        <?php wp_nonce_field( 'agp_pv_front_append_observation' ); ?>
                                                        <input type="hidden" name="agp_pv_front_action" value="append_observation">
                                                        <input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $submission_id ); ?>">
                                                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $current_view_url ); ?>">
                                                        <textarea name="observation_note" rows="5" required placeholder="<?php echo esc_attr__( 'Escribe el comentario...', 'agrocampo-post-venta' ); ?>"></textarea>
                                                        <div class="agp-pv-dialog__actions">
                                                            <button type="button" class="agp-pv-secondary-link agp-pv-dialog__dismiss" data-agp-dialog-close><?php esc_html_e( 'Cancelar', 'agrocampo-post-venta' ); ?></button>
                                                            <button type="submit"><?php esc_html_e( 'Guardar comentario', 'agrocampo-post-venta' ); ?></button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </dialog>
                                        <?php endif; ?>
                                    </div>
                                <?php else : ?>
                                    <span class="agp-pv-empty-action">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="agp-pv-table-footer">
            <div class="agp-pv-table-footer__meta">
                <?php
                if ( $row_count > 0 ) {
                    $from = $offset + 1;
                    $to   = min( $offset + $per_page, $row_count );
                    printf(
                        /* translators: 1: first item number, 2: last item number, 3: total items. */
                        esc_html__( 'Mostrando %1$s a %2$s de %3$s observaciones', 'agrocampo-post-venta' ),
                        esc_html( (string) $from ),
                        esc_html( (string) $to ),
                        esc_html( (string) $row_count )
                    );
                } else {
                    esc_html_e( 'Sin resultados.', 'agrocampo-post-venta' );
                }
                ?>
            </div>

            <?php if ( $total_pages > 1 ) : ?>
                <nav class="agp-pv-pagination" aria-label="<?php esc_attr_e( 'Paginación', 'agrocampo-post-venta' ); ?>">
                    <?php if ( $current_page > 1 ) : ?>
                        <a class="agp-pv-page-link is-nav" href="<?php echo esc_url( $build_url( array( 'pg' => $current_page - 1 ) ) ); ?>"><?php esc_html_e( 'Anterior', 'agrocampo-post-venta' ); ?></a>
                    <?php endif; ?>

                    <?php
                    $start_page = max( 1, $current_page - 2 );
                    $end_page   = min( $total_pages, $current_page + 2 );
                    if ( $start_page > 1 ) {
                        echo '<a class="agp-pv-page-link" href="' . esc_url( $build_url( array( 'pg' => 1 ) ) ) . '">1</a>';
                        if ( $start_page > 2 ) {
                            echo '<span class="agp-pv-page-dots">…</span>';
                        }
                    }

                    for ( $page = $start_page; $page <= $end_page; $page++ ) {
                        $page_classes = 'agp-pv-page-link';
                        if ( $page === $current_page ) {
                            $page_classes .= ' is-current';
                        }
                        echo '<a class="' . esc_attr( $page_classes ) . '" href="' . esc_url( $build_url( array( 'pg' => $page ) ) ) . '">' . esc_html( (string) $page ) . '</a>';
                    }

                    if ( $end_page < $total_pages ) {
                        if ( $end_page < $total_pages - 1 ) {
                            echo '<span class="agp-pv-page-dots">…</span>';
                        }
                        echo '<a class="agp-pv-page-link" href="' . esc_url( $build_url( array( 'pg' => $total_pages ) ) ) . '">' . esc_html( (string) $total_pages ) . '</a>';
                    }
                    ?>

                    <?php if ( $current_page < $total_pages ) : ?>
                        <a class="agp-pv-page-link is-nav" href="<?php echo esc_url( $build_url( array( 'pg' => $current_page + 1 ) ) ); ?>"><?php esc_html_e( 'Siguiente', 'agrocampo-post-venta' ); ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </section>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-agp-confirm-pending]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm('<?php echo esc_js( __( '¿Seguro que quieres volver esta observación a pendiente?', 'agrocampo-post-venta' ) ); ?>')) {
                event.preventDefault();
            }
        });
    });

    var openers = document.querySelectorAll('[data-agp-dialog-open]');
    var closeDialog = function (dialog) {
        if (!dialog) { return; }
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    };

    openers.forEach(function (button) {
        button.addEventListener('click', function () {
            var dialogId = button.getAttribute('data-agp-dialog-open');
            var dialog = dialogId ? document.getElementById(dialogId) : null;
            if (!dialog) { return; }
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', 'open');
            }
        });
    });

    document.querySelectorAll('[data-agp-dialog-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeDialog(button.closest('dialog'));
        });
    });

    document.querySelectorAll('.agp-pv-dialog').forEach(function (dialog) {
        dialog.addEventListener('click', function (event) {
            var surface = dialog.querySelector('.agp-pv-dialog__surface');
            if (!surface) { return; }
            var rect = surface.getBoundingClientRect();
            var inside = event.clientX >= rect.left && event.clientX <= rect.right && event.clientY >= rect.top && event.clientY <= rect.bottom;
            if (!inside) {
                closeDialog(dialog);
            }
        });
    });
});
</script>
<?php wp_footer(); ?>
</body>
</html>
