<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AGP_PV_Admin {
    private static ?AGP_PV_Admin $instance = null;

    public static function get_instance(): AGP_PV_Admin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_agp_pv_resend_email', array( $this, 'handle_resend_email' ) );
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'Post Venta', 'agrocampo-post-venta' ),
            __( 'Post Venta', 'agrocampo-post-venta' ),
            'manage_options',
            'agp-pv-submissions',
            array( $this, 'render_list_page' ),
            'dashicons-forms'
        );
    }

    public function render_list_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        $table = new AGP_PV_Submissions_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Envíos Post Venta', 'agrocampo-post-venta' ) . '</h1>';

        if ( isset( $_GET['agp_pv_notice'] ) ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Correo reenviado.', 'agrocampo-post-venta' ) . '</p></div>';
        }

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="agp-pv-submissions">';
        $table->search_box( __( 'Buscar', 'agrocampo-post-venta' ), 'agp-pv-search' );
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    public function handle_resend_email(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ) );
        }

        check_admin_referer( 'agp_pv_resend_email' );

        $submission_id = isset( $_GET['submission_id'] ) ? absint( $_GET['submission_id'] ) : 0;
        if ( $submission_id ) {
            AGP_PV_Email::send_submission_email( $submission_id );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=agp-pv-submissions&agp_pv_notice=1' ) );
        exit;
    }
}

class AGP_PV_Submissions_Table extends WP_List_Table {
    public function get_columns(): array {
        return array(
            'id' => __( 'ID', 'agrocampo-post-venta' ),
            'tecnico' => __( 'Técnico', 'agrocampo-post-venta' ),
            'cliente' => __( 'Cliente', 'agrocampo-post-venta' ),
            'serie' => __( 'Serie', 'agrocampo-post-venta' ),
            'tipo_servicio' => __( 'Tipo de Servicio', 'agrocampo-post-venta' ),
            'created_at' => __( 'Fecha', 'agrocampo-post-venta' ),
        );
    }

    public function prepare_items(): void {
        global $wpdb;
        $table = AGP_PV_DB::table_name();

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $where = '';
        $args = array();

        if ( $search ) {
            $where = "WHERE tecnico LIKE %s OR cliente LIKE %s OR serie LIKE %s";
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $args = array( $like, $like, $like );
        }

        $items_sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $items_query = $wpdb->prepare( $items_sql, array_merge( $args, array( $per_page, $offset ) ) );
        $this->items = $wpdb->get_results( $items_query, ARRAY_A );

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
        $count_query = $wpdb->prepare( $count_sql, $args );
        $total_items = (int) $wpdb->get_var( $count_query );

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page' => $per_page,
            )
        );

        $this->_column_headers = array( $this->get_columns(), array(), array() );
    }

    public function column_default( $item, $column_name ) {
        if ( 'created_at' === $column_name ) {
            return esc_html( $item['created_at'] );
        }

        return esc_html( $item[ $column_name ] ?? '' );
    }

    public function column_id( $item ): string {
        $actions = array();

        $actions['view'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=agp-pv-submissions&submission_id=' . absint( $item['id'] ) ) ),
            esc_html__( 'Ver detalle', 'agrocampo-post-venta' )
        );

        $actions['resend'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=agp_pv_resend_email&submission_id=' . absint( $item['id'] ) ), 'agp_pv_resend_email' ) ),
            esc_html__( 'Reenviar correo', 'agrocampo-post-venta' )
        );

        $value = esc_html( (string) $item['id'] );
        return $value . $this->row_actions( $actions );
    }

    public function single_row( $item ): void {
        echo '<tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $this->single_row_columns( $item );
        echo '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ( isset( $_GET['submission_id'] ) && absint( $_GET['submission_id'] ) === (int) $item['id'] ) {
            echo '<tr class="agp-pv-detail"><td colspan="6">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo wp_kses_post( $this->render_detail( (int) $item['id'] ) );
            echo '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    private function render_detail( int $submission_id ): string {
        $submission = AGP_PV_Email::get_submission( $submission_id );
        if ( ! $submission ) {
            return esc_html__( 'No se encontró el envío.', 'agrocampo-post-venta' );
        }

        $output = '<strong>' . esc_html__( 'Detalle del envío', 'agrocampo-post-venta' ) . '</strong><br>';
        foreach ( $submission as $key => $value ) {
            if ( 'fotos_ids' === $key ) {
                continue;
            }
            $output .= '<strong>' . esc_html( $key ) . ':</strong> ' . esc_html( (string) $value ) . '<br>';
        }

        $fotos = json_decode( (string) $submission['fotos_ids'], true );
        if ( is_array( $fotos ) && ! empty( $fotos ) ) {
            $output .= '<strong>' . esc_html__( 'Fotos:', 'agrocampo-post-venta' ) . '</strong><br>';
            foreach ( $fotos as $foto_id ) {
                $url = wp_get_attachment_url( (int) $foto_id );
                if ( $url ) {
                    $output .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver foto', 'agrocampo-post-venta' ) . '</a><br>';
                }
            }
        }

        return $output;
    }
}
