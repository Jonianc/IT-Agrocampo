<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table = AGP_PV_DB::table_name();

$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$review_status = isset( $_GET['review_status'] ) ? sanitize_key( wp_unslash( $_GET['review_status'] ) ) : '';

$clauses = array(
    "COALESCE(TRIM(observaciones), '') <> ''",
    "UPPER(TRIM(COALESCE(observaciones, ''))) <> 'NULL'",
);
$values = array();

if ( '' !== $search ) {
    $like = '%' . $wpdb->esc_like( $search ) . '%';
    $clauses[] = '(tecnico LIKE %s OR cliente LIKE %s OR observaciones LIKE %s OR serie LIKE %s)';
    array_push( $values, $like, $like, $like, $like );
}

if ( in_array( $review_status, array( 'pending_review', 'reviewed' ), true ) ) {
    $clauses[] = 'review_status = %s';
    $values[] = $review_status;
}

$where = 'WHERE ' . implode( ' AND ', $clauses );
$sql = "SELECT id, legacy_id, tecnico, cliente, observaciones, review_status, reviewed_by, reviewed_at, created_at FROM {$table} {$where} ORDER BY (legacy_id > 0) DESC, legacy_id DESC, id DESC LIMIT 200";
$query = empty( $values ) ? $sql : $wpdb->prepare( $sql, $values );
$rows = $wpdb->get_results( $query, ARRAY_A );

$notice = isset( $_GET['agp_pv_notice'] ) ? sanitize_key( wp_unslash( $_GET['agp_pv_notice'] ) ) : '';

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body class="agp-pv-observations-standalone">
<main class="agp-pv-observations-wrap">
    <header class="agp-pv-observations-header">
        <h1><?php esc_html_e( 'Gestor de observaciones (frontend)', 'agrocampo-post-venta' ); ?></h1>
        <p><?php esc_html_e( 'Vista standalone sin theme para revisar informes con observaciones.', 'agrocampo-post-venta' ); ?></p>
    </header>

    <?php if ( 'review_marked' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-success"><?php esc_html_e( 'Informe marcado como revisado.', 'agrocampo-post-venta' ); ?></div>
    <?php elseif ( 'review_failed' === $notice ) : ?>
        <div class="agp-pv-notice agp-pv-notice-error"><?php esc_html_e( 'No se pudo actualizar el estado de revisión.', 'agrocampo-post-venta' ); ?></div>
    <?php endif; ?>

    <form method="get" class="agp-pv-observations-filters">
        <label>
            <?php esc_html_e( 'Buscar', 'agrocampo-post-venta' ); ?>
            <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="cliente, técnico, serie...">
        </label>
        <label>
            <?php esc_html_e( 'Estado revisión', 'agrocampo-post-venta' ); ?>
            <select name="review_status">
                <option value=""><?php esc_html_e( 'Todos', 'agrocampo-post-venta' ); ?></option>
                <option value="pending_review" <?php selected( $review_status, 'pending_review' ); ?>><?php esc_html_e( 'Pendiente de revisión', 'agrocampo-post-venta' ); ?></option>
                <option value="reviewed" <?php selected( $review_status, 'reviewed' ); ?>><?php esc_html_e( 'Revisado', 'agrocampo-post-venta' ); ?></option>
            </select>
        </label>
        <button type="submit"><?php esc_html_e( 'Aplicar', 'agrocampo-post-venta' ); ?></button>
        <a class="agp-pv-link-reset" href="<?php echo esc_url( AGP_PV_Plugin::observations_standalone_url() ); ?>"><?php esc_html_e( 'Limpiar', 'agrocampo-post-venta' ); ?></a>
    </form>

    <table class="agp-pv-observations-table">
        <thead>
        <tr>
            <th><?php esc_html_e( 'ID', 'agrocampo-post-venta' ); ?></th>
            <th><?php esc_html_e( 'Técnico', 'agrocampo-post-venta' ); ?></th>
            <th><?php esc_html_e( 'Cliente', 'agrocampo-post-venta' ); ?></th>
            <th><?php esc_html_e( 'Observaciones', 'agrocampo-post-venta' ); ?></th>
            <th><?php esc_html_e( 'Estado revisión', 'agrocampo-post-venta' ); ?></th>
            <th><?php esc_html_e( 'Revisado por', 'agrocampo-post-venta' ); ?></th>
            <th><?php esc_html_e( 'Fecha revisión', 'agrocampo-post-venta' ); ?></th>
            <th><?php esc_html_e( 'PDF', 'agrocampo-post-venta' ); ?></th>
            <th><?php esc_html_e( 'Acción', 'agrocampo-post-venta' ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if ( empty( $rows ) ) : ?>
            <tr><td colspan="9"><?php esc_html_e( 'No hay informes con observaciones para los filtros seleccionados.', 'agrocampo-post-venta' ); ?></td></tr>
        <?php else : ?>
            <?php foreach ( $rows as $row ) : ?>
                <?php
                $submission_id = absint( $row['id'] ?? 0 );
                $visible_id = AGP_PV_DB::get_visible_report_id( $row );
                $user_id = absint( $row['reviewed_by'] ?? 0 );
                $reviewer = '—';
                if ( $user_id > 0 ) {
                    $user = get_user_by( 'id', $user_id );
                    $reviewer = $user ? $user->display_name : '#' . $user_id;
                }
                $reviewed_at = (string) ( $row['reviewed_at'] ?? '' );
                if ( '' === $reviewed_at || '0000-00-00 00:00:00' === $reviewed_at ) {
                    $reviewed_at = '—';
                }
                $status = (string) ( $row['review_status'] ?? '' );
                $observaciones = trim( (string) ( $row['observaciones'] ?? '' ) );
                if ( 'NULL' === strtoupper( $observaciones ) ) {
                    $observaciones = '';
                }
                $view_pdf_url = wp_nonce_url(
                    admin_url( 'admin.php?page=agp-pv-observations&view=' . $submission_id ),
                    'agp_pv_view_pdf_' . $submission_id
                );
                ?>
                <tr>
                    <td><?php echo esc_html( (string) $visible_id ); ?></td>
                    <td><?php echo esc_html( (string) ( $row['tecnico'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( (string) ( $row['cliente'] ?? '' ) ); ?></td>
                    <td><?php echo '' !== $observaciones ? esc_html( $observaciones ) : '&mdash;'; ?></td>
                    <td><?php echo esc_html( 'pending_review' === $status ? __( 'Pendiente de revisión', 'agrocampo-post-venta' ) : ( 'reviewed' === $status ? __( 'Revisado', 'agrocampo-post-venta' ) : $status ) ); ?></td>
                    <td><?php echo esc_html( (string) $reviewer ); ?></td>
                    <td><?php echo esc_html( $reviewed_at ); ?></td>
                    <td><a href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ver PDF', 'agrocampo-post-venta' ); ?></a></td>
                    <td>
                        <?php if ( 'reviewed' !== $status && $submission_id > 0 ) : ?>
                            <form method="post">
                                <?php wp_nonce_field( 'agp_pv_front_mark_reviewed' ); ?>
                                <input type="hidden" name="agp_pv_front_action" value="mark_reviewed">
                                <input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $submission_id ); ?>">
                                <button type="submit"><?php esc_html_e( 'Marcar revisado', 'agrocampo-post-venta' ); ?></button>
                            </form>
                        <?php else : ?>
                            <span>—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</main>
<?php wp_footer(); ?>
</body>
</html>
