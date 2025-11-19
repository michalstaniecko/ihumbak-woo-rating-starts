/**
 * Rating Widget JavaScript
 */
(function($) {
    'use strict';
    
    class RatingWidget {
        constructor(element) {
            this.widget = $(element);
            this.starsContainer = this.widget.find('.ihumbak-wrs-stars');
            this.stars = this.starsContainer.find('.star');
            this.messageContainer = this.widget.find('.ihumbak-wrs-message.error');
            this.successMessage = this.widget.find('.ihumbak-wrs-message.success');
            this.loadingIndicator = this.widget.find('.ihumbak-wrs-loading');
            this.countElement = this.widget.find('.ihumbak-wrs-count .count');
            this.productId = this.widget.data('product-id');
            this.currentRating = parseInt(this.starsContainer.data('user-rating')) || 0;
            this.isSubmitting = false;
            
            this.init();
        }
        
        init() {
            this.attachEvents();
            this.updateStars(this.currentRating);
        }
        
        attachEvents() {
            const self = this;
            
            // Hover effect
            this.stars.on('mouseenter', function() {
                const rating = $(this).data('rating');
                self.highlightStars(rating);
            });
            
            // Reset on mouse leave
            this.starsContainer.on('mouseleave', function() {
                self.updateStars(self.currentRating);
            });
            
            // Click to rate
            this.stars.on('click', function(e) {
                e.preventDefault();
                const rating = $(this).data('rating');
                self.submitRating(rating);
            });
        }
        
        highlightStars(rating) {
            this.stars.each(function() {
                const starRating = $(this).data('rating');
                if (starRating <= rating) {
                    $(this).addClass('active');
                } else {
                    $(this).removeClass('active');
                }
            });
        }
        
        updateStars(rating) {
            this.stars.removeClass('active filled');
            this.stars.each(function() {
                const starRating = $(this).data('rating');
                if (starRating <= rating) {
                    $(this).addClass('filled');
                }
            });
        }
        
        submitRating(rating) {
            if (this.isSubmitting) {
                return;
            }
            
            // Check login requirement
            if (ihumbakWRS.require_login && !ihumbakWRS.is_logged_in) {
                this.showError(ihumbakWRS.text.login_required);
                return;
            }
            
            this.isSubmitting = true;
            this.hideMessages();
            this.showLoading();
            
            const self = this;
            
            $.ajax({
                url: ihumbakWRS.ajax_url + 'rate',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', ihumbakWRS.nonce);
                },
                data: {
                    product_id: this.productId,
                    rating: rating
                },
                success: function(response) {
                    // WordPress REST API zwraca dane bezpośrednio lub obiekt z success
                    if (response && (response.success === true || response.success === undefined)) {
                        self.handleSuccess(rating, response);
                    } else {
                        self.showError(response.message || ihumbakWRS.text.error);
                    }
                },
                error: function(xhr) {
                    let errorMessage = ihumbakWRS.text.error;
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    
                    if (xhr.status === 429) {
                        errorMessage = ihumbakWRS.text.rate_limit;
                    }
                    
                    self.showError(errorMessage);
                },
                complete: function() {
                    self.isSubmitting = false;
                    self.hideLoading();
                }
            });
        }
        
        handleSuccess(rating, response) {
            this.currentRating = rating;
            this.updateStars(rating);
            
            // Update success message text if provided in response
            if (response.message) {
                this.successMessage.text(response.message);
            }
            
            this.showSuccess();
            
            // Update count if exists
            if (response.stats && response.stats.total_count) {
                this.updateCount(response.stats.total_count);
            }
            
            // Hide success message after 3 seconds
            const self = this;
            setTimeout(function() {
                self.successMessage.fadeOut();
            }, 3000);
        }
        
        updateCount(count) {
            if (this.countElement.length) {
                this.countElement.text(count);
            }
        }
        
        showSuccess() {
            this.successMessage.fadeIn();
        }
        
        showError(message) {
            this.messageContainer.text(message).fadeIn();
            
            // Hide error after 5 seconds
            const self = this;
            setTimeout(function() {
                self.messageContainer.fadeOut();
            }, 5000);
        }
        
        hideMessages() {
            this.messageContainer.hide();
            this.successMessage.hide();
        }
        
        showLoading() {
            this.loadingIndicator.fadeIn();
        }
        
        hideLoading() {
            this.loadingIndicator.fadeOut();
        }
    }
    
    // Initialize widgets
    $(document).ready(function() {
        $('.ihumbak-wrs-widget').each(function() {
            new RatingWidget(this);
        });
    });
    
})(jQuery);
