<?php
/**
 * Frontend Render
 * Handles rendering of rating widget on frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ihumbak_WRS_Frontend_Render {
    
    private $rating_model;
    private $calculator;
    
    public function __construct() {
        $this->rating_model = new Ihumbak_WRS_Rating_Model();
        $this->calculator = new Ihumbak_WRS_Rating_Calculator();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        if (get_option('ihumbak_wrs_enabled') !== 'yes') {
            return;
        }
        
        $position = get_option('ihumbak_wrs_widget_position', 'after_title');
        
        switch ($position) {
            case 'after_title':
                add_action('woocommerce_single_product_summary', array($this, 'render_widget'), 6);
                break;
            case 'before_price':
                add_action('woocommerce_single_product_summary', array($this, 'render_widget'), 9);
                break;
            case 'after_price':
                add_action('woocommerce_single_product_summary', array($this, 'render_widget'), 11);
                break;
        }
    }
    
    /**
     * Render rating widget
     */
    public function render_widget() {
        global $product;
        
        if (!$product || !is_object($product)) {
            $product = wc_get_product(get_the_ID());
        }
        
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }
        
        $product_id = $product->get_id();
        $stats = $this->calculator->get_product_stats($product_id);
        
        // Get user's rating if exists
        $user_id = get_current_user_id();
        $ip_address = $this->get_user_ip();
        $user_rating = $this->rating_model->get_user_rating($product_id, $user_id ?: null, $ip_address);
        
        $data = array(
            'product_id' => $product_id,
            'stats' => $stats,
            'user_rating' => $user_rating ? (int) $user_rating->rating : 0,
            'text_rate' => get_option('ihumbak_wrs_text_rate', __('Rate this product', 'ihumbak-woo-rating-stars')),
            'text_thanks' => get_option('ihumbak_wrs_text_thanks', __('Thank you for your rating!', 'ihumbak-woo-rating-stars')),
            'show_count' => get_option('ihumbak_wrs_show_count', 'yes') === 'yes',
            'require_login' => get_option('ihumbak_wrs_require_login', 'no') === 'yes',
            'is_logged_in' => is_user_logged_in()
        );
        
        $this->render_template('widget-stars', $data);
    }
    
    /**
     * Render template
     */
    private function render_template($template, $data = array()) {
        extract($data);
        
        $template_path = IHUMBAK_WRS_PLUGIN_DIR . 'templates/' . $template . '.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        }
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
}
