<?php
/**
 * Admin Panel
 * Handles admin interface for managing ratings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ihumbak_WRS_Admin_Panel {
    
    private $rating_model;
    private $calculator;
    
    public function __construct() {
        $this->rating_model = new Ihumbak_WRS_Rating_Model();
        $this->calculator = new Ihumbak_WRS_Rating_Calculator();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_ihumbak_wrs_delete_rating', array($this, 'delete_rating'));
        add_filter('manage_product_posts_columns', array($this, 'add_product_column'));
        add_action('manage_product_posts_custom_column', array($this, 'render_product_column'), 10, 2);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Quick Ratings', 'ihumbak-woo-rating-stars'),
            __('Quick Ratings', 'ihumbak-woo-rating-stars'),
            'manage_woocommerce',
            'ihumbak-wrs-ratings',
            array($this, 'render_ratings_page'),
            'dashicons-star-filled',
            56
        );
        
        add_submenu_page(
            'ihumbak-wrs-ratings',
            __('All Ratings', 'ihumbak-woo-rating-stars'),
            __('All Ratings', 'ihumbak-woo-rating-stars'),
            'manage_woocommerce',
            'ihumbak-wrs-ratings',
            array($this, 'render_ratings_page')
        );
        
        add_submenu_page(
            'ihumbak-wrs-ratings',
            __('Settings', 'ihumbak-woo-rating-stars'),
            __('Settings', 'ihumbak-woo-rating-stars'),
            'manage_options',
            'ihumbak-wrs-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render ratings page
     */
    public function render_ratings_page() {
        $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Quick Ratings', 'ihumbak-woo-rating-stars'); ?></h1>
            
            <?php if ($product_id): ?>
                <?php $this->render_product_ratings($product_id); ?>
            <?php else: ?>
                <?php $this->render_all_products(); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render all products with ratings
     */
    private function render_all_products() {
        global $wpdb;
        
        $products = $wpdb->get_results(
            "SELECT p.ID, p.post_title, COUNT(r.id) as rating_count, AVG(r.rating) as avg_rating
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}woo_quick_ratings r ON p.ID = r.product_id
            WHERE p.post_type = 'product' AND p.post_status = 'publish'
            GROUP BY p.ID
            HAVING rating_count > 0
            ORDER BY rating_count DESC
            LIMIT 50"
        );
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product', 'ihumbak-woo-rating-stars'); ?></th>
                    <th><?php esc_html_e('Quick Ratings', 'ihumbak-woo-rating-stars'); ?></th>
                    <th><?php esc_html_e('Average', 'ihumbak-woo-rating-stars'); ?></th>
                    <th><?php esc_html_e('Combined Average', 'ihumbak-woo-rating-stars'); ?></th>
                    <th><?php esc_html_e('Actions', 'ihumbak-woo-rating-stars'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e('No ratings found', 'ihumbak-woo-rating-stars'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $combined_avg = $this->calculator->get_combined_average($product->ID);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($product->post_title); ?></strong>
                                <br>
                                <small>ID: <?php echo esc_html($product->ID); ?></small>
                            </td>
                            <td><?php echo esc_html($product->rating_count); ?></td>
                            <td><?php echo esc_html(number_format($product->avg_rating, 2)); ?> ⭐</td>
                            <td><?php echo esc_html(number_format($combined_avg, 2)); ?> ⭐</td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ihumbak-wrs-ratings&product_id=' . $product->ID)); ?>" class="button button-small">
                                    <?php esc_html_e('View Details', 'ihumbak-woo-rating-stars'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render product ratings
     */
    private function render_product_ratings($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Product not found', 'ihumbak-woo-rating-stars') . '</p></div>';
            return;
        }
        
        $ratings = $this->rating_model->get_all_ratings($product_id);
        $stats = $this->calculator->get_product_stats($product_id);
        
        ?>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ihumbak-wrs-ratings')); ?>">
                &larr; <?php esc_html_e('Back to all products', 'ihumbak-woo-rating-stars'); ?>
            </a>
        </p>
        
        <h2><?php echo esc_html($product->get_name()); ?></h2>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-bottom: 20px;">
            <h3><?php esc_html_e('Statistics', 'ihumbak-woo-rating-stars'); ?></h3>
            <p>
                <strong><?php esc_html_e('Combined Average:', 'ihumbak-woo-rating-stars'); ?></strong> 
                <?php echo esc_html(number_format($stats['average'], 2)); ?> ⭐
            </p>
            <p>
                <strong><?php esc_html_e('Total Ratings:', 'ihumbak-woo-rating-stars'); ?></strong> 
                <?php echo esc_html($stats['total_count']); ?> 
                (<?php echo esc_html($stats['quick_count']); ?> quick + <?php echo esc_html($stats['review_count']); ?> reviews)
            </p>
            
            <h4><?php esc_html_e('Distribution', 'ihumbak-woo-rating-stars'); ?></h4>
            <?php foreach ($stats['distribution'] as $stars => $count): ?>
                <p>
                    <?php echo str_repeat('⭐', $stars); ?>: <?php echo esc_html($count); ?>
                </p>
            <?php endforeach; ?>
        </div>
        
        <h3><?php esc_html_e('Individual Ratings', 'ihumbak-woo-rating-stars'); ?></h3>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Rating', 'ihumbak-woo-rating-stars'); ?></th>
                    <th><?php esc_html_e('User', 'ihumbak-woo-rating-stars'); ?></th>
                    <th><?php esc_html_e('IP Address', 'ihumbak-woo-rating-stars'); ?></th>
                    <th><?php esc_html_e('Date', 'ihumbak-woo-rating-stars'); ?></th>
                    <th><?php esc_html_e('Actions', 'ihumbak-woo-rating-stars'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ratings)): ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e('No ratings found', 'ihumbak-woo-rating-stars'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ratings as $rating): ?>
                        <tr>
                            <td><?php echo str_repeat('⭐', $rating->rating); ?></td>
                            <td>
                                <?php
                                if ($rating->user_id) {
                                    $user = get_userdata($rating->user_id);
                                    echo esc_html($user ? $user->display_name : __('Unknown', 'ihumbak-woo-rating-stars'));
                                } else {
                                    echo esc_html__('Anonymous', 'ihumbak-woo-rating-stars');
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($rating->ip_address); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($rating->created_at))); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="ihumbak_wrs_delete_rating">
                                    <input type="hidden" name="rating_id" value="<?php echo esc_attr($rating->id); ?>">
                                    <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
                                    <?php wp_nonce_field('ihumbak_wrs_delete_rating'); ?>
                                    <button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure?', 'ihumbak-woo-rating-stars'); ?>')">
                                        <?php esc_html_e('Delete', 'ihumbak-woo-rating-stars'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // This will be handled by Admin_Settings class
        if (class_exists('Ihumbak_WRS_Admin_Settings')) {
            $settings = new Ihumbak_WRS_Admin_Settings();
            $settings->render_settings_page();
        }
    }
    
    /**
     * Delete rating
     */
    public function delete_rating() {
        check_admin_referer('ihumbak_wrs_delete_rating');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to do that', 'ihumbak-woo-rating-stars'));
        }
        
        $rating_id = isset($_POST['rating_id']) ? intval($_POST['rating_id']) : 0;
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        $this->rating_model->delete_rating($rating_id);
        
        wp_redirect(admin_url('admin.php?page=ihumbak-wrs-ratings&product_id=' . $product_id . '&deleted=1'));
        exit;
    }
    
    /**
     * Add product column
     */
    public function add_product_column($columns) {
        $columns['quick_ratings'] = __('Quick Ratings', 'ihumbak-woo-rating-stars');
        return $columns;
    }
    
    /**
     * Render product column
     */
    public function render_product_column($column, $product_id) {
        if ($column === 'quick_ratings') {
            $count = $this->rating_model->get_rating_count($product_id);
            $average = $this->rating_model->get_average_rating($product_id);
            
            if ($count > 0) {
                echo esc_html(number_format($average, 1)) . ' ⭐ (' . esc_html($count) . ')';
            } else {
                echo '-';
            }
        }
    }
}
