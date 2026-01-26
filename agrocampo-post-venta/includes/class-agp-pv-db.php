<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AGP_PV_DB {
    public const VERSION = '1.0.0';
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
            tecnico VARCHAR(255) NOT NULL,
            cliente VARCHAR(255) NOT NULL,
            email_cliente VARCHAR(255) DEFAULT '',
            faena_lugar VARCHAR(255) DEFAULT '',
            maquina VARCHAR(255) DEFAULT '',
            modelo VARCHAR(255) DEFAULT '',
            serie VARCHAR(255) DEFAULT '',
            numero_interno VARCHAR(255) DEFAULT '',
            fecha VARCHAR(20) DEFAULT '',
            horas VARCHAR(255) DEFAULT '',
            tipo_servicio VARCHAR(255) DEFAULT '',
            tipo_mantencion VARCHAR(255) DEFAULT '',
            cantidad_horas VARCHAR(255) DEFAULT '',
            fecha_reparacion VARCHAR(20) DEFAULT '',
            fecha_cierre VARCHAR(20) DEFAULT '',
            lubricantes TEXT,
            filtros_utilizados TEXT,
            componentes_utilizados TEXT,
            trabajos_realizados TEXT,
            observaciones TEXT,
            firma_cliente_id BIGINT UNSIGNED DEFAULT 0,
            firma_tecnico_id BIGINT UNSIGNED DEFAULT 0,
            fotos_ids LONGTEXT,
            correo_copia VARCHAR(255) DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
