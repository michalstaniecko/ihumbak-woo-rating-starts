<?php
/**
 * REST API Handler
 * Registers and handles REST API endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ihumbak_WRS_REST_API_Handler {
    
    private $namespace = 'woo-quick-ratings/v1';
    private $rating_model;
    private $calculator;
    
    public function __construct() {
        $this->rating_model = new Ihumbak_WRS_Rating_Model();
        $this->calculator = new Ihumbak_WRS_Rating_Calculator();
        
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // POST: Add/update rating
        register_rest_route($this->namespace, '/rate', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_rating'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'product_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => array($this, 'validate_product_id')
                ),
                'rating' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => array($this, 'validate_rating')
                )
            )
        ));
        
        // GET: Get product stats
        register_rest_route($this->namespace, '/stats/(?P<product_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => '__return_true',
            'args' => array(
                'product_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => array($this, 'validate_product_id')
                )
            )
        ));
    }
    
    /**
     * Add rating callback
     */
    public function add_rating($request) {
        $product_id = $request->get_param('product_id');
        $rating = $request->get_param('rating');
        
        // Check if rating is enabled
        if (get_option('ihumbak_wrs_enabled') !== 'yes') {
            return new WP_Error(
                'ratings_disabled',
                __('Ratings are currently disabled', 'ihumbak-woo-rating-stars'),
                array('status' => 403)
            );
        }
        
        // Check if login is required
        if (get_option('ihumbak_wrs_require_login') === 'yes' && !is_user_logged_in()) {
            return new WP_Error(
                'login_required',
                __('You must be logged in to rate products', 'ihumbak-woo-rating-stars'),
                array('status' => 403)
            );
        }
        
        // Get user data
        $user_id = get_current_user_id();
        $ip_address = $this->get_user_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        
        // Check rate limiting
        if ($this->rating_model->check_rate_limit($ip_address, $product_id, 10)) {
            return new WP_Error(
                'rate_limit',
                __('Please wait before rating again', 'ihumbak-woo-rating-stars'),
                array('status' => 429)
            );
        }
        
        // Add rating
        $result = $this->rating_model->add_rating(
            $product_id,
            $rating,
            $user_id ?: null,
            $ip_address,
            $user_agent
        );
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Get updated stats
        $stats = $this->calculator->get_product_stats($product_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => get_option('ihumbak_wrs_text_thanks', __('Thank you for your rating!', 'ihumbak-woo-rating-stars')),
            'rating_id' => $result,
            'stats' => $stats
        ));
    }
    
    /**
     * Get stats callback
     */
    public function get_stats($request) {
        $product_id = $request->get_param('product_id');
        
        $stats = $this->calculator->get_product_stats($product_id);
        
        // Get user's rating if exists
        $user_id = get_current_user_id();
        $ip_address = $this->get_user_ip();
        $user_rating = $this->rating_model->get_user_rating($product_id, $user_id ?: null, $ip_address);
        
        $stats['user_rating'] = $user_rating ? (int) $user_rating->rating : null;
        
        return rest_ensure_response($stats);
    }
    
    /**
     * Check permission
     */
    public function check_permission($request) {
        // Allow any visitor to rate (will check login requirement in callback)
        return true;
    }
    
    /**
     * Validate product ID
     */
    public function validate_product_id($param, $request, $key) {
        if (!is_numeric($param)) {
            return false;
        }
        
        $product = wc_get_product($param);
        return $product !== false;
    }
    
    /**
     * Validate rating value
     */
    public function validate_rating($param, $request, $key) {
        return is_numeric($param) && $param >= 1 && $param <= 5;
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
