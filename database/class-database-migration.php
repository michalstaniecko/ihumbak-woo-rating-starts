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
    private $charset_collate;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'woo_quick_ratings';
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
