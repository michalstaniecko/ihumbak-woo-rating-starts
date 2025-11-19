<?php
/**
 * Assets Manager
 * Handles loading of CSS and JavaScript files
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ihumbak_WRS_Assets_Manager {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if (!is_product()) {
            return;
        }
        
        if (get_option('ihumbak_wrs_enabled') !== 'yes') {
            return;
        }
        
        global $product;
        
        if (!$product || !is_object($product)) {
            $product = wc_get_product(get_the_ID());
        }
        
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }
        
        $product_id = $product->get_id();
        
        // Enqueue CSS
        wp_enqueue_style(
            'ihumbak-wrs-widget',
            IHUMBAK_WRS_PLUGIN_URL . 'assets/css/rating-widget.css',
            array(),
            IHUMBAK_WRS_VERSION
        );
        
        // Add custom star color
        $star_color = get_option('ihumbak_wrs_star_color', '#ffc107');
        $custom_css = "
            .ihumbak-wrs-widget .star:hover,
            .ihumbak-wrs-widget .star.active,
            .ihumbak-wrs-widget .star.filled {
                color: {$star_color};
            }
        ";
        wp_add_inline_style('ihumbak-wrs-widget', $custom_css);
        
        // Enqueue JS
        wp_enqueue_script(
            'ihumbak-wrs-widget',
            IHUMBAK_WRS_PLUGIN_URL . 'assets/js/rating-widget.js',
            array('jquery'),
            IHUMBAK_WRS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('ihumbak-wrs-widget', 'ihumbakWRS', array(
            'ajax_url' => rest_url('woo-quick-ratings/v1/'),
            'product_id' => $product_id,
            'nonce' => wp_create_nonce('wp_rest'),
            'require_login' => get_option('ihumbak_wrs_require_login', 'no') === 'yes',
            'is_logged_in' => is_user_logged_in(),
            'text' => array(
                'rate' => get_option('ihumbak_wrs_text_rate', __('Rate this product', 'ihumbak-woo-rating-stars')),
                'thanks' => get_option('ihumbak_wrs_text_thanks', __('Thank you for your rating!', 'ihumbak-woo-rating-stars')),
                'login_required' => __('Please log in to rate this product', 'ihumbak-woo-rating-stars'),
                'error' => __('Something went wrong. Please try again.', 'ihumbak-woo-rating-stars'),
                'rate_limit' => __('Please wait before rating again', 'ihumbak-woo-rating-stars')
            )
        ));
    }
}
