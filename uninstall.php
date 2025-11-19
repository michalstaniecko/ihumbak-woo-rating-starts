<?php
/**
 * Uninstall script
 * Fired when the plugin is uninstalled
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete custom database table
global $wpdb;
$table_name = $wpdb->prefix . 'woo_quick_ratings';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete plugin options
delete_option('ihumbak_wrs_enabled');
delete_option('ihumbak_wrs_require_login');
delete_option('ihumbak_wrs_admin_only');
delete_option('ihumbak_wrs_widget_position');
delete_option('ihumbak_wrs_show_count');
delete_option('ihumbak_wrs_star_color');
delete_option('ihumbak_wrs_text_rate');
delete_option('ihumbak_wrs_text_thanks');
delete_option('ihumbak_wrs_db_version');

// Delete transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ihumbak_wrs_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ihumbak_wrs_%'");

// Clear cache
wp_cache_flush();
