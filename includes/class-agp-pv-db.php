<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AGP_PV_DB {
    public const VERSION = '1.8.0';
    public const OPTION_KEY = 'agp_pv_db_version';

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'agp_pv_submissions';
    }

    public static function maybe_upgrade(): void {
        $installed = get_option( self::OPTION_KEY );
        if ( self::VERSION !== $installed ) {
            self::create_table();
            update_option( self::OPTION_KEY, self::VERSION );
        }
    }

    public static function create_table(): void {
        global $wpdb;
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            legacy_id BIGINT UNSIGNED DEFAULT 0,
            tecnico VARCHAR(255) NOT NULL,
            cliente VARCHAR(255) NOT NULL,
            email_cliente VARCHAR(255) DEFAULT '',
            faena_lugar VARCHAR(255) DEFAULT '',
            jefe_taller_nombre VARCHAR(255) DEFAULT '',
            maquina VARCHAR(255) DEFAULT '',
            modelo VARCHAR(255) DEFAULT '',
            serie VARCHAR(255) DEFAULT '',
            numero_interno VARCHAR(255) DEFAULT '',
            fecha VARCHAR(20) DEFAULT '',
            horas VARCHAR(255) DEFAULT '',
            tipo_servicio VARCHAR(255) DEFAULT '',
            tipo_servicio_label VARCHAR(255) DEFAULT '',
            tipo_mantencion VARCHAR(255) DEFAULT '',
            tipo_mantencion_label VARCHAR(255) DEFAULT '',
            cantidad_horas VARCHAR(255) DEFAULT '',
            fecha_reparacion VARCHAR(20) DEFAULT '',
            fecha_cierre VARCHAR(20) DEFAULT '',
            lubricantes TEXT,
            filtros_utilizados TEXT,
            componentes_utilizados TEXT,
            trabajos_realizados TEXT,
            estado_maquina VARCHAR(40) DEFAULT '',
            fecha_contacto VARCHAR(20) DEFAULT '',
            observaciones TEXT,
            firma_cliente_id BIGINT UNSIGNED DEFAULT 0,
            firma_tecnico_id BIGINT UNSIGNED DEFAULT 0,
            firma_jefe_taller_id BIGINT UNSIGNED DEFAULT 0,
            fotos_ids LONGTEXT,
            pdf_attachment_id BIGINT UNSIGNED DEFAULT 0,
            pdf_status VARCHAR(20) DEFAULT 'pending',
            pdf_error VARCHAR(255) DEFAULT '',
            pdf_last_attempt_at DATETIME NULL,
            pdf_generated_ms INT UNSIGNED DEFAULT 0,
            pdf_size_bytes BIGINT UNSIGNED DEFAULT 0,
            pdf_page_count SMALLINT UNSIGNED DEFAULT 0,
            pdf_warnings LONGTEXT,
            correo_copia VARCHAR(255) DEFAULT '',
            mail_status VARCHAR(20) DEFAULT 'pending',
            mail_error VARCHAR(255) DEFAULT '',
            mail_last_attempt_at DATETIME NULL,
            review_status VARCHAR(20) DEFAULT 'not_required',
            reviewed_by BIGINT UNSIGNED DEFAULT 0,
            reviewed_at DATETIME NULL,
            overdue_notified_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY legacy_id (legacy_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function get_visible_report_id( array $submission ): int {
        $legacy_id = isset( $submission['legacy_id'] ) ? absint( $submission['legacy_id'] ) : 0;
        if ( $legacy_id > 0 ) {
            return $legacy_id;
        }

        return isset( $submission['id'] ) ? absint( $submission['id'] ) : 0;
    }

    public static function update_mail_status( int $submission_id, string $status, string $error = '' ): void {
        global $wpdb;
        $table = self::table_name();

        $wpdb->update(
            $table,
            array(
                'mail_status' => $status,
                'mail_error' => $error,
                'mail_last_attempt_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $submission_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function update_pdf_attachment( int $submission_id, int $attachment_id ): void {
        global $wpdb;
        $table = self::table_name();

        $wpdb->update(
            $table,
            array(
                'pdf_attachment_id' => $attachment_id,
                'pdf_last_attempt_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $submission_id ),
            array( '%d', '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function update_pdf_status( int $submission_id, string $status, string $error = '' ): void {
        global $wpdb;
        $table = self::table_name();

        $wpdb->update(
            $table,
            array(
                'pdf_status' => $status,
                'pdf_error' => $error,
                'pdf_last_attempt_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $submission_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function update_pdf_metrics( int $submission_id, array $metrics ): void {
        global $wpdb;
        $table = self::table_name();

        $warnings = array();
        if ( isset( $metrics['warnings'] ) && is_array( $metrics['warnings'] ) ) {
            $warnings = array_map( 'strval', $metrics['warnings'] );
        }

        $warnings_json = wp_json_encode( $warnings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( false === $warnings_json ) {
            $warnings_json = '[]';
        }

        $wpdb->update(
            $table,
            array(
                'pdf_generated_ms' => max( 0, (int) ( $metrics['elapsed_ms'] ?? 0 ) ),
                'pdf_size_bytes' => max( 0, (int) ( $metrics['size_bytes'] ?? 0 ) ),
                'pdf_page_count' => max( 0, (int) ( $metrics['page_count'] ?? 0 ) ),
                'pdf_warnings' => $warnings_json,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $submission_id ),
            array( '%d', '%d', '%d', '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function update_overdue_notified_at( int $submission_id ): void {
        global $wpdb;
        $table = self::table_name();

        $wpdb->update(
            $table,
            array(
                'overdue_notified_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $submission_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }
}
