<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table = AGP_PV_DB::table_name();

$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$review_status_raw = isset( $_GET['review_status'] ) ? sanitize_key( wp_unslash( $_GET['review_status'] ) ) : '';
$review_status = '' !== $review_status_raw ? AGP_PV_Plugin::normalize_observation_review_filter( $review_status_raw ) : '';
$current_page  = isset( $_GET['pg'] ) ? max( 1, absint( wp_unslash( $_GET['pg'] ) ) ) : 1;
$per_page      = 20;
$window_days    = AGP_PV_Plugin::get_observations_report_window_days();
$window_cutoff  = AGP_PV_Plugin::get_observations_report_cutoff_mysql();
$overdue_days   = AGP_PV_Plugin::get_observations_overdue_days();
$overdue_cutoff = AGP_PV_Plugin::get_observations_overdue_cutoff_mysql();

$base_clauses = array_merge(
    AGP_PV_Plugin::observation_presence_sql_clauses(),
    array( 'created_at >= %s' )
);
$base_values = array( $window_cutoff );

if ( '' !== $search ) {
    $like = '%' . $wpdb->esc_like( $search ) . '%';
    $base_clauses[] = '(tecnico LIKE %s OR cliente LIKE %s OR observaciones LIKE %s OR serie LIKE %s)';
    array_push( $base_values, $like, $like, $like, $like );
}

$base_where       = 'WHERE ' . implode( ' AND ', $base_clauses );
$filtered_clauses = $base_clauses;
$filtered_values  = $base_values;

if ( 'not_reviewed' === $review_status ) {
    $filtered_clauses[] = AGP_PV_Plugin::effective_pending_observation_sql();
} elseif ( 'strict_pending_review' === $review_status ) {
    $filtered_clauses[] = AGP_PV_Plugin::strict_pending_observation_sql();
} elseif ( 'reviewed' === $review_status ) {
    $filtered_clauses[] = AGP_PV_Plugin::reviewed_observation_sql();
} elseif ( 'overdue_pending' === $review_status ) {
    $filtered_clauses[] = AGP_PV_Plugin::effective_pending_observation_sql();
    $filtered_clauses[] = 'created_at <= %s';
    $filtered_values[]  = $overdue_cutoff;
}

$filtered_where = 'WHERE ' . implode( ' AND ', $filtered_clauses );

$pending_sql  = AGP_PV_Plugin::effective_pending_observation_sql();
$reviewed_sql = AGP_PV_Plugin::reviewed_observation_sql();

$summary_sql = "
    SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN {$pending_sql} THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN {$reviewed_sql} THEN 1 ELSE 0 END) AS reviewed_count,
        SUM(CASE WHEN {$pending_sql} AND created_at <= %s THEN 1 ELSE 0 END) AS overdue_count
    FROM {$table}
    {$base_where}
";
$summary_values = array_merge( array( $overdue_cutoff ), $base_values );
$summary_query = $wpdb->prepare( $summary_sql, $summary_values );
$summary       = (array) $wpdb->get_row( $summary_query, ARRAY_A );

$total_count    = isset( $summary['total_count'] ) ? (int) $summary['total_count'] : 0;
$pending_count  = isset( $summary['pending_count'] ) ? (int) $summary['pending_count'] : 0;
$reviewed_count = isset( $summary['reviewed_count'] ) ? (int) $summary['reviewed_count'] : 0;
$overdue_count  = isset( $summary['overdue_count'] ) ? (int) $summary['overdue_count'] : 0;

$count_sql   = "SELECT COUNT(*) FROM {$table} {$filtered_where}";
$count_query = empty( $filtered_values ) ? $count_sql : $wpdb->prepare( $count_sql, $filtered_values );
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
    ''               => array(
        'label' => __( 'Todas', 'agrocampo-post-venta' ),
        'count' => $total_count,
    ),
    'not_reviewed' => array(
        'label' => __( 'Pendientes', 'agrocampo-post-venta' ),
        'count' => $pending_count,
    ),
    'reviewed'       => array(
        'label' => __( 'Resueltas', 'agrocampo-post-venta' ),
        'count' => $reviewed_count,
    ),
    'overdue_pending' => array(
        'label' => __( 'Vencidas', 'agrocampo-post-venta' ),
        'count' => $overdue_count,
    ),
);

$base_url               = AGP_PV_Plugin::observations_standalone_url();
$admin_observations_url = admin_url( 'admin.php?page=agp-pv-observations' );

$build_url = static function ( array $args = array() ) use ( $base_url, $search, $review_status, $review_status_raw ) {
    $query_args = array();

    if ( '' !== $search ) {
        $query_args['s'] = $search;
    }

    if ( '' !== $review_status ) {
        $query_args['review_status'] = $review_status;
    } elseif ( 'pending_review' === $review_status_raw ) {
        $query_args['review_status'] = 'pending_review';
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
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body class="agp-pv-observations-standalone">
<main class="agp-pv-observations-wrap">
    <section class="agp-pv-shell-header">
        <div class="agp-pv-shell-header__title">
            <span class="agp-pv-shell-header__eyebrow"><?php esc_html_e( 'Post venta', 'agrocampo-post-venta' ); ?></span>
            <h1><?php esc_html_e( 'Observaciones', 'agrocampo-post-venta' ); ?></h1>
            <p class="agp-pv-shell-header__hint"><?php echo esc_html( sprintf( __( 'Mostrando informes con observaciones creados en los últimos %1$d días. Se consideran vencidas desde %2$d días.', 'agrocampo-post-venta' ), $window_days, $overdue_days ) ); ?></p>
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
            <a class="agp-pv-primary-link" href="<?php echo esc_url( $admin_observations_url ); ?>"><?php esc_html_e( 'Gestor interno', 'agrocampo-post-venta' ); ?></a>
        </div>
    </section>

    <?php if ( 'review_marked' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-success"><?php esc_html_e( 'Informe marcado como revisado.', 'agrocampo-post-venta' ); ?></div>
    <?php elseif ( 'review_failed' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-error"><?php esc_html_e( 'No se pudo actualizar el estado de revisión.', 'agrocampo-post-venta' ); ?></div>
    <?php elseif ( 'observation_appended' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-success"><?php esc_html_e( 'Observación agregada correctamente al informe revisado.', 'agrocampo-post-venta' ); ?></div>
    <?php elseif ( 'observation_append_failed' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-error"><?php esc_html_e( 'No se pudo agregar la observación.', 'agrocampo-post-venta' ); ?></div>
    <?php endif; ?>

    <section class="agp-pv-panel agp-pv-filters-panel">
        <div class="agp-pv-panel__heading"><?php esc_html_e( 'Filtros', 'agrocampo-post-venta' ); ?></div>
        <form method="get" class="agp-pv-observations-filters">
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
            <div class="agp-pv-filters-actions">
                <button type="submit"><?php esc_html_e( 'Filtrar', 'agrocampo-post-venta' ); ?></button>
                <a class="agp-pv-secondary-link" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Limpiar', 'agrocampo-post-venta' ); ?></a>
            </div>
        </form>
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
                    <th><?php esc_html_e( 'Revisado por', 'agrocampo-post-venta' ); ?></th>
                    <th><?php esc_html_e( 'Fecha revisión', 'agrocampo-post-venta' ); ?></th>
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
                                <span><?php esc_html_e( 'Prueba con otro estado o limpia los filtros.', 'agrocampo-post-venta' ); ?></span>
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

                        $status        = (string) ( $row['review_status'] ?? '' );
                        $is_reviewed   = AGP_PV_Plugin::is_reviewed_observation_status( $status );
                        $is_overdue    = AGP_PV_Plugin::is_overdue_observation_row( $row );
                        $status_label  = $is_reviewed ? __( 'Resuelta', 'agrocampo-post-venta' ) : ( $is_overdue ? __( 'Vencida', 'agrocampo-post-venta' ) : __( 'Pendiente', 'agrocampo-post-venta' ) );
                        $status_class  = $is_reviewed ? 'is-reviewed' : ( $is_overdue ? 'is-overdue' : 'is-pending' );

                        $observaciones = AGP_PV_Plugin::normalize_observation_text( (string) ( $row['observaciones'] ?? '' ) );

                        $view_pdf_url = wp_nonce_url(
                            admin_url( 'admin.php?page=agp-pv-observations&view=' . $submission_id ),
                            'agp_pv_view_pdf_' . $submission_id
                        );
                        ?>
                        <tr>
                            <td data-label="<?php esc_attr_e( 'ID', 'agrocampo-post-venta' ); ?>"><span class="agp-pv-id-chip">#<?php echo esc_html( (string) $visible_id ); ?></span></td>
                            <td data-label="<?php esc_attr_e( 'Cliente', 'agrocampo-post-venta' ); ?>"><?php echo esc_html( (string) ( $row['cliente'] ?? '' ) ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Técnico', 'agrocampo-post-venta' ); ?>"><?php echo esc_html( (string) ( $row['tecnico'] ?? '' ) ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Observación', 'agrocampo-post-venta' ); ?>"><?php echo '' !== $observaciones ? esc_html( $observaciones ) : '&mdash;'; ?></td>
                            <td data-label="<?php esc_attr_e( 'Estado', 'agrocampo-post-venta' ); ?>"><span class="agp-pv-status-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
                            <td data-label="<?php esc_attr_e( 'Revisado por', 'agrocampo-post-venta' ); ?>"><?php echo esc_html( (string) $reviewer ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Fecha revisión', 'agrocampo-post-venta' ); ?>"><?php echo esc_html( $reviewed_at ); ?></td>
                            <td data-label="<?php esc_attr_e( 'PDF', 'agrocampo-post-venta' ); ?>"><a class="agp-pv-icon-link" href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ver PDF', 'agrocampo-post-venta' ); ?></a></td>
                            <td data-label="<?php esc_attr_e( 'Acción', 'agrocampo-post-venta' ); ?>">
                                <?php if ( ! $is_reviewed && $submission_id > 0 ) : ?>
                                    <form method="post" class="agp-pv-inline-action">
                                        <?php wp_nonce_field( 'agp_pv_front_mark_reviewed' ); ?>
                                        <input type="hidden" name="agp_pv_front_action" value="mark_reviewed">
                                        <input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $submission_id ); ?>">
                                        <button type="submit"><?php esc_html_e( 'Marcar revisado', 'agrocampo-post-venta' ); ?></button>
                                    </form>
                                <?php elseif ( $submission_id > 0 ) : ?>
                                    <details class="agp-pv-note-box">
                                        <summary><?php esc_html_e( 'Agregar observación', 'agrocampo-post-venta' ); ?></summary>
                                        <form method="post" class="agp-pv-inline-note-form">
                                            <?php wp_nonce_field( 'agp_pv_front_append_observation' ); ?>
                                            <input type="hidden" name="agp_pv_front_action" value="append_observation">
                                            <input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $submission_id ); ?>">
                                            <textarea name="observation_note" rows="3" required placeholder="<?php echo esc_attr__( 'Escribe la nueva observación...', 'agrocampo-post-venta' ); ?>"></textarea>
                                            <button type="submit"><?php esc_html_e( 'Guardar observación', 'agrocampo-post-venta' ); ?></button>
                                        </form>
                                    </details>
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
<?php wp_footer(); ?>
</body>
</html>
