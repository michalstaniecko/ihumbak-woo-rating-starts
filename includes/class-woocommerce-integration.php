<?php
/**
 * WooCommerce Integration
 * Integrates quick ratings with WooCommerce rating system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ihumbak_WRS_WooCommerce_Integration {
    
    private $calculator;
    
    public function __construct() {
        $this->calculator = new Ihumbak_WRS_Rating_Calculator();
        
        add_filter('woocommerce_product_get_average_rating', array($this, 'modify_average_rating'), 10, 2);
        add_filter('woocommerce_product_get_rating_count', array($this, 'modify_rating_count'), 10, 2);
        add_filter('woocommerce_product_get_rating_html', array($this, 'modify_rating_html'), 10, 3);
    }
    
    /**
     * Modify average rating to include quick ratings
     */
    public function modify_average_rating($average, $product) {
        if (get_option('ihumbak_wrs_enabled') !== 'yes') {
            return $average;
        }
        
        $product_id = $product->get_id();
        $combined_average = $this->calculator->get_combined_average($product_id);
        
        return $combined_average;
    }
    
    /**
     * Modify rating count to include quick ratings
     */
    public function modify_rating_count($count, $product) {
        if (get_option('ihumbak_wrs_enabled') !== 'yes') {
            return $count;
        }
        
        $product_id = $product->get_id();
        $total_count = $this->calculator->get_total_rating_count($product_id);
        
        return $total_count;
    }
    
    /**
     * Modify rating HTML
     */
    public function modify_rating_html($html, $rating, $count) {
        if (get_option('ihumbak_wrs_enabled') !== 'yes') {
            return $html;
        }
        
        if ($count > 0) {
            $html = sprintf(
                '<div class="star-rating" role="img" aria-label="' . esc_attr__('Rated %s out of 5', 'ihumbak-woo-rating-stars') . '"><span style="width:%s%%">%s</span></div>',
                esc_attr($rating),
                ($rating / 5) * 100,
                sprintf(__('Rated %s out of 5', 'ihumbak-woo-rating-stars'), '<strong class="rating">' . esc_html($rating) . '</strong>')
            );
        }
        
        return $html;
    }
}
