<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AGP_PV_Plugin {
    private static ?AGP_PV_Plugin $instance = null;
    /** @var array<int,array<string,string>>|null */
    private static ?array $lubricants_catalog = null;
    private const VERSION_OPTION_KEY = 'agp_pv_plugin_version';
    private const REWRITE_VERSION_OPTION_KEY = 'agp_pv_rewrite_version';
    private const REWRITE_VERSION = '2';
    private const PUBLIC_OBSERVATIONS_ACCESS_OPTION_KEY = 'agp_pv_public_observations_access';
    private const LUBRICANTS_BASE_CATALOG_RELATIVE_PATH = 'data/aceites-catalog.json';
    private const LUBRICANTS_PERSISTENT_DIR = 'agrocampo-postventa';
    private const LUBRICANTS_PERSISTENT_FILENAME = 'catalogo.json';

    private function __construct() {
        add_action( 'init', array( $this, 'register_rewrite' ), 5 );
        add_action( 'init', array( $this, 'maybe_upgrade' ), 20 );
        add_action( 'init', array( $this, 'maybe_schedule_notification_events' ) );
        add_filter( 'query_vars', array( $this, 'register_query_var' ) );
        add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
        add_action( 'template_redirect', array( $this, 'render_standalone' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'agp_pv_daily_notifications', array( $this, 'handle_daily_notifications' ) );
        add_action( 'agp_pv_weekly_summary', array( $this, 'handle_weekly_summary' ) );

        AGP_PV_Ajax::get_instance();
        AGP_PV_Admin::get_instance();
    }

    public static function get_instance(): AGP_PV_Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * @return string[]
     */
    public static function observation_placeholder_tokens(): array {
        return array(
            'NULL',
            'N/A',
            'NA',
            '-',
            'SIN OBSERVACIONES',
        );
    }

    public static function normalize_observation_text( string $text ): string {
        $text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
        if ( '' === $text ) {
            return '';
        }

        $normalized = strtoupper( trim( remove_accents( $text ) ) );
        if ( in_array( $normalized, self::observation_placeholder_tokens(), true ) ) {
            return '';
        }

        return $text;
    }

    public static function has_meaningful_observation_text( string $text ): bool {
        return '' !== self::normalize_observation_text( $text );
    }

    public static function normalize_review_status_key( string $status ): string {
        return strtolower( trim( $status ) );
    }

    public static function is_reviewed_observation_status( string $status ): bool {
        return 'reviewed' === self::normalize_review_status_key( $status );
    }

    public static function is_strict_pending_review_status( string $status ): bool {
        return 'pending_review' === self::normalize_review_status_key( $status );
    }

    public static function is_effective_pending_observation_row( array $row ): bool {
        if ( ! self::has_meaningful_observation_text( (string) ( $row['observaciones'] ?? '' ) ) ) {
            return false;
        }

        return ! self::is_reviewed_observation_status( (string) ( $row['review_status'] ?? '' ) );
    }

    public static function observation_review_status_sql_expr( string $column = 'review_status' ): string {
        return "LOWER(TRIM(COALESCE({$column}, '')))";
    }

    public static function effective_pending_observation_sql( string $column = 'review_status' ): string {
        $expr = self::observation_review_status_sql_expr( $column );

        return "{$expr} <> 'reviewed'";
    }

    public static function reviewed_observation_sql( string $column = 'review_status' ): string {
        $expr = self::observation_review_status_sql_expr( $column );

        return "{$expr} = 'reviewed'";
    }

    public static function strict_pending_observation_sql( string $column = 'review_status' ): string {
        $expr = self::observation_review_status_sql_expr( $column );

        return "{$expr} = 'pending_review'";
    }

    public static function normalize_observation_review_filter( string $filter ): string {
        $filter = sanitize_key( $filter );

        if ( 'pending_review' === $filter ) {
            return 'not_reviewed';
        }

        if ( in_array( $filter, array( 'not_reviewed', 'strict_pending_review', 'reviewed', 'overdue_pending' ), true ) ) {
            return $filter;
        }

        return '';
    }

    public static function resolve_review_status_for_observation( string $text, string $current_status = '' ): string {
        if ( self::has_meaningful_observation_text( $text ) ) {
            return 'pending_review';
        }

        if ( in_array( $current_status, array( 'pending_review', 'reviewed', 'not_required' ), true ) ) {
            return $current_status;
        }

        return 'not_required';
    }

    /**
     * @return string[]
     */
    public static function observation_presence_sql_clauses( string $column = 'observaciones' ): array {
        return array(
            "COALESCE(TRIM({$column}), '') <> ''",
            "UPPER(TRIM(COALESCE({$column}, ''))) NOT IN ('NULL', 'N/A', 'NA', '-', 'SIN OBSERVACIONES')",
        );
    }


    /**
     * @return array<int,array<string,string>>
     */
    public static function get_lubricants_catalog(): array {
        if ( null !== self::$lubricants_catalog ) {
            return self::$lubricants_catalog;
        }

        $persist_info = self::get_lubricants_persistent_catalog_path();
        $catalog      = self::read_lubricants_catalog_file( $persist_info['path'] );

        if ( empty( $catalog ) ) {
            $catalog = self::read_lubricants_catalog_file( self::get_lubricants_base_catalog_path() );
        }

        self::$lubricants_catalog = $catalog;
        return self::$lubricants_catalog;
    }

    public static function clear_lubricants_catalog_cache(): void {
        self::$lubricants_catalog = null;
    }

    /**
     * @return array{path:string,exists:bool,writable:bool,base_dir:string}
     */
    public static function get_lubricants_persistent_catalog_path(): array {
        $uploads = wp_upload_dir();
        $base_dir = (string) ( $uploads['basedir'] ?? '' );
        if ( '' === $base_dir ) {
            return array(
                'path' => '',
                'exists' => false,
                'writable' => false,
                'base_dir' => '',
            );
        }

        $dir = trailingslashit( $base_dir ) . self::LUBRICANTS_PERSISTENT_DIR;
        $path = trailingslashit( $dir ) . self::LUBRICANTS_PERSISTENT_FILENAME;

        return array(
            'path' => $path,
            'exists' => file_exists( $path ),
            'writable' => file_exists( $dir ) ? is_writable( $dir ) : is_writable( $base_dir ),
            'base_dir' => $base_dir,
        );
    }

    public static function get_lubricants_base_catalog_path(): string {
        return AGP_PV_PLUGIN_DIR . self::LUBRICANTS_BASE_CATALOG_RELATIVE_PATH;
    }

    /**
     * @return array{created:bool,error:string}
     */
    public static function ensure_persistent_lubricants_catalog(): array {
        $info = self::get_lubricants_persistent_catalog_path();
        $path = $info['path'];
        if ( '' === $path ) {
            return array(
                'created' => false,
                'error' => __( 'No se pudo resolver la ruta de uploads para el catálogo persistente.', 'agrocampo-post-venta' ),
            );
        }

        if ( file_exists( $path ) ) {
            return array(
                'created' => false,
                'error' => '',
            );
        }

        $dir = dirname( $path );
        if ( ! wp_mkdir_p( $dir ) ) {
            return array(
                'created' => false,
                'error' => __( 'No se pudo crear la carpeta persistente del catálogo en uploads.', 'agrocampo-post-venta' ),
            );
        }

        $base_path = self::get_lubricants_base_catalog_path();
        if ( ! file_exists( $base_path ) ) {
            return array(
                'created' => false,
                'error' => __( 'No existe catálogo base del plugin para inicializar el persistente.', 'agrocampo-post-venta' ),
            );
        }

        $base_contents = file_get_contents( $base_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false === $base_contents || '' === trim( $base_contents ) ) {
            return array(
                'created' => false,
                'error' => __( 'El catálogo base del plugin está vacío o no se pudo leer.', 'agrocampo-post-venta' ),
            );
        }

        $catalog = self::read_lubricants_catalog_file( $base_path );
        if ( empty( $catalog ) ) {
            return array(
                'created' => false,
                'error' => __( 'El catálogo base del plugin es inválido.', 'agrocampo-post-venta' ),
            );
        }

        $written = file_put_contents( $path, $base_contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        if ( false === $written ) {
            return array(
                'created' => false,
                'error' => __( 'No se pudo crear el catálogo persistente en uploads.', 'agrocampo-post-venta' ),
            );
        }

        self::clear_lubricants_catalog_cache();

        return array(
            'created' => true,
            'error' => '',
        );
    }

    /**
     * @return array{source:string,path:string,exists:bool,valid:bool,item_count:int,modified_gmt:string}
     */
    public static function get_lubricants_catalog_status(): array {
        $persist_info = self::get_lubricants_persistent_catalog_path();
        $persist_path = (string) $persist_info['path'];
        $persist_catalog = self::read_lubricants_catalog_file( $persist_path );
        if ( ! empty( $persist_catalog ) ) {
            return array(
                'source' => 'persistent',
                'path' => $persist_path,
                'exists' => file_exists( $persist_path ),
                'valid' => true,
                'item_count' => count( $persist_catalog ),
                'modified_gmt' => self::format_file_modified_gmt( $persist_path ),
            );
        }

        $base_path = self::get_lubricants_base_catalog_path();
        $base_catalog = self::read_lubricants_catalog_file( $base_path );

        return array(
            'source' => 'base',
            'path' => $base_path,
            'exists' => file_exists( $base_path ),
            'valid' => ! empty( $base_catalog ),
            'item_count' => count( $base_catalog ),
            'modified_gmt' => self::format_file_modified_gmt( $base_path ),
        );
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function read_lubricants_catalog_file( string $path ): array {
        if ( '' === $path || ! file_exists( $path ) ) {
            return array();
        }

        $contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false === $contents || '' === trim( $contents ) ) {
            return array();
        }

        $decoded = json_decode( $contents, true );
        if ( ! is_array( $decoded ) ) {
            return array();
        }

        $catalog = array();
        foreach ( $decoded as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $type = sanitize_text_field( (string) ( $row['type'] ?? '' ) );
            $product = sanitize_text_field( (string) ( $row['product'] ?? '' ) );
            if ( '' === $type || '' === $product ) {
                continue;
            }

            $catalog[] = array(
                'type' => $type,
                'product' => $product,
                'code' => sanitize_text_field( (string) ( $row['code'] ?? '' ) ),
                'presentation' => sanitize_text_field( (string) ( $row['presentation'] ?? '' ) ),
                'description' => sanitize_text_field( (string) ( $row['description'] ?? '' ) ),
                'unit' => sanitize_text_field( (string) ( $row['unit'] ?? '' ) ),
            );
        }

        return $catalog;
    }

    private static function format_file_modified_gmt( string $path ): string {
        if ( '' === $path || ! file_exists( $path ) ) {
            return '';
        }

        $timestamp = filemtime( $path );
        if ( false === $timestamp ) {
            return '';
        }

        return gmdate( 'Y-m-d H:i:s', (int) $timestamp );
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    public static function build_lubricants_legacy_summary( array $items ): string {
        $lines = array();

        foreach ( $items as $item ) {
            $product = sanitize_text_field( (string) ( $item['product'] ?? '' ) );
            if ( '' === $product ) {
                continue;
            }

            $type = sanitize_text_field( (string) ( $item['type'] ?? '' ) );
            $code = sanitize_text_field( (string) ( $item['code'] ?? '' ) );
            $presentation = sanitize_text_field( (string) ( $item['presentation'] ?? '' ) );
            $description = sanitize_text_field( (string) ( $item['description'] ?? '' ) );
            $quantity = sanitize_text_field( (string) ( $item['quantity'] ?? '' ) );
            $unit = sanitize_text_field( (string) ( $item['unit'] ?? '' ) );
            $observation = sanitize_textarea_field( (string) ( $item['observation'] ?? '' ) );

            $line_parts = array();
            if ( '' !== $type ) {
                $line_parts[] = $type;
            }

            $line_parts[] = $product;

            $meta = array();
            if ( '' !== $code ) {
                $meta[] = 'Código: ' . $code;
            }
            if ( '' !== $presentation ) {
                $meta[] = 'Presentación: ' . $presentation;
            }
            if ( '' !== $description ) {
                $meta[] = $description;
            }
            if ( '' !== $quantity ) {
                $meta[] = 'Cantidad: ' . $quantity . ( '' !== $unit ? ' ' . $unit : '' );
            }
            if ( '' !== $observation ) {
                $meta[] = 'Obs: ' . $observation;
            }

            if ( ! empty( $meta ) ) {
                $line_parts[] = '(' . implode( '; ', $meta ) . ')';
            }

            $lines[] = implode( ' - ', $line_parts );
        }

        return implode( "\n", $lines );
    }

    /**
     * @return array{items:array<int,array<string,string>>,error:string}
     */
    public static function normalize_lubricants_json( string $raw_json ): array {
        $raw_json = trim( $raw_json );
        if ( '' === $raw_json ) {
            return array(
                'items' => array(),
                'error' => '',
            );
        }

        $decoded = json_decode( $raw_json, true );
        if ( ! is_array( $decoded ) ) {
            return array(
                'items' => array(),
                'error' => __( 'Formato de lubricantes inválido.', 'agrocampo-post-venta' ),
            );
        }

        $catalog = self::get_lubricants_catalog();
        $has_catalog = ! empty( $catalog );
        $lubricants_settings = self::get_lubricants_settings();
        $usage_mode = sanitize_key( (string) ( $lubricants_settings['usage_mode'] ?? 'catalog_only' ) );
        $allow_manual_products = in_array( $usage_mode, array( 'mixed', 'manual_only' ), true ) || ! $has_catalog;
        $catalog_map = array();
        if ( $has_catalog ) {
            foreach ( $catalog as $catalog_item ) {
                $catalog_key = strtolower( trim( (string) $catalog_item['type'] ) . '|' . trim( (string) $catalog_item['product'] ) );
                $catalog_map[ $catalog_key ] = $catalog_item;
            }
        }

        $normalized = array();
        foreach ( $decoded as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $type = sanitize_text_field( (string) ( $row['type'] ?? '' ) );
            $raw_product = (string) ( $row['product'] ?? '' );
            $product = sanitize_text_field( $raw_product );
            $quantity = sanitize_text_field( (string) ( $row['quantity'] ?? '' ) );
            $unit = sanitize_text_field( (string) ( $row['unit'] ?? '' ) );
            $observation = sanitize_textarea_field( (string) ( $row['observation'] ?? '' ) );

            if ( '' === $type && '' === $product && '' === $quantity && '' === $unit && '' === $observation ) {
                continue;
            }

            if ( '' === $product ) {
                if ( '' !== trim( $raw_product ) ) {
                    return array(
                        'items' => array(),
                        'error' => __( 'Producto manual de lubricantes inválido.', 'agrocampo-post-venta' ),
                    );
                }
                continue;
            }

            if ( '' === $quantity ) {
                return array(
                    'items' => array(),
                    'error' => __( 'Cantidad obligatoria para lubricantes con producto seleccionado.', 'agrocampo-post-venta' ),
                );
            }

            if ( $has_catalog ) {
                $catalog_key = strtolower( trim( $type ) . '|' . trim( $product ) );
                if ( isset( $catalog_map[ $catalog_key ] ) ) {
                    $catalog_item = $catalog_map[ $catalog_key ];
                    if ( '' === $unit ) {
                        $unit = (string) ( $catalog_item['unit'] ?? '' );
                    }

                    $normalized[] = array(
                        'type' => $type,
                        'product' => $product,
                        'code' => sanitize_text_field( (string) ( $catalog_item['code'] ?? '' ) ),
                        'presentation' => sanitize_text_field( (string) ( $catalog_item['presentation'] ?? '' ) ),
                        'description' => sanitize_text_field( (string) ( $catalog_item['description'] ?? '' ) ),
                        'quantity' => $quantity,
                        'unit' => $unit,
                        'observation' => $observation,
                    );
                } elseif ( $allow_manual_products ) {
                    $normalized[] = array(
                        'type' => $type,
                        'product' => $product,
                        'code' => sanitize_text_field( (string) ( $row['code'] ?? '' ) ),
                        'presentation' => sanitize_text_field( (string) ( $row['presentation'] ?? '' ) ),
                        'description' => sanitize_text_field( (string) ( $row['description'] ?? '' ) ),
                        'quantity' => $quantity,
                        'unit' => $unit,
                        'observation' => $observation,
                    );
                } else {
                    return array(
                        'items' => array(),
                        'error' => __( 'Producto de lubricantes inválido.', 'agrocampo-post-venta' ),
                    );
                }
            } else {
                $normalized[] = array(
                    'type' => $type,
                    'product' => $product,
                    'code' => sanitize_text_field( (string) ( $row['code'] ?? '' ) ),
                    'presentation' => sanitize_text_field( (string) ( $row['presentation'] ?? '' ) ),
                    'description' => sanitize_text_field( (string) ( $row['description'] ?? '' ) ),
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'observation' => $observation,
                );
            }

            if ( count( $normalized ) > 10 ) {
                return array(
                    'items' => array(),
                    'error' => __( 'Máximo 10 filas de lubricantes.', 'agrocampo-post-venta' ),
                );
            }
        }

        return array(
            'items' => $normalized,
            'error' => '',
        );
    }

    public static function resolve_lubricants_text_from_submission( array $submission ): string {
        $raw_json = isset( $submission['lubricantes_json'] ) ? (string) $submission['lubricantes_json'] : '';
        if ( '' !== trim( $raw_json ) ) {
            $normalized = self::normalize_lubricants_json( $raw_json );
            if ( '' === $normalized['error'] && ! empty( $normalized['items'] ) ) {
                return self::build_lubricants_legacy_summary( $normalized['items'] );
            }
        }

        return sanitize_textarea_field( (string) ( $submission['lubricantes'] ?? '' ) );
    }

    public static function get_observations_overdue_days(): int {
        $settings = AGP_PV_Email::get_notification_settings();
        $days     = max( 1, min( 3650, (int) ( $settings['overdue_days'] ?? 7 ) ) );

        return $days;
    }

    public static function get_observations_overdue_cutoff_mysql(): string {
        $days = self::get_observations_overdue_days();

        return current_datetime()->modify( '-' . $days . ' days' )->format( 'Y-m-d H:i:s' );
    }

    public static function is_overdue_observation_row( array $row ): bool {
        if ( ! self::is_effective_pending_observation_row( $row ) ) {
            return false;
        }

        $created_at = (string) ( $row['created_at'] ?? '' );
        if ( '' === $created_at || '0000-00-00 00:00:00' === $created_at ) {
            return false;
        }

        return $created_at <= self::get_observations_overdue_cutoff_mysql();
    }

    public static function append_observation_note( string $existing_text, string $new_text, string $user_label = '' ): string {
        $existing_text = self::normalize_observation_text( $existing_text );
        $new_text      = self::normalize_observation_text( $new_text );

        if ( '' === $new_text ) {
            return $existing_text;
        }

        $stamp = wp_date( 'd/m/Y H:i', current_time( 'timestamp' ) );
        $line  = sprintf(
            /* translators: 1: date/time, 2: user display name. */
            __( '[%1$s · %2$s] %3$s', 'agrocampo-post-venta' ),
            $stamp,
            '' !== $user_label ? $user_label : __( 'Usuario', 'agrocampo-post-venta' ),
            $new_text
        );

        if ( '' === $existing_text ) {
            return $line;
        }

        return $existing_text . "

" . $line;
    }

    public static function has_appended_observation_notes( string $text ): bool {
        $text = trim( $text );
        if ( '' === $text ) {
            return false;
        }

        return 1 === preg_match( '/\[\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}\s+·\s+.+?\]/u', $text );
    }

    public static function create_observation_note( int $submission_id, string $text ): int {
        return AGP_PV_DB::create_observation_note( $submission_id, $text );
    }

    public static function update_observation_note( int $submission_id, int $note_id, string $text ): bool {
        return AGP_PV_DB::update_observation_note( $submission_id, $note_id, $text );
    }

    public static function soft_delete_observation_note( int $submission_id, int $note_id ): bool {
        return AGP_PV_DB::soft_delete_observation_note( $submission_id, $note_id );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_observation_notes( int $submission_id ): array {
        return AGP_PV_DB::get_observation_notes( $submission_id );
    }

    /**
     * @return string[]
     */

    public static function get_machine_status_options(): array {
        return array(
            'operativo' => __( 'Operativo', 'agrocampo-post-venta' ),
            'detenido' => __( 'Detenido', 'agrocampo-post-venta' ),
            'operativo_con_pendiente' => __( 'Operativo con pendiente', 'agrocampo-post-venta' ),
        );
    }

    public static function get_machine_status_label( string $value ): string {
        $options = self::get_machine_status_options();
        $key     = self::parse_machine_status_value( $value );

        return $options[ $key ] ?? sanitize_text_field( $value );
    }

    public static function parse_machine_status_value( string $value ): string {
        $normalized = sanitize_key( $value );
        $options    = self::get_machine_status_options();

        if ( isset( $options[ $normalized ] ) ) {
            return $normalized;
        }

        $normalized_text = strtolower( trim( remove_accents( $value ) ) );
        foreach ( $options as $option_key => $option_label ) {
            if ( strtolower( trim( remove_accents( (string) $option_label ) ) ) === $normalized_text ) {
                return $option_key;
            }
        }

        return '';
    }

    public static function get_machine_status_field_position(): string {
        $position = (string) get_option( 'agp_pv_machine_status_position', 'before_observaciones' );
        return self::parse_machine_status_position( $position );
    }

    public static function parse_machine_status_position( string $position ): string {
        $allowed  = array( 'top', 'before_observaciones', 'bottom' );

        if ( ! in_array( $position, $allowed, true ) ) {
            return 'before_observaciones';
        }

        return $position;
    }

    /**
     * @return string[]
     */
    public static function get_lubricants_allowed_fields(): array {
        return array(
            'type',
            'product',
            'quantity',
            'code',
            'presentation',
            'description',
            'unit',
            'observation',
        );
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    public static function normalize_lubricants_visible_fields( $raw ): array {
        if ( is_string( $raw ) ) {
            $raw = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        }

        if ( ! is_array( $raw ) ) {
            $raw = array();
        }

        $allowed = self::get_lubricants_allowed_fields();
        $result  = array();
        foreach ( $raw as $field ) {
            $key = sanitize_key( (string) $field );
            if ( in_array( $key, $allowed, true ) ) {
                $result[ $key ] = $key;
            }
        }

        $result['type']     = 'type';
        $result['product']  = 'product';
        $result['quantity'] = 'quantity';

        $ordered = array();
        foreach ( $allowed as $field ) {
            if ( isset( $result[ $field ] ) ) {
                $ordered[] = $field;
            }
        }

        return $ordered;
    }

    /**
     * @return string[]
     */
    public static function get_lubricants_visible_fields(): array {
        $stored = get_option( 'agp_pv_lubricants_visible_fields', array( 'type', 'product', 'quantity' ) );
        return self::normalize_lubricants_visible_fields( $stored );
    }

    /**
     * @param mixed $raw
     * @return array<string, string>
     */
    public static function normalize_lubricants_field_states( $raw ): array {
        $allowed_fields = self::get_lubricants_allowed_fields();
        $allowed_states = array( 'visible', 'collapsed', 'hidden' );
        $states         = array();

        if ( ! is_array( $raw ) ) {
            $raw = array();
        }

        foreach ( $allowed_fields as $field ) {
            $value = isset( $raw[ $field ] ) ? sanitize_key( (string) $raw[ $field ] ) : '';
            if ( ! in_array( $value, $allowed_states, true ) ) {
                $value = in_array( $field, array( 'type', 'product', 'quantity' ), true ) ? 'visible' : 'hidden';
            }
            $states[ $field ] = $value;
        }

        $states['type']     = 'visible';
        $states['product']  = 'visible';
        $states['quantity'] = 'visible';

        return $states;
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public static function normalize_lubricants_settings( $raw ): array {
        $defaults = array(
            'usage_mode' => 'catalog_only',
            'field_states' => self::normalize_lubricants_field_states( array() ),
            'quick_types' => array(),
            'fallback_mode' => 'manual_only',
        );

        $usage_mode = isset( $raw['usage_mode'] ) ? sanitize_key( (string) $raw['usage_mode'] ) : '';
        if ( ! in_array( $usage_mode, array( 'catalog_only', 'mixed', 'manual_only' ), true ) ) {
            $usage_mode = $defaults['usage_mode'];
        }

        $fallback_mode = isset( $raw['fallback_mode'] ) ? sanitize_key( (string) $raw['fallback_mode'] ) : '';
        if ( ! in_array( $fallback_mode, array( 'manual_only', 'mixed', 'catalog_only' ), true ) ) {
            $fallback_mode = $defaults['fallback_mode'];
        }

        $field_states = self::normalize_lubricants_field_states( $raw['field_states'] ?? array() );

        $quick_types_raw = $raw['quick_types'] ?? array();
        if ( is_string( $quick_types_raw ) ) {
            $quick_types_raw = preg_split( '/\r\n|\r|\n/', $quick_types_raw ) ?: array();
        }
        if ( ! is_array( $quick_types_raw ) ) {
            $quick_types_raw = array();
        }

        $quick_types = array();
        foreach ( $quick_types_raw as $item ) {
            $type = '';
            $unit = '';

            if ( is_array( $item ) ) {
                $type = sanitize_text_field( (string) ( $item['type'] ?? '' ) );
                $unit = sanitize_text_field( (string) ( $item['unit'] ?? '' ) );
            } else {
                $line = sanitize_text_field( (string) $item );
                if ( false !== strpos( $line, '|' ) ) {
                    $parts = explode( '|', $line, 2 );
                    $type  = sanitize_text_field( trim( (string) $parts[0] ) );
                    $unit  = sanitize_text_field( trim( (string) $parts[1] ) );
                } else {
                    $type = $line;
                }
            }

            if ( '' === $type ) {
                continue;
            }

            $key = strtolower( $type );
            if ( isset( $quick_types[ $key ] ) ) {
                continue;
            }

            $quick_types[ $key ] = array(
                'type' => $type,
                'unit' => $unit,
            );
        }

        return array(
            'usage_mode' => $usage_mode,
            'field_states' => $field_states,
            'quick_types' => array_values( $quick_types ),
            'fallback_mode' => $fallback_mode,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_lubricants_settings(): array {
        $stored = get_option( 'agp_pv_lubricants_settings', array() );
        if ( is_array( $stored ) && ! empty( $stored ) ) {
            return self::normalize_lubricants_settings( $stored );
        }

        $legacy_visible = self::get_lubricants_visible_fields();
        $legacy_states  = array();
        foreach ( self::get_lubricants_allowed_fields() as $field ) {
            $legacy_states[ $field ] = in_array( $field, $legacy_visible, true ) ? 'visible' : 'hidden';
        }

        return self::normalize_lubricants_settings(
            array(
                'usage_mode' => 'catalog_only',
                'field_states' => $legacy_states,
                'quick_types' => array(),
                'fallback_mode' => 'manual_only',
            )
        );
    }

    public static function default_technicians(): array {
        return array(
            'Enrique Rivas Diaz',
            'Juan Castro Meza',
            'Bastián Cancino Ortega',
            'Maximiliano Tapia Briones',
            'Luis Zúñiga Medel',
            'Daniel Rojas Zúñiga',
            'Alejandro Vásquez Gonzales',
            'Darwin Reveco Vásquez',
            'Moisés Acevedo Abaca',
            'Jeremy Castillo Díaz',
            'Eduardo Espinoza',
            'Guillermo Jerez',
            'Jorge Valdez',
            'Benjamín Castro',
        );
    }

    /**
     * @return string[]
     */
    public static function get_technicians(): array {
        $stored = get_option( 'agp_pv_technicians', array() );
        $list = self::normalize_technicians( $stored );

        if ( empty( $list ) ) {
            return self::default_technicians();
        }

        return $list;
    }

    /**
     * @param string|string[] $raw
     * @return string[]
     */
    public static function normalize_technicians( $raw ): array {
        if ( is_string( $raw ) ) {
            $raw = preg_split( '/\r\n|\r|\n/', $raw ) ?: array();
        }

        if ( ! is_array( $raw ) ) {
            return array();
        }

        $items = array();
        foreach ( $raw as $row ) {
            $name = sanitize_text_field( (string) $row );
            if ( function_exists( 'mb_substr' ) ) {
                $name = (string) mb_substr( $name, 0, 80 );
            } else {
                $name = substr( $name, 0, 80 );
            }
            if ( '' === $name ) {
                continue;
            }
            $items[] = $name;
        }

        $items = array_values( array_unique( $items ) );
        if ( count( $items ) > 100 ) {
            $items = array_slice( $items, 0, 100 );
        }

        return $items;
    }

    public static function activate(): void {
        AGP_PV_DB::maybe_upgrade();
        self::ensure_persistent_lubricants_catalog();
        self::schedule_notification_events();
        self::register_rewrite_rules();
        flush_rewrite_rules();
        update_option( self::VERSION_OPTION_KEY, AGP_PV_VERSION );
        update_option( self::REWRITE_VERSION_OPTION_KEY, self::REWRITE_VERSION );
    }

    public static function deactivate(): void {
        self::clear_notification_events();
        flush_rewrite_rules();
    }

    public function maybe_upgrade(): void {
        AGP_PV_DB::maybe_upgrade();
        self::ensure_persistent_lubricants_catalog();

        $installed_version = (string) get_option( self::VERSION_OPTION_KEY, '' );
        if ( '' === $installed_version || version_compare( $installed_version, AGP_PV_VERSION, '<' ) ) {
            flush_rewrite_rules( false );
            update_option( self::VERSION_OPTION_KEY, AGP_PV_VERSION );
        }

        $rewrite_version = (string) get_option( self::REWRITE_VERSION_OPTION_KEY, '' );
        if ( self::REWRITE_VERSION !== $rewrite_version ) {
            flush_rewrite_rules( false );
            update_option( self::REWRITE_VERSION_OPTION_KEY, self::REWRITE_VERSION );
        }
    }

    public function register_rewrite(): void {
        self::register_rewrite_rules();
    }

    private static function register_rewrite_rules(): void {
        add_rewrite_rule( '^post-venta/?$', 'index.php?agp_pv_standalone=1', 'top' );
        add_rewrite_rule( '^post-venta-informes/?$', 'index.php?agp_pv_reports_standalone=1', 'top' );
        add_rewrite_rule( '^post-venta-observaciones/?$', 'index.php?agp_pv_observations_standalone=1', 'top' );
    }

    public function register_query_var( array $vars ): array {
        $vars[] = 'agp_pv_standalone';
        $vars[] = 'agp_pv_manifest';
        $vars[] = 'agp_pv_reports_standalone';
        $vars[] = 'agp_pv_observations_standalone';
        return $vars;
    }

    public static function reports_standalone_url(): string {
        return home_url( '/post-venta-informes/' );
    }

    public static function form_standalone_url(): string {
        return home_url( '/post-venta/' );
    }

    public function register_cron_schedules( array $schedules ): array {
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = array(
                'interval' => WEEK_IN_SECONDS,
                'display'  => __( 'Una vez por semana', 'agrocampo-post-venta' ),
            );
        }

        return $schedules;
    }

    public static function observations_standalone_url(): string {
        return home_url( '/post-venta-observaciones/' );
    }

    /**
     * @return array{enabled:int,token_hash:string,expires_at_gmt:string}
     */
    public static function get_public_observations_access_settings(): array {
        $stored = get_option( self::PUBLIC_OBSERVATIONS_ACCESS_OPTION_KEY, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }

        $enabled = ! empty( $stored['enabled'] ) ? 1 : 0;
        $token_hash = isset( $stored['token_hash'] ) ? (string) $stored['token_hash'] : '';
        $expires_at_gmt = isset( $stored['expires_at_gmt'] ) ? (string) $stored['expires_at_gmt'] : '';

        if ( '' !== $expires_at_gmt ) {
            $expires_ts = strtotime( $expires_at_gmt . ' UTC' );
            if ( false === $expires_ts ) {
                $expires_at_gmt = '';
            } else {
                $expires_at_gmt = gmdate( 'Y-m-d H:i:s', (int) $expires_ts );
            }
        }

        return array(
            'enabled' => $enabled,
            'token_hash' => $token_hash,
            'expires_at_gmt' => $expires_at_gmt,
        );
    }

    /**
     * @param array{enabled?:int,token_hash?:string,expires_at_gmt?:string} $settings
     */
    public static function update_public_observations_access_settings( array $settings ): void {
        $current = self::get_public_observations_access_settings();
        $normalized = array(
            'enabled' => ! empty( $settings['enabled'] ) ? 1 : 0,
            'token_hash' => isset( $settings['token_hash'] ) ? (string) $settings['token_hash'] : (string) $current['token_hash'],
            'expires_at_gmt' => isset( $settings['expires_at_gmt'] ) ? (string) $settings['expires_at_gmt'] : (string) $current['expires_at_gmt'],
        );
        update_option( self::PUBLIC_OBSERVATIONS_ACCESS_OPTION_KEY, $normalized );
    }

    public static function verify_public_observations_token( string $token ): bool {
        $token = trim( $token );
        if ( '' === $token ) {
            return false;
        }

        $settings = self::get_public_observations_access_settings();
        if ( 1 !== (int) $settings['enabled'] || '' === (string) $settings['token_hash'] || '' === (string) $settings['expires_at_gmt'] ) {
            return false;
        }

        $expires_ts = strtotime( (string) $settings['expires_at_gmt'] . ' UTC' );
        if ( false === $expires_ts || $expires_ts < time() ) {
            return false;
        }

        return wp_check_password( $token, (string) $settings['token_hash'] );
    }

    /**
     * @return array{is_public_read_only:bool,enabled:bool,expires_at_gmt:string,expires_in_human:string}
     */
    public static function get_public_observations_request_access_context(): array {
        $token = isset( $_GET['agp_public_token'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['agp_public_token'] ) ) : '';
        $is_public_read_only = self::verify_public_observations_token( $token ) && ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) );
        $settings = self::get_public_observations_access_settings();

        $expires_in_human = '';
        if ( $is_public_read_only && '' !== (string) $settings['expires_at_gmt'] ) {
            $expires_ts = strtotime( (string) $settings['expires_at_gmt'] . ' UTC' );
            if ( false !== $expires_ts ) {
                $remaining = $expires_ts - time();
                if ( $remaining > 0 ) {
                    $expires_in_human = human_time_diff( time(), $expires_ts );
                }
            }
        }

        return array(
            'is_public_read_only' => $is_public_read_only,
            'enabled' => 1 === (int) $settings['enabled'],
            'expires_at_gmt' => (string) $settings['expires_at_gmt'],
            'expires_in_human' => $expires_in_human,
        );
    }

    public static function get_observations_report_window_days(): int {
        $days = absint( get_option( 'agp_pv_observations_report_window_days', 30 ) );
        if ( $days < 1 ) {
            $days = 30;
        }

        return min( 3650, $days );
    }

    public static function get_observations_report_cutoff_mysql(): string {
        $days = self::get_observations_report_window_days();
        return current_datetime()->modify( '-' . $days . ' days' )->format( 'Y-m-d H:i:s' );
    }


    public static function get_observations_front_redirect_url(): string {
        $default = self::observations_standalone_url();
        $raw     = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

        if ( '' === $raw ) {
            return $default;
        }

        $validated = wp_validate_redirect( $raw, '' );

        return '' !== $validated ? $validated : $default;
    }

    public static function schedule_notification_events(): void {
        if ( ! wp_next_scheduled( 'agp_pv_daily_notifications' ) ) {
            wp_schedule_event( self::next_daily_notification_timestamp(), 'daily', 'agp_pv_daily_notifications' );
        }

        if ( ! wp_next_scheduled( 'agp_pv_weekly_summary' ) ) {
            wp_schedule_event( self::next_weekly_summary_timestamp(), 'weekly', 'agp_pv_weekly_summary' );
        }
    }

    public static function clear_notification_events(): void {
        wp_clear_scheduled_hook( 'agp_pv_daily_notifications' );
        wp_clear_scheduled_hook( 'agp_pv_weekly_summary' );
    }

    private static function next_daily_notification_timestamp(): int {
        $now = current_datetime();
        $target = $now->setTime( 9, 0, 0 );

        if ( $target <= $now ) {
            $target = $target->modify( '+1 day' );
        }

        return $target->getTimestamp();
    }

    private static function next_weekly_summary_timestamp(): int {
        $now = current_datetime();
        $target = $now->setTime( 9, 0, 0 );
        $weekday = (int) $target->format( 'N' );
        $days_until_monday = ( 8 - $weekday ) % 7;
        $target = $target->modify( '+' . $days_until_monday . ' days' );

        if ( $target <= $now ) {
            $target = $target->modify( '+7 days' );
        }

        return $target->getTimestamp();
    }

    public function maybe_schedule_notification_events(): void {
        self::schedule_notification_events();
    }

    public function handle_daily_notifications(): void {
        AGP_PV_Email::send_due_observation_notifications();
        AGP_PV_Email::send_period_summary( 'daily' );
    }

    public function handle_weekly_summary(): void {
        AGP_PV_Email::send_period_summary( 'weekly' );
    }

    private function is_manifest_request(): bool {
        return '1' === get_query_var( 'agp_pv_manifest' );
    }

    private function get_manifest_payload(): array {
        $payload = array(
            'name' => __( 'Informe Técnico Agrocampo', 'agrocampo-post-venta' ),
            'short_name' => __( 'Informe Técnico', 'agrocampo-post-venta' ),
            'description' => __( 'Formulario post venta de Agrocampo para captura de informes técnicos en terreno.', 'agrocampo-post-venta' ),
            'lang' => get_bloginfo( 'language' ) ? get_bloginfo( 'language' ) : 'es-CL',
            'start_url' => home_url( '/post-venta/' ),
            'scope' => home_url( '/post-venta/' ),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f5f8ff',
            'theme_color' => '#1f3f8f',
            'icons' => array(),
        );

        $icon_192_id = absint( get_option( 'agp_pv_app_icon_192_attachment_id', 0 ) );
        $icon_512_id = absint( get_option( 'agp_pv_app_icon_512_attachment_id', 0 ) );
        $icon_192_url = $icon_192_id ? wp_get_attachment_url( $icon_192_id ) : '';
        $icon_512_url = $icon_512_id ? wp_get_attachment_url( $icon_512_id ) : '';

        if ( $icon_192_url ) {
            $payload['icons'][] = array(
                'src' => esc_url_raw( $icon_192_url ),
                'sizes' => '192x192',
                'type' => get_post_mime_type( $icon_192_id ) ?: 'image/png',
                'purpose' => 'any maskable',
            );
        }

        if ( $icon_512_url ) {
            $payload['icons'][] = array(
                'src' => esc_url_raw( $icon_512_url ),
                'sizes' => '512x512',
                'type' => get_post_mime_type( $icon_512_id ) ?: 'image/png',
                'purpose' => 'any maskable',
            );
        }

        return $payload;
    }

    private function render_manifest(): void {
        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: application/manifest+json; charset=' . get_bloginfo( 'charset' ) );
        header( 'X-Content-Type-Options: nosniff' );

        echo wp_json_encode( $this->get_manifest_payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    }

    public function is_standalone(): bool {
        return '1' === get_query_var( 'agp_pv_standalone' );
    }

    public function is_observations_standalone(): bool {
        return '1' === get_query_var( 'agp_pv_observations_standalone' );
    }

    public function is_reports_standalone(): bool {
        return '1' === get_query_var( 'agp_pv_reports_standalone' );
    }

    private function process_observations_review_action(): void {
        if ( ! isset( $_POST['agp_pv_front_action'] ) ) {
            return;
        }

        $action = sanitize_key( wp_unslash( $_POST['agp_pv_front_action'] ) );
        if ( ! in_array( $action, array( 'mark_reviewed', 'unmark_reviewed', 'append_observation', 'edit_observation_note', 'delete_observation_note' ), true ) ) {
            return;
        }

        $submission_id = isset( $_POST['submission_id'] ) ? absint( wp_unslash( $_POST['submission_id'] ) ) : 0;
        if ( $submission_id <= 0 ) {
            wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'review_failed', self::get_observations_front_redirect_url() ) );
            exit;
        }

        if ( 'mark_reviewed' === $action ) {
            if ( ! check_admin_referer( 'agp_pv_front_mark_reviewed' ) ) {
                wp_die( esc_html__( 'Solicitud inválida.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
            }
            $submission = AGP_PV_Email::get_submission( $submission_id );
            if ( ! $submission ) {
                wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'review_failed', self::get_observations_front_redirect_url() ) );
                exit;
            }

            global $wpdb;

            $updated = $wpdb->update(
                AGP_PV_DB::table_name(),
                array(
                    'review_status' => 'reviewed',
                    'reviewed_by' => get_current_user_id(),
                    'reviewed_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $submission_id ),
                array( '%s', '%d', '%s', '%s' ),
                array( '%d' )
            );

            if ( false === $updated ) {
                wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'review_failed', self::get_observations_front_redirect_url() ) );
                exit;
            }

            wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'review_marked', self::get_observations_front_redirect_url() ) );
            exit;
        }

        if ( 'unmark_reviewed' === $action ) {
            if ( ! check_admin_referer( 'agp_pv_front_unmark_reviewed' ) ) {
                wp_die( esc_html__( 'Solicitud inválida.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
            }

            $submission = AGP_PV_Email::get_submission( $submission_id );
            if ( ! $submission || ! self::is_reviewed_observation_status( (string) ( $submission['review_status'] ?? '' ) ) ) {
                wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'review_unmark_failed', self::get_observations_front_redirect_url() ) );
                exit;
            }
            global $wpdb;

            $updated = $wpdb->update(
                AGP_PV_DB::table_name(),
                array(
                    'review_status' => self::resolve_review_status_for_observation( (string) ( $submission['observaciones'] ?? '' ), '' ),
                    'reviewed_by' => 0,
                    'reviewed_at' => null,
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $submission_id ),
                array( '%s', '%d', '%s', '%s' ),
                array( '%d' )
            );

            if ( false === $updated ) {
                wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'review_unmark_failed', self::get_observations_front_redirect_url() ) );
                exit;
            }

            wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'review_unmarked', self::get_observations_front_redirect_url() ) );
            exit;
        }

        if ( 'edit_observation_note' === $action ) {
            if ( ! check_admin_referer( 'agp_pv_front_edit_observation_note' ) ) {
                wp_die( esc_html__( 'Solicitud inválida.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
            }

            $submission = AGP_PV_Email::get_submission( $submission_id );
            if ( ! $submission || ! self::is_reviewed_observation_status( (string) ( $submission['review_status'] ?? '' ) ) ) {
                wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_note_failed', self::get_observations_front_redirect_url() ) );
                exit;
            }

            $note_id  = isset( $_POST['note_id'] ) ? absint( wp_unslash( $_POST['note_id'] ) ) : 0;
            $new_text = self::normalize_observation_text( sanitize_textarea_field( wp_unslash( $_POST['observation_note'] ?? '' ) ) );

            if ( $note_id <= 0 || '' === $new_text || ! self::update_observation_note( $submission_id, $note_id, $new_text ) ) {
                wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_note_failed', self::get_observations_front_redirect_url() ) );
                exit;
            }

            wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_note_updated', self::get_observations_front_redirect_url() ) );
            exit;
        }

        if ( 'delete_observation_note' === $action ) {
            if ( ! check_admin_referer( 'agp_pv_front_delete_observation_note' ) ) {
                wp_die( esc_html__( 'Solicitud inválida.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
            }

            $submission = AGP_PV_Email::get_submission( $submission_id );
            if ( ! $submission || ! self::is_reviewed_observation_status( (string) ( $submission['review_status'] ?? '' ) ) ) {
                wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_note_failed', self::get_observations_front_redirect_url() ) );
                exit;
            }

            $note_id = isset( $_POST['note_id'] ) ? absint( wp_unslash( $_POST['note_id'] ) ) : 0;
            if ( $note_id <= 0 || ! self::soft_delete_observation_note( $submission_id, $note_id ) ) {
                wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_note_failed', self::get_observations_front_redirect_url() ) );
                exit;
            }

            wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_note_deleted', self::get_observations_front_redirect_url() ) );
            exit;
        }

        if ( ! check_admin_referer( 'agp_pv_front_append_observation' ) ) {
            wp_die( esc_html__( 'Solicitud inválida.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
        }

        $submission = AGP_PV_Email::get_submission( $submission_id );
        if ( ! $submission || ! self::is_reviewed_observation_status( (string) ( $submission['review_status'] ?? '' ) ) ) {
            wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_note_failed', self::get_observations_front_redirect_url() ) );
            exit;
        }

        $new_note   = self::normalize_observation_text( sanitize_textarea_field( wp_unslash( $_POST['observation_note'] ?? '' ) ) );

        if ( '' === $new_note ) {
            wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_note_failed', self::get_observations_front_redirect_url() ) );
            exit;
        }

        $created_id = self::create_observation_note( $submission_id, $new_note );
        if ( $created_id <= 0 ) {
            wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_note_failed', self::get_observations_front_redirect_url() ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_note_created', self::get_observations_front_redirect_url() ) );
        exit;
    }

    public function render_standalone(): void {
        if ( $this->is_manifest_request() ) {
            $this->render_manifest();
        }

        if ( ! $this->is_standalone() ) {
            if ( ! $this->is_observations_standalone() && ! $this->is_reports_standalone() ) {
                return;
            }

            $is_admin_session = is_user_logged_in() && current_user_can( 'manage_options' );
            $public_access_context = $this->is_observations_standalone() ? self::get_public_observations_request_access_context() : array(
                'is_public_read_only' => false,
                'enabled' => false,
                'expires_at_gmt' => '',
                'expires_in_human' => '',
            );
            $is_public_read_only = ! $is_admin_session && ! empty( $public_access_context['is_public_read_only'] );

            if ( ! $is_admin_session && ! $is_public_read_only ) {
                wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
            }

            $request_method = strtoupper( sanitize_text_field( wp_unslash( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) );
            if ( $is_public_read_only && 'GET' !== $request_method ) {
                wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
            }

            if ( $this->is_observations_standalone() && 'POST' === $request_method ) {
                $this->process_observations_review_action();
            }

            status_header( 200 );
            nocache_headers();
            if ( $is_public_read_only ) {
                header( 'X-Robots-Tag: noindex, nofollow', true );
            }

            if ( $this->is_reports_standalone() ) {
                $template = AGP_PV_PLUGIN_DIR . 'templates/reports-standalone.php';
                if ( file_exists( $template ) ) {
                    include $template;
                    exit;
                }

                return;
            }

            $template = AGP_PV_PLUGIN_DIR . 'templates/observations-standalone.php';
            if ( file_exists( $template ) ) {
                include $template;
                exit;
            }

            return;
        }

        status_header( 200 );
        nocache_headers();

        $template = AGP_PV_PLUGIN_DIR . 'templates/standalone.php';
        if ( file_exists( $template ) ) {
            include $template;
            exit;
        }
    }

    public function enqueue_assets(): void {
        if ( $this->is_observations_standalone() || $this->is_reports_standalone() ) {
            wp_enqueue_style(
                'agp-pv-observations-standalone',
                AGP_PV_PLUGIN_URL . 'assets/css/observations-standalone.css',
                array(),
                AGP_PV_VERSION
            );

            return;
        }

        if ( ! $this->is_standalone() ) {
            return;
        }

        wp_enqueue_style(
            'agp-pv-standalone',
            AGP_PV_PLUGIN_URL . 'assets/css/standalone.css',
            array(),
            AGP_PV_VERSION
        );

        wp_enqueue_script(
            'agp-pv-standalone',
            AGP_PV_PLUGIN_URL . 'assets/js/standalone.js',
            array( 'jquery' ),
            AGP_PV_VERSION,
            true
        );

        wp_localize_script(
            'agp-pv-standalone',
            'agpPvData',
            array(
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'agp_pv_submit' ),
                'debugFrontend' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
                'detalleMinimumChars' => 10,
                'machineStatusPendingValue' => 'operativo_con_pendiente',
                'lubricantsCatalog' => self::get_lubricants_catalog(),
                'lubricantsSettings' => self::get_lubricants_settings(),
                'lubricantsVisibleFields' => self::get_lubricants_visible_fields(),
                'serviceWorker' => array(
                    'url' => AGP_PV_PLUGIN_URL . 'assets/js/standalone-sw.js',
                    'scope' => home_url( '/post-venta/' ),
                    'version' => AGP_PV_VERSION,
                    'shellPath' => wp_parse_url( home_url( '/post-venta/' ), PHP_URL_PATH ) ?: '/post-venta/',
                    'assetPrefix' => wp_parse_url( AGP_PV_PLUGIN_URL . 'assets/', PHP_URL_PATH ) ?: '/wp-content/plugins/agrocampo-post-venta/assets/',
                ),
                'settings' => array(
                    'hasFixedJefeTallerSignature' => absint( get_option( 'agp_pv_jefe_taller_firma_attachment_id', 0 ) ) > 0,
                ),
                'messages' => array(
                    'invalid' => __( 'Error: tu formulario no es válido, ¡por favor, corrige los errores!', 'agrocampo-post-venta' ),
                    'success' => __( 'Informe enviado.', 'agrocampo-post-venta' ),
                    'successMailWarning' => __( 'Informe guardado correctamente, pero NO se pudo enviar el correo.', 'agrocampo-post-venta' ),
                    'successPdfWarning' => __( 'Informe enviado, pero el PDF no pudo adjuntarse.', 'agrocampo-post-venta' ),
                    'reportIdPrefix' => __( 'ID informe', 'agrocampo-post-venta' ),
                    'statusReviewFieldsBeforeContinue' => __( 'Revisa los campos marcados antes de continuar.', 'agrocampo-post-venta' ),
                    'statusReviewFields' => __( 'Revisa los campos marcados.', 'agrocampo-post-venta' ),
                    'errorSummaryTitle' => __( 'Revisa los siguientes campos antes de continuar:', 'agrocampo-post-venta' ),
                    'statusSending' => __( 'Enviando...', 'agrocampo-post-venta' ),
                    'draftRecovered' => __( 'Se recuperó un borrador local.', 'agrocampo-post-venta' ),
                    'draftCleared' => __( 'Borrador local eliminado.', 'agrocampo-post-venta' ),
                    'photosMaxCount' => __( 'Máximo 10 fotos.', 'agrocampo-post-venta' ),
                    'photosMaxCountTrimmed' => __( 'Máximo 10 fotos. El resto fue descartado.', 'agrocampo-post-venta' ),
                    'photoMaxSize' => __( 'Cada foto debe pesar máximo 5 MB.', 'agrocampo-post-venta' ),
                    'photosTotalLimit' => __( 'Límite total alcanzado: 20 MB.', 'agrocampo-post-venta' ),
                    'photoOmittedInvalidFormat' => __( 'Se omitió "%s": formato no permitido.', 'agrocampo-post-venta' ),
                    'photoOmittedTooLarge' => __( 'Se omitió "%s": supera 5 MB.', 'agrocampo-post-venta' ),
                    'fieldMaxRemainingTemplate' => __( 'Máximo %1$d caracteres (%2$d restantes)', 'agrocampo-post-venta' ),
                    'fieldMaxUsedTemplate' => __( '%1$d/%2$d caracteres', 'agrocampo-post-venta' ),
                    'fieldMaxReachedTemplate' => __( 'Has alcanzado el máximo de %d caracteres.', 'agrocampo-post-venta' ),
                    'detalleAtLeastOneMinChars' => __( 'Completa “Trabajos realizados” u “Observaciones” con al menos %d caracteres.', 'agrocampo-post-venta' ),
                    'stepStateEmpty' => __( 'vacío', 'agrocampo-post-venta' ),
                    'stepStateInProgress' => __( 'en progreso', 'agrocampo-post-venta' ),
                    'stepStateComplete' => __( 'completo', 'agrocampo-post-venta' ),
                    'stepStateSubmitted' => __( 'enviado', 'agrocampo-post-venta' ),
                    'stepStateSubmittedWarning' => __( 'enviado con advertencia', 'agrocampo-post-venta' ),
                    'submittedStepTitle' => __( 'Envío completado', 'agrocampo-post-venta' ),
                    'submittedStepCta' => __( 'Iniciar nuevo envío', 'agrocampo-post-venta' ),
                    'submittedStepFallbackMessage' => __( 'Tu informe fue enviado. Puedes iniciar un nuevo envío cuando lo necesites.', 'agrocampo-post-venta' ),
                    'submittedStepMetaSuccess' => __( 'Guardamos tu informe correctamente.', 'agrocampo-post-venta' ),
                    'submittedStepMetaWarning' => __( 'El informe quedó guardado con advertencias revisables en el mensaje superior.', 'agrocampo-post-venta' ),
                    'submittedStepCopyReport' => __( 'Copiar ID informe', 'agrocampo-post-venta' ),
                    'submittedStepCopySuccess' => __( 'ID informe copiado al portapapeles.', 'agrocampo-post-venta' ),
                    'submittedStepCopyUnavailable' => __( 'No se pudo copiar automáticamente. Puedes copiarlo manualmente desde el mensaje.', 'agrocampo-post-venta' ),
                    'appShortcutAvailable' => __( 'Instala este formulario como acceso directo para abrirlo como app desde tu dispositivo.', 'agrocampo-post-venta' ),
                    'appShortcutInstallButton' => __( 'Agregar acceso directo', 'agrocampo-post-venta' ),
                    'appShortcutIos' => __( 'En iPhone/iPad: toca Compartir y luego “Agregar a pantalla de inicio”.', 'agrocampo-post-venta' ),
                    'appShortcutManual' => __( 'En tu navegador, abre el menú y elige “Instalar app” o “Agregar a pantalla de inicio”.', 'agrocampo-post-venta' ),
                    'appShortcutInstalled' => __( 'Este formulario ya está abierto como app.', 'agrocampo-post-venta' ),
                    'swUpdateAvailable' => __( 'Hay una nueva versión disponible del formulario.', 'agrocampo-post-venta' ),
                    'swUpdateCta' => __( 'Actualizar ahora', 'agrocampo-post-venta' ),
                    'swUpdateReloading' => __( 'Actualizando…', 'agrocampo-post-venta' ),
                    'networkOnline' => __( 'Conexión disponible.', 'agrocampo-post-venta' ),
                    'networkOffline' => __( 'Sin conexión. Puedes completar el formulario y reintentar el envío cuando vuelva Internet.', 'agrocampo-post-venta' ),
                    'networkOfflineSubmitBlocked' => __( 'Sin conexión. Guardamos el envío como pendiente para que puedas reintentarlo.', 'agrocampo-post-venta' ),
                    'networkOfflineRetryBlocked' => __( 'Sin conexión. No se puede reintentar todavía.', 'agrocampo-post-venta' ),
                    'pendingReadyToRetry' => __( 'Hay un envío pendiente listo para reintentar.', 'agrocampo-post-venta' ),
                    'pendingRetrying' => __( 'Reintentando envío pendiente...', 'agrocampo-post-venta' ),
                ),
            )
        );
    }
}
