<?php
/**
 * Schema.org Markup Handler
 * Adds structured data for SEO and Google Rich Snippets
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ihumbak_WRS_Schema_Markup {
    
    private $calculator;
    
    public function __construct() {
        $this->calculator = new Ihumbak_WRS_Rating_Calculator();

        if (get_option('ihumbak_wrs_enabled') === 'yes') {
            add_filter('woocommerce_structured_data_product', array($this, 'modify_wc_schema'), 10, 2);
        }
    }
    
    /**
     * Add product schema with aggregated ratings
     */
    public function add_product_schema() {
        if (!is_product()) {
            return;
        }
        
        global $product;
        
        if (!$product || !is_a($product, 'WC_Product')) {
            $product = wc_get_product(get_the_ID());
        }
        
        if (!$product) {
            return;
        }
        
        $product_id = $product->get_id();
        $stats = $this->calculator->get_product_stats($product_id);
        
        // Only add schema if there are ratings
        if ($stats['total_count'] === 0) {
            return;
        }
        
        $schema = $this->generate_aggregate_rating_schema($product, $stats);
        
        if ($schema) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
        }
    }
    
    /**
     * Generate AggregateRating schema
     */
    private function generate_aggregate_rating_schema($product, $stats) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => get_permalink($product->get_id()) . '#product',
            'name' => $product->get_name(),
            'description' => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
            'aggregateRating' => $this->build_aggregate_rating($stats)
        );
        
        // Add SKU if available
        if ($product->get_sku()) {
            $schema['sku'] = $product->get_sku();
        }
        
        // Add brand if available
        $brands = wp_get_post_terms($product->get_id(), 'product_brand', array('fields' => 'names'));
        if (!empty($brands)) {
            $schema['brand'] = array(
                '@type' => 'Brand',
                'name' => $brands[0]
            );
        }
        
        // Add image
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image = wp_get_attachment_image_src($image_id, 'full');
            if ($image) {
                $schema['image'] = $image[0];
            }
        }
        
        // Add offers (price info)
        if ($product->is_in_stock()) {
            $schema['offers'] = array(
                '@type' => 'Offer',
                'price' => $product->get_price(),
                'priceCurrency' => get_woocommerce_currency(),
                'availability' => 'https://schema.org/InStock',
                'url' => get_permalink($product->get_id())
            );
            
            // Add price valid until for sale items
            if ($product->is_on_sale() && $product->get_date_on_sale_to()) {
                $schema['offers']['priceValidUntil'] = $product->get_date_on_sale_to()->format('Y-m-d');
            }
        }
        
        return $schema;
    }
    
    /**
     * Modify WooCommerce's built-in schema
     */
    public function modify_wc_schema($markup, $product) {
        $product_id = $product->get_id();
        $stats = $this->calculator->get_product_stats($product_id);

        if ($stats['total_count'] > 0) {
            $markup['aggregateRating'] = $this->build_aggregate_rating($stats);
        }

        return $markup;
    }
    
    /**
     * Build AggregateRating array for schema
     */
    private function build_aggregate_rating($stats) {
        $aggregate = array(
            '@type' => 'AggregateRating',
            'ratingValue' => number_format($stats['average'], 2),
            'ratingCount' => $stats['total_count'],
            'bestRating' => '5',
            'worstRating' => '1'
        );

        if ($stats['review_count'] > 0) {
            $aggregate['reviewCount'] = $stats['review_count'];
        }

        return $aggregate;
    }

    /**
     * Add microdata attributes to widget (optional - for older SEO)
     */
    public function get_microdata_attributes($stats) {
        if ($stats['total_count'] === 0) {
            return '';
        }
        
        return sprintf(
            'itemscope itemtype="https://schema.org/AggregateRating" itemprop="aggregateRating"',
            ''
        );
    }
}
