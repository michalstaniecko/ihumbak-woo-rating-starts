<?php
/**
 * Admin Settings
 * Handles plugin settings page
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ihumbak_WRS_Admin_Settings {
    
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('ihumbak_wrs_settings', 'ihumbak_wrs_enabled');
        register_setting('ihumbak_wrs_settings', 'ihumbak_wrs_require_login');
        register_setting('ihumbak_wrs_settings', 'ihumbak_wrs_admin_only');
        register_setting('ihumbak_wrs_settings', 'ihumbak_wrs_widget_position');
        register_setting('ihumbak_wrs_settings', 'ihumbak_wrs_show_count');
        register_setting('ihumbak_wrs_settings', 'ihumbak_wrs_star_color');
        register_setting('ihumbak_wrs_settings', 'ihumbak_wrs_text_rate');
        register_setting('ihumbak_wrs_settings', 'ihumbak_wrs_text_thanks');
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_POST['submit']) && check_admin_referer('ihumbak_wrs_settings')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved', 'ihumbak-woo-rating-stars') . '</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Quick Ratings Settings', 'ihumbak-woo-rating-stars'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('ihumbak_wrs_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ihumbak_wrs_enabled">
                                <?php esc_html_e('Enable Quick Ratings', 'ihumbak-woo-rating-stars'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="ihumbak_wrs_enabled" 
                                   name="ihumbak_wrs_enabled" 
                                   value="yes" 
                                   <?php checked(get_option('ihumbak_wrs_enabled'), 'yes'); ?>>
                            <p class="description">
                                <?php esc_html_e('Enable or disable the quick rating system', 'ihumbak-woo-rating-stars'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ihumbak_wrs_require_login">
                                <?php esc_html_e('Require Login', 'ihumbak-woo-rating-stars'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="ihumbak_wrs_require_login" 
                                   name="ihumbak_wrs_require_login" 
                                   value="yes" 
                                   <?php checked(get_option('ihumbak_wrs_require_login'), 'yes'); ?>>
                            <p class="description">
                                <?php esc_html_e('Require users to be logged in to rate products', 'ihumbak-woo-rating-stars'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ihumbak_wrs_widget_position">
                                <?php esc_html_e('Widget Position', 'ihumbak-woo-rating-stars'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="ihumbak_wrs_widget_position" name="ihumbak_wrs_widget_position">
                                <option value="after_title" <?php selected(get_option('ihumbak_wrs_widget_position'), 'after_title'); ?>>
                                    <?php esc_html_e('After Product Title', 'ihumbak-woo-rating-stars'); ?>
                                </option>
                                <option value="before_price" <?php selected(get_option('ihumbak_wrs_widget_position'), 'before_price'); ?>>
                                    <?php esc_html_e('Before Price', 'ihumbak-woo-rating-stars'); ?>
                                </option>
                                <option value="after_price" <?php selected(get_option('ihumbak_wrs_widget_position'), 'after_price'); ?>>
                                    <?php esc_html_e('After Price', 'ihumbak-woo-rating-stars'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Choose where to display the rating widget', 'ihumbak-woo-rating-stars'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ihumbak_wrs_admin_only">
                                <?php esc_html_e('Admin Only Mode (Debug)', 'ihumbak-woo-rating-stars'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="ihumbak_wrs_admin_only" 
                                   name="ihumbak_wrs_admin_only" 
                                   value="yes" 
                                   <?php checked(get_option('ihumbak_wrs_admin_only'), 'yes'); ?>>
                            <p class="description">
                                <?php esc_html_e('Show rating widget only to logged-in administrators. Perfect for testing before going live!', 'ihumbak-woo-rating-stars'); ?>
                                <br>
                                <strong style="color: #d63638;">⚠️ <?php esc_html_e('Warning: Regular users will not see the widget when this is enabled.', 'ihumbak-woo-rating-stars'); ?></strong>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ihumbak_wrs_show_count">
                                <?php esc_html_e('Show Rating Count', 'ihumbak-woo-rating-stars'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="ihumbak_wrs_show_count" 
                                   name="ihumbak_wrs_show_count" 
                                   value="yes" 
                                   <?php checked(get_option('ihumbak_wrs_show_count'), 'yes'); ?>>
                            <p class="description">
                                <?php esc_html_e('Display number of ratings next to stars', 'ihumbak-woo-rating-stars'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ihumbak_wrs_star_color">
                                <?php esc_html_e('Star Color', 'ihumbak-woo-rating-stars'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="ihumbak_wrs_star_color" 
                                   name="ihumbak_wrs_star_color" 
                                   value="<?php echo esc_attr(get_option('ihumbak_wrs_star_color', '#ffc107')); ?>">
                            <p class="description">
                                <?php esc_html_e('Choose the color for rating stars', 'ihumbak-woo-rating-stars'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ihumbak_wrs_text_rate">
                                <?php esc_html_e('Rating Text', 'ihumbak-woo-rating-stars'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ihumbak_wrs_text_rate" 
                                   name="ihumbak_wrs_text_rate" 
                                   value="<?php echo esc_attr(get_option('ihumbak_wrs_text_rate', __('Rate this product', 'ihumbak-woo-rating-stars'))); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Text displayed above rating stars', 'ihumbak-woo-rating-stars'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ihumbak_wrs_text_thanks">
                                <?php esc_html_e('Thank You Text', 'ihumbak-woo-rating-stars'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ihumbak_wrs_text_thanks" 
                                   name="ihumbak_wrs_text_thanks" 
                                   value="<?php echo esc_attr(get_option('ihumbak_wrs_text_thanks', __('Thank you for your rating!', 'ihumbak-woo-rating-stars'))); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Message shown after user rates a product', 'ihumbak-woo-rating-stars'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        update_option('ihumbak_wrs_enabled', isset($_POST['ihumbak_wrs_enabled']) ? 'yes' : 'no');
        update_option('ihumbak_wrs_require_login', isset($_POST['ihumbak_wrs_require_login']) ? 'yes' : 'no');
        update_option('ihumbak_wrs_admin_only', isset($_POST['ihumbak_wrs_admin_only']) ? 'yes' : 'no');
        update_option('ihumbak_wrs_widget_position', sanitize_text_field($_POST['ihumbak_wrs_widget_position']));
        update_option('ihumbak_wrs_show_count', isset($_POST['ihumbak_wrs_show_count']) ? 'yes' : 'no');
        update_option('ihumbak_wrs_star_color', sanitize_hex_color($_POST['ihumbak_wrs_star_color']));
        update_option('ihumbak_wrs_text_rate', sanitize_text_field($_POST['ihumbak_wrs_text_rate']));
        update_option('ihumbak_wrs_text_thanks', sanitize_text_field($_POST['ihumbak_wrs_text_thanks']));
        
        // Clear all caches
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ihumbak_wrs_%'");
    }
}
