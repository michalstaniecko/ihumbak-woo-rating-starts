<?php
/**
 * Plugin Name: WooCommerce Quick Ratings & Reviews
 * Plugin URI: https://github.com/ihumbak/woo-rating-stars
 * Description: System szybkich ocen gwiazdkowych dla WooCommerce z integracją recenzji
 * Version: 1.0.4
 * Author: iHumbak
 * Author URI: https://ihumbak.com
 * Text Domain: ihumbak-woo-rating-stars
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('IHUMBAK_WRS_VERSION', '1.0.4');
define('IHUMBAK_WRS_PLUGIN_FILE', __FILE__);
define('IHUMBAK_WRS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IHUMBAK_WRS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IHUMBAK_WRS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
require_once IHUMBAK_WRS_PLUGIN_DIR . 'includes/class-autoloader.php';

/**
 * Main plugin class
 */
final class Ihumbak_WooCommerce_Rating_Stars {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        //$this->check_requirements();
        $this->init_hooks();
    }

    private function check_requirements() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
    }

    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' .
             esc_html__('WooCommerce Quick Ratings & Reviews', 'ihumbak-woo-rating-stars') .
             '</strong> ' .
             esc_html__('requires WooCommerce to be installed and active.', 'ihumbak-woo-rating-stars') .
             '</p></div>';
    }

    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init'), 10);
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Load text domain
        load_plugin_textdomain('ihumbak-woo-rating-stars', false, dirname(IHUMBAK_WRS_PLUGIN_BASENAME) . '/languages');

        // Initialize components
        $this->init_components();
    }

    private function init_components() {
        // Database
        new Ihumbak_WRS_Database_Migration();

        // Backend
        new Ihumbak_WRS_Rating_Model();
        new Ihumbak_WRS_Rating_Calculator();
        new Ihumbak_WRS_REST_API_Handler();
        new Ihumbak_WRS_WooCommerce_Integration();
        
        // SEO & Schema.org
        new Ihumbak_WRS_Schema_Markup();

        // Admin
        if (is_admin()) {
            new Ihumbak_WRS_Admin_Panel();
            new Ihumbak_WRS_Admin_Settings();
        }

        // Frontend
        if (!is_admin()) {
            new Ihumbak_WRS_Frontend_Render();
            new Ihumbak_WRS_Assets_Manager();
        }
    }

    public function activate() {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(IHUMBAK_WRS_PLUGIN_BASENAME);
            wp_die(
                esc_html__('WooCommerce Quick Ratings & Reviews requires WooCommerce to be installed and active.', 'ihumbak-woo-rating-stars'),
                'Plugin dependency check',
                array('back_link' => true)
            );
        }

        require_once IHUMBAK_WRS_PLUGIN_DIR . 'database/class-database-migration.php';
        $migration = new Ihumbak_WRS_Database_Migration();
        $migration->create_tables();

        // Set default options
        add_option('ihumbak_wrs_enabled', 'yes');
        add_option('ihumbak_wrs_require_login', 'no');
        add_option('ihumbak_wrs_widget_position', 'after_title');
        add_option('ihumbak_wrs_show_count', 'yes');
        add_option('ihumbak_wrs_star_color', '#ffc107');
        add_option('ihumbak_wrs_text_rate', __('Rate this product', 'ihumbak-woo-rating-stars'));
        add_option('ihumbak_wrs_text_thanks', __('Thank you for your rating!', 'ihumbak-woo-rating-stars'));

        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function ihumbak_wrs() {
    return Ihumbak_WooCommerce_Rating_Stars::instance();
}

// Start the plugin
ihumbak_wrs();
