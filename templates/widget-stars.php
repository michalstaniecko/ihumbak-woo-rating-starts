<?php
/**
 * Rating Widget Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ihumbak-wrs-widget" data-product-id="<?php echo esc_attr($product_id); ?>" 
     <?php if ($stats['total_count'] > 0): ?>
     itemscope itemtype="https://schema.org/AggregateRating" itemprop="aggregateRating"
     <?php endif; ?>>
    <div class="ihumbak-wrs-container">
        <div class="ihumbak-wrs-message success" style="display:<?php echo $user_rating > 0 ? 'block' : 'none'; ?>">
            <?php echo esc_html($text_thanks); ?>
        </div>
        
        <div class="ihumbak-wrs-stars-wrapper">
            <div class="ihumbak-wrs-label">
                <?php echo esc_html($text_rate); ?>
            </div>
            
            <div class="ihumbak-wrs-stars" data-user-rating="<?php echo esc_attr($user_rating); ?>">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star <?php echo ($i <= $user_rating) ? 'filled active' : ''; ?>" 
                          data-rating="<?php echo esc_attr($i); ?>"
                          role="button"
                          aria-label="<?php echo esc_attr(sprintf(__('Rate %d stars', 'ihumbak-woo-rating-stars'), $i)); ?>">
                        ★
                    </span>
                <?php endfor; ?>
            </div>
            
            <?php if ($show_count && $stats['total_count'] > 0): ?>
                <div class="ihumbak-wrs-count">
                    <?php 
                    echo sprintf(
                        _n('%s rating', '%s ratings', $stats['total_count'], 'ihumbak-woo-rating-stars'),
                        '<span class="count" itemprop="ratingCount">' . esc_html($stats['total_count']) . '</span>'
                    ); 
                    ?>
                    <meta itemprop="ratingValue" content="<?php echo esc_attr(number_format($stats['average'], 2)); ?>">
                    <meta itemprop="bestRating" content="5">
                    <meta itemprop="worstRating" content="1">
                </div>
            <?php endif; ?>
        </div>
        
        <div class="ihumbak-wrs-message error" style="display:none;"></div>
        <div class="ihumbak-wrs-loading" style="display:none;">
            <span class="spinner"></span>
        </div>
    </div>
</div>
