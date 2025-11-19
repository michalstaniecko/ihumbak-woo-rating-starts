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
        
        // Hide rating count in loop if option enabled
        if (get_option('ihumbak_wrs_hide_count_in_loop') === 'yes') {
            add_action('wp_head', array($this, 'hide_loop_rating_count_css'));
        }
        
        // Update product rating meta when reviews are updated
        add_action('comment_post', array($this, 'update_product_rating_meta'), 10, 3);
        add_action('edit_comment', array($this, 'update_product_rating_meta_by_comment'), 10, 2);
        add_action('trashed_comment', array($this, 'update_product_rating_meta_by_comment'), 10, 2);
        add_action('untrashed_comment', array($this, 'update_product_rating_meta_by_comment'), 10, 2);
        add_action('deleted_comment', array($this, 'update_product_rating_meta_by_comment'), 10, 2);
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
    
    /**
     * Update product rating meta (called when comment is posted)
     */
    public function update_product_rating_meta($comment_id, $comment_approved, $commentdata) {
        if ($comment_approved === 1 || $comment_approved === 'approve') {
            $comment = get_comment($comment_id);
            if ($comment && $comment->comment_type === 'review') {
                $product_id = $comment->comment_post_ID;
                $this->sync_product_rating_meta($product_id);
            }
        }
    }
    
    /**
     * Update product rating meta by comment ID
     */
    public function update_product_rating_meta_by_comment($comment_id, $comment = null) {
        if (!$comment) {
            $comment = get_comment($comment_id);
        }
        
        if ($comment && $comment->comment_type === 'review') {
            $product_id = $comment->comment_post_ID;
            $this->sync_product_rating_meta($product_id);
        }
    }
    
    /**
     * Sync product rating meta with combined ratings
     */
    public function sync_product_rating_meta($product_id) {
        if (get_option('ihumbak_wrs_enabled') !== 'yes') {
            return;
        }
        
        $stats = $this->calculator->get_product_stats($product_id);
        
        // Update WooCommerce meta with combined data
        update_post_meta($product_id, '_wc_average_rating', number_format($stats['average'], 2));
        update_post_meta($product_id, '_wc_rating_count', $stats['total_count']);
        update_post_meta($product_id, '_wc_review_count', $stats['review_count']);
        
        // Clear cache
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
        clean_post_cache($product_id);
    }
    
    /**
     * Hide rating count in loop via CSS
     */
    public function hide_loop_rating_count_css() {
        ?>
        <style type="text/css">
            /* Hide rating count in WooCommerce product loop */
            .woocommerce ul.products li.product .woocommerce-product-rating .woocommerce-review-link,
            .woocommerce ul.products li.product .woocommerce-product-rating .rating-count,
            .woocommerce ul.products li.product .star-rating + *:not(.star-rating) {
                display: none !important;
            }
            
            /* Ensure stars are still visible */
            .woocommerce ul.products li.product .star-rating {
                display: inline-block !important;
                margin: 0 !important;
            }
            
            /* Hide count in related/upsell products too */
            .related ul.products li.product .woocommerce-review-link,
            .upsells ul.products li.product .woocommerce-review-link,
            .cross-sells ul.products li.product .woocommerce-review-link {
                display: none !important;
            }
        </style>
        <?php
    }
}
