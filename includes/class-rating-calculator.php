<?php
/**
 * Rating Calculator
 * Calculates combined averages from quick ratings and WooCommerce reviews
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ihumbak_WRS_Rating_Calculator {
    
    private $rating_model;
    
    public function __construct() {
        $this->rating_model = new Ihumbak_WRS_Rating_Model();
    }
    
    /**
     * Get quick ratings average for product
     */
    public function get_quick_ratings_average($product_id) {
        $cache_key = 'ihumbak_wrs_quick_avg_' . $product_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return (float) $cached;
        }
        
        $average = $this->rating_model->get_average_rating($product_id);
        
        set_transient($cache_key, $average, HOUR_IN_SECONDS);
        
        return (float) $average;
    }
    
    /**
     * Get WooCommerce reviews average for product
     */
    public function get_reviews_average($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return 0;
        }
        
        $review_count = $product->get_review_count();
        
        if ($review_count === 0) {
            return 0;
        }
        
        global $wpdb;
        
        $average = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(meta_value) FROM {$wpdb->commentmeta} cm
            INNER JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
            WHERE c.comment_post_ID = %d 
            AND c.comment_approved = '1'
            AND cm.meta_key = 'rating'
            AND cm.meta_value > 0",
            $product_id
        ));
        
        return (float) $average;
    }
    
    /**
     * Get combined average rating
     */
    public function get_combined_average($product_id) {
        $cache_key = 'ihumbak_wrs_combined_avg_' . $product_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return (float) $cached;
        }
        
        $quick_count = $this->rating_model->get_rating_count($product_id);
        $quick_sum = $quick_count > 0 ? $this->get_quick_ratings_average($product_id) * $quick_count : 0;
        
        $product = wc_get_product($product_id);
        $review_count = $product ? $product->get_review_count() : 0;
        $review_sum = $review_count > 0 ? $this->get_reviews_average($product_id) * $review_count : 0;
        
        $total_count = $quick_count + $review_count;
        
        if ($total_count === 0) {
            return 0;
        }
        
        $combined_average = ($quick_sum + $review_sum) / $total_count;
        
        set_transient($cache_key, $combined_average, HOUR_IN_SECONDS);
        
        return (float) $combined_average;
    }
    
    /**
     * Get total rating count (quick + reviews)
     */
    public function get_total_rating_count($product_id) {
        $cache_key = 'ihumbak_wrs_total_count_' . $product_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return (int) $cached;
        }
        
        $quick_count = $this->rating_model->get_rating_count($product_id);
        
        $product = wc_get_product($product_id);
        $review_count = $product ? $product->get_review_count() : 0;
        
        $total = $quick_count + $review_count;
        
        set_transient($cache_key, $total, HOUR_IN_SECONDS);
        
        return (int) $total;
    }
    
    /**
     * Get rating distribution
     */
    public function get_rating_distribution($product_id) {
        $cache_key = 'ihumbak_wrs_distribution_' . $product_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Get quick ratings distribution
        $quick_dist = $this->rating_model->get_rating_distribution($product_id);
        
        // Get reviews distribution
        global $wpdb;
        $review_results = $wpdb->get_results($wpdb->prepare(
            "SELECT cm.meta_value as rating, COUNT(*) as count 
            FROM {$wpdb->commentmeta} cm
            INNER JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
            WHERE c.comment_post_ID = %d 
            AND c.comment_approved = '1'
            AND cm.meta_key = 'rating'
            AND cm.meta_value > 0
            GROUP BY cm.meta_value
            ORDER BY cm.meta_value DESC",
            $product_id
        ), ARRAY_A);
        
        $review_dist = array(5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0);
        foreach ($review_results as $row) {
            $review_dist[(int) $row['rating']] = (int) $row['count'];
        }
        
        // Combine distributions
        $combined_dist = array();
        for ($i = 5; $i >= 1; $i--) {
            $combined_dist[$i] = $quick_dist[$i] + $review_dist[$i];
        }
        
        set_transient($cache_key, $combined_dist, HOUR_IN_SECONDS);
        
        return $combined_dist;
    }
    
    /**
     * Get statistics for product
     */
    public function get_product_stats($product_id) {
        $cache_key = 'ihumbak_wrs_stats_' . $product_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $stats = array(
            'average' => $this->get_combined_average($product_id),
            'total_count' => $this->get_total_rating_count($product_id),
            'quick_count' => $this->rating_model->get_rating_count($product_id),
            'review_count' => 0,
            'distribution' => $this->get_rating_distribution($product_id)
        );
        
        $product = wc_get_product($product_id);
        if ($product) {
            $stats['review_count'] = $product->get_review_count();
        }
        
        set_transient($cache_key, $stats, HOUR_IN_SECONDS);
        
        return $stats;
    }
}
