<?php
/**
 * Database Migration
 * Handles creation and updates of plugin tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ihumbak_WRS_Database_Migration {

    private $table_name;
    private $email_log_table;
    private $charset_collate;

    public function __construct() {
        global $wpdb;
        $this->table_name      = $wpdb->prefix . 'woo_quick_ratings';
        $this->email_log_table = $wpdb->prefix . 'woo_quick_ratings_email_log';
        $this->charset_collate = $wpdb->get_charset_collate();
    }
    
    /**
     * Create plugin tables
     */
    public function create_tables() {
        global $wpdb;
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            rating tinyint(1) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY ip_address (ip_address),
            KEY product_user (product_id, user_id),
            KEY product_ip (product_id, ip_address)
        ) {$this->charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Store database version
        update_option('ihumbak_wrs_db_version', IHUMBAK_WRS_VERSION);
    }
    
    /**
     * Tworzy tabelę logów wysyłki e-mail.
     *
     * Idempotentna — bezpieczne do wielokrotnego wywołania dzięki dbDelta().
     */
    public function create_email_log_table(): void {
        $sql = "CREATE TABLE {$this->email_log_table} (
            id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id        bigint(20) unsigned NOT NULL,
            customer_email  varchar(190) NOT NULL DEFAULT '',
            step            smallint(5) unsigned NOT NULL DEFAULT 0,
            status          varchar(20) NOT NULL DEFAULT '',
            reason          text NULL,
            created_at      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY created_at (created_at),
            KEY status (status)
        ) {$this->charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Usuwa tabelę logów wysyłki e-mail (używane przy odinstalowaniu).
     */
    public function drop_email_log_table(): void {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$this->email_log_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
    }

    /**
     * Sprawdza, czy tabela logów e-mail istnieje.
     *
     * @return bool True gdy tabela istnieje.
     */
    public function email_log_table_exists(): bool {
        global $wpdb;
        $table = $this->email_log_table;
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Drop plugin tables (for uninstall)
     */
    public function drop_tables() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
        delete_option('ihumbak_wrs_db_version');
    }
    
    /**
     * Check if tables exist
     */
    public function tables_exist() {
        global $wpdb;
        $table_name = $this->table_name;
        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }
}
