<?php
/**
 * Rating Model
 * Handles all database operations for ratings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ihumbak_WRS_Rating_Model {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'woo_quick_ratings';
    }
    
    /**
     * Get all ratings for a product
     */
    public function get_all_ratings($product_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE product_id = %d ORDER BY created_at DESC",
            $product_id
        ));
    }
    
    /**
     * Get user's rating for a product
     */
    public function get_user_rating($product_id, $user_id = null, $ip_address = null) {
        global $wpdb;
        
        if ($user_id) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE product_id = %d AND user_id = %d",
                $product_id,
                $user_id
            ));
        } elseif ($ip_address) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE product_id = %d AND ip_address = %s AND user_id IS NULL",
                $product_id,
                $ip_address
            ));
        }
        
        return null;
    }
    
    /**
     * Add a new rating
     */
    public function add_rating($product_id, $rating, $user_id = null, $ip_address = null, $user_agent = null) {
        global $wpdb;
        
        // Validate rating
        if ($rating < 1 || $rating > 5) {
            return new WP_Error('invalid_rating', __('Rating must be between 1 and 5', 'ihumbak-woo-rating-stars'));
        }
        
        // Check if rating already exists
        $existing = $this->get_user_rating($product_id, $user_id, $ip_address);
        
        if ($existing) {
            return $this->update_rating($existing->id, $rating);
        }
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'product_id' => $product_id,
                'rating' => $rating,
                'user_id' => $user_id,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to save rating', 'ihumbak-woo-rating-stars'));
        }
        
        // Clear cache
        $this->clear_product_cache($product_id);
        
        // Update WooCommerce product rating meta
        $this->update_wc_product_meta($product_id);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update existing rating
     */
    public function update_rating($rating_id, $new_rating) {
        global $wpdb;
        
        // Validate rating
        if ($new_rating < 1 || $new_rating > 5) {
            return new WP_Error('invalid_rating', __('Rating must be between 1 and 5', 'ihumbak-woo-rating-stars'));
        }
        
        // Get product_id for cache clearing
        $rating = $wpdb->get_row($wpdb->prepare(
            "SELECT product_id FROM {$this->table_name} WHERE id = %d",
            $rating_id
        ));
        
        $result = $wpdb->update(
            $this->table_name,
            array(
                'rating' => $new_rating,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $rating_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update rating', 'ihumbak-woo-rating-stars'));
        }
        
        // Clear cache
        if ($rating) {
            $this->clear_product_cache($rating->product_id);
            $this->update_wc_product_meta($rating->product_id);
        }
        
        return $rating_id;
    }
    
    /**
     * Delete a rating
     */
    public function delete_rating($rating_id) {
        global $wpdb;
        
        // Get product_id for cache clearing
        $rating = $wpdb->get_row($wpdb->prepare(
            "SELECT product_id FROM {$this->table_name} WHERE id = %d",
            $rating_id
        ));
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $rating_id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete rating', 'ihumbak-woo-rating-stars'));
        }
        
        // Clear cache
        if ($rating) {
            $this->clear_product_cache($rating->product_id);
            $this->update_wc_product_meta($rating->product_id);
        }
        
        return true;
    }
    
    /**
     * Check rate limiting
     */
    public function check_rate_limit($ip_address, $product_id, $minutes = 10) {
        global $wpdb;
        
        $time_limit = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        
        $recent_rating = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE ip_address = %s AND product_id = %d AND created_at > %s",
            $ip_address,
            $product_id,
            $time_limit
        ));
        
        return $recent_rating > 0;
    }
    
    /**
     * Get rating count for product
     */
    public function get_rating_count($product_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE product_id = %d",
            $product_id
        ));
    }
    
    /**
     * Get average rating for product
     */
    public function get_average_rating($product_id) {
        global $wpdb;
        
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(rating) FROM {$this->table_name} WHERE product_id = %d",
            $product_id
        ));
    }
    
    /**
     * Get rating distribution for product
     */
    public function get_rating_distribution($product_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT rating, COUNT(*) as count FROM {$this->table_name} 
            WHERE product_id = %d GROUP BY rating ORDER BY rating DESC",
            $product_id
        ), ARRAY_A);
        
        $distribution = array(
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0
        );
        
        foreach ($results as $row) {
            $distribution[$row['rating']] = (int) $row['count'];
        }
        
        return $distribution;
    }
    
    /**
     * Clear product cache
     */
    private function clear_product_cache($product_id) {
        // Clear plugin transients
        delete_transient('ihumbak_wrs_stats_' . $product_id);
        delete_transient('ihumbak_wrs_quick_avg_' . $product_id);
        delete_transient('ihumbak_wrs_combined_avg_' . $product_id);
        delete_transient('ihumbak_wrs_total_count_' . $product_id);
        delete_transient('ihumbak_wrs_distribution_' . $product_id);
        
        // Clear WordPress cache
        wp_cache_delete('product-' . $product_id, 'products');
        
        // Clear WooCommerce product transients
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
        
        // Force WooCommerce to recalculate rating on next request
        delete_post_meta($product_id, '_wc_average_rating');
        delete_post_meta($product_id, '_wc_rating_count');
        delete_post_meta($product_id, '_wc_review_count');
        
        // Clear product object cache
        clean_post_cache($product_id);
    }
    
    /**
     * Update WooCommerce product rating meta
     */
    private function update_wc_product_meta($product_id) {
        // Get combined stats
        $calculator = new Ihumbak_WRS_Rating_Calculator();
        $stats = $calculator->get_product_stats($product_id);
        
        // Update WooCommerce meta
        update_post_meta($product_id, '_wc_average_rating', number_format($stats['average'], 2));
        update_post_meta($product_id, '_wc_rating_count', $stats['total_count']);
        update_post_meta($product_id, '_wc_review_count', $stats['review_count']);
    }
}
