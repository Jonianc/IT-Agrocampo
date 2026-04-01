<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AGP_PV_Plugin {
    private static ?AGP_PV_Plugin $instance = null;
    private const VERSION_OPTION_KEY = 'agp_pv_plugin_version';
    private const REWRITE_VERSION_OPTION_KEY = 'agp_pv_rewrite_version';
    private const REWRITE_VERSION = '2';

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
        $allowed  = array( 'top', 'before_observaciones', 'bottom' );

        if ( ! in_array( $position, $allowed, true ) ) {
            return 'before_observaciones';
        }

        return $position;
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
        if ( ! in_array( $action, array( 'mark_reviewed', 'append_observation' ), true ) ) {
            return;
        }

        $submission_id = isset( $_POST['submission_id'] ) ? absint( wp_unslash( $_POST['submission_id'] ) ) : 0;
        if ( $submission_id <= 0 ) {
            wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'review_failed', self::get_observations_front_redirect_url() ) );
            exit;
        }

        global $wpdb;

        if ( 'mark_reviewed' === $action ) {
            if ( ! check_admin_referer( 'agp_pv_front_mark_reviewed' ) ) {
                wp_die( esc_html__( 'Solicitud inválida.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
            }

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

        if ( ! check_admin_referer( 'agp_pv_front_append_observation' ) ) {
            wp_die( esc_html__( 'Solicitud inválida.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
        }

        $submission = AGP_PV_Email::get_submission( $submission_id );
        $new_note   = self::normalize_observation_text( sanitize_textarea_field( wp_unslash( $_POST['observation_note'] ?? '' ) ) );

        if ( ! $submission || '' === $new_note ) {
            wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_append_failed', self::get_observations_front_redirect_url() ) );
            exit;
        }

        $user      = wp_get_current_user();
        $user_name = $user instanceof WP_User ? (string) $user->display_name : '';
        $combined  = self::append_observation_note( (string) ( $submission['observaciones'] ?? '' ), $new_note, $user_name );
        $status    = (string) ( $submission['review_status'] ?? '' );

        $updated = $wpdb->update(
            AGP_PV_DB::table_name(),
            array(
                'observaciones' => $combined,
                'review_status' => self::is_reviewed_observation_status( $status ) ? 'reviewed' : self::resolve_review_status_for_observation( $combined, $status ),
                'updated_at'    => current_time( 'mysql' ),
            ),
            array( 'id' => $submission_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $updated ) {
            wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_append_failed', self::get_observations_front_redirect_url() ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'agp_pv_notice', 'observation_appended', self::get_observations_front_redirect_url() ) );
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

            if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'No autorizado.', 'agrocampo-post-venta' ), esc_html__( 'Acceso denegado', 'agrocampo-post-venta' ), array( 'response' => 403 ) );
            }

            if ( $this->is_observations_standalone() && 'POST' === strtoupper( sanitize_text_field( wp_unslash( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) ) {
                $this->process_observations_review_action();
            }

            status_header( 200 );
            nocache_headers();

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
                'serviceWorker' => array(
                    'url' => AGP_PV_PLUGIN_URL . 'assets/js/standalone-sw.js',
                    'scope' => home_url( '/post-venta/' ),
                    'version' => AGP_PV_VERSION,
                    'shellPath' => wp_parse_url( home_url( '/post-venta/' ), PHP_URL_PATH ) ?: '/post-venta/',
                    'assetPrefix' => wp_parse_url( AGP_PV_PLUGIN_URL . 'assets/', PHP_URL_PATH ) ?: '/wp-content/plugins/agrocampo-post-venta/assets/',
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
