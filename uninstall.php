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
delete_option('ihumbak_wrs_hide_count_in_loop');
delete_option('ihumbak_wrs_star_color');
delete_option('ihumbak_wrs_text_rate');
delete_option('ihumbak_wrs_text_thanks');
delete_option('ihumbak_wrs_db_version');

// Delete email settings options (issue #3)
delete_option('ihumbak_wrs_email_enabled');
delete_option('ihumbak_wrs_email_trigger_status');
delete_option('ihumbak_wrs_email_delay_days');
delete_option('ihumbak_wrs_email_skip_refunded');
delete_option('ihumbak_wrs_email_skip_already_rated');
delete_option('ihumbak_wrs_email_excluded_products');
delete_option('ihumbak_wrs_email_excluded_categories');
delete_option('ihumbak_wrs_email_subject');
delete_option('ihumbak_wrs_email_heading');
delete_option('ihumbak_wrs_email_body');
delete_option('ihumbak_wrs_email_from_name');
delete_option('ihumbak_wrs_email_from_email');
delete_option('ihumbak_wrs_email_reply_to');
delete_option('ihumbak_wrs_email_coupon_id');
delete_option('ihumbak_wrs_email_coupon_mode');
delete_option('ihumbak_wrs_email_coupon_auto_discount');
delete_option('ihumbak_wrs_email_coupon_auto_validity_days');
delete_option('ihumbak_wrs_email_followups');

// Delete transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ihumbak_wrs_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ihumbak_wrs_%'");

// Usuń meta klucz _ihumbak_wrs_generated_coupon_id z zamówień.
// Klasyczna tabela wp_postmeta (gdy HPOS nieaktywne lub w trybie dual).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete(
    $wpdb->postmeta,
    array( 'meta_key' => '_ihumbak_wrs_generated_coupon_id' ),
    array( '%s' )
);

// Tabela HPOS wc_orders_meta — usuń tylko jeśli istnieje.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$hpos_table = $wpdb->prefix . 'wc_orders_meta';
$hpos_exists = $wpdb->get_var(
    $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_table )
);
if ( $hpos_exists === $hpos_table ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete(
        $hpos_table,
        array( 'meta_key' => '_ihumbak_wrs_generated_coupon_id' ),
        array( '%s' )
    );
}
// Uwaga: auto-wygenerowane kupony (shop_coupon) NIE są usuwane —
// klienci mogą nadal posiadać kody kuponów.

// Clear cache
wp_cache_flush();
