# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- New admin tool: test email button on the Email Review Requests settings page. Sends a rendered preview of the current subject/body template to a configurable recipient (defaults to the currently logged-in user). Uses the most recent completed order as sample context, falls back to a hardcoded fake context when no completed orders exist. Requires `manage_woocommerce` capability and a valid nonce. Does not write any persistent state (no log entry, no scheduled job) — closes #8.
- New admin tool: manual resend action on the WooCommerce order edit screen ("Wyślij e-mail z prośbą o ocenę / Send review request email"). Triggers `Ihumbak_WRS_Email_Sender::send_for_order()` for the current order, respecting all skip rules. Skip reason is reported in the admin notice. A private order note is always written for audit trail purposes — closes #8.
- New class `Ihumbak_WRS_Admin_Email_Tools` (`admin/class-admin-email-tools.php`) providing the test-send form and order-action handler. Both actions are guarded by `manage_woocommerce` + nonce.
- New value object `Ihumbak_WRS_Email_Send_Result` (`includes/class-email-send-result.php`) representing the outcome of an email dispatch. Carries status (sent/skipped/failed), reason (REASON_* constant), translated message, and `is_test` flag — accessed via `get_status()`, `get_reason()`, `get_message()`, `is_test()` getters (properties are private to enforce immutability). Used internally by sender and admin tools.
- New REASON_NO_ITEMS constant on `Ihumbak_WRS_Email_Send_Result` — reported when an order has no `line_item` entries at all (edge case: refunds-only orders). Previously such orders were reported as `REASON_ALL_EXCLUDED`, conflating "nothing to send" with "filtered out by config".
- New action hook `ihumbak_wrs_email_send_complete( Ihumbak_WRS_Email_Send_Result $result, int $order_id, int $step )` fired by the sender after `process()` returns in both AS-driven (`handle_send`) and manual-resend (`send_for_order`) paths. Forward-looking integration point for the future email log feature (issue #11). Test sends (`send_test`) never fire this hook.
- New class `Ihumbak_WRS_Email_Product_List` (`includes/class-email-product-list.php`) implementing `{products_list}` and `{rating_links_list}` email template placeholders (issue #7).
- `{products_list}` renders an HTML `<ul>` of purchased products with links to their product pages.
- `{rating_links_list}` renders an HTML `<ul>` of purchased products with deep-link URLs pointing directly to the rating widget anchor (`#ihumbak-wrs-rate`).
- Rating widget (`templates/widget-stars.php`) now carries `id="ihumbak-wrs-rate"` and `tabindex="-1"` on its root element, making it a stable deep-link target.
- JavaScript (`assets/js/rating-widget.js`): when a page is loaded with `#ihumbak-wrs-rate` in the URL, the widget is smooth-scrolled into view and briefly highlighted.
- CSS (`assets/css/rating-widget.css`): new `@keyframes ihumbak-wrs-deep-link-pulse` animation and `.ihumbak-wrs-widget.ihumbak-wrs-deep-link-highlight` rule for the arrival highlight effect.

### Changed
- `Ihumbak_WRS_Email_Sender::process()` now returns `Ihumbak_WRS_Email_Send_Result` (was void). `handle_send()` (AS-driven) and `send_for_order()` (manual resend) consume the result internally to fire the new `ihumbak_wrs_email_send_complete` hook; Action Scheduler still receives `void` and behavior for scheduled retries is unchanged.
- `Ihumbak_WRS_Email_Sender::process()` disambiguates the three consecutive empty-items checks: the first (after `get_items()`) reports `REASON_NO_ITEMS`; the second (after `filter_excluded_items()`) reports `REASON_ALL_EXCLUDED`; the third (after `filter_already_rated_items()`) reports `REASON_ALL_ALREADY_RATED`.
- `Ihumbak_WRS_Email_Sender::dispatch()` refactored into `dispatch_raw(string $to, string $subject, string $body_html): bool` — no longer receives a `WC_Order` argument (removed implicit logging dependency). Used by both `process()` and `send_test()`.
- New public methods on `Ihumbak_WRS_Email_Sender`: `send_for_order(int $order_id, int $step): Ihumbak_WRS_Email_Send_Result` and `send_test(string $recipient, ?int $sample_order_id): Ihumbak_WRS_Email_Send_Result`.
- `admin/class-admin-email-settings.php`: `render_page()` now fires `do_action('ihumbak_wrs_after_email_settings_form')` after the closing `</form>` tag, enabling external injection of the test-send card.
- `Ihumbak_WRS_Email_Sender::process()` now uses the new `build_html_context()` method for HTML body rendering; subject rendering substitutes empty strings for `products_list` and `rating_links_list` to prevent raw HTML leaking into the mail subject line.
- `Ihumbak_WRS_Email_Template::KNOWN_PLACEHOLDERS` updated to include `products_list` and `rating_links_list`.
- Admin settings UI (`admin/class-admin-email-settings.php`): disclaimer updated to reflect that `{products_list}` and `{rating_links_list}` are now fully implemented; only `{coupon_code}` remains pending.

## [1.1.1] - 2026-04-03

## [1.1.0] - 2026-04-03

## [1.0.7] - 2025-11-19

### Added
- ✅ **Opcja ukrywania liczby ocen w loop** - nowe ustawienie w Settings
- ✅ "Hide Count in Product Loop" - ukrywa tekst "(25)" przy gwiazdkach
- ✅ Zachowuje gwiazdki widoczne (tylko ukrywa liczbę)
- ✅ Działa dla: shop page, category, archive, related, upsells, cross-sells
- ✅ Implementacja przez CSS (lekka, nie wpływa na performance)

### Changed
- Admin Settings: nowa opcja checkbox
- WooCommerce Integration: CSS injection w `wp_head`
- Targeting specific selectors dla WooCommerce loop

### Use Case
Dla sklepów które chcą pokazać tylko gwiazdki wizualnie, bez liczby ocen w tekście.

## [1.0.6] - 2025-11-19

### Fixed
- ✅ **Gwiazdki na liście produktów** teraz pokazują kombinowaną średnią (quick + reviews)
- ✅ Automatyczna aktualizacja WooCommerce meta (`_wc_average_rating`, `_wc_rating_count`)
- ✅ Rozszerzone czyszczenie cache po każdej ocenie
- ✅ Synchronizacja ratingu we wszystkich miejscach WooCommerce (shop loop, widgets, related products)

### Added
- Metoda `update_wc_product_meta()` w Rating Model
- Metoda `sync_product_rating_meta()` w WooCommerce Integration
- Hooki dla aktualizacji meta przy zmianach recenzji
- Czyszczenie WooCommerce transients (`wc_delete_product_transients()`)
- Usuwanie WC meta przy cache clear (force recalculation)

### Changed
- Rating Model: rozszerzone `clear_product_cache()` - czyści wszystkie transients
- Rating Model: wywołanie `update_wc_product_meta()` po add/update/delete rating
- WooCommerce Integration: hooki dla comment_post, edit_comment, trashed_comment, etc.
- Post meta `_wc_average_rating` aktualizowane automatycznie przy każdej zmianie

### Technical Details
**Problem:** WooCommerce cache'ował stare wartości rating w loop products
**Rozwiązanie:** 
1. Aktualizacja WC meta po każdej zmianie quick rating
2. Czyszczenie wszystkich cache WooCommerce
3. Synchronizacja przy zmianach recenzji (comments)
4. Force recalculation przez usuwanie meta

## [1.0.5] - 2025-11-19

### Added
- ✅ **Admin Only Mode (Debug)** - nowa opcja w ustawieniach
- ✅ Widget widoczny tylko dla zalogowanych administratorów
- ✅ Idealne do testowania przed uruchomieniem na produkcji
- ✅ Wizualne ostrzeżenie dla admina w widgecie
- ✅ Sprawdzanie `current_user_can('manage_options')` w Frontend Render i Assets Manager

### Changed
- Nowe ustawienie: "Admin Only Mode (Debug)" w Settings
- Template pokazuje żółte ostrzeżenie dla adminów w trybie debug
- Frontend Render i Assets Manager sprawdzają tryb admin-only
- Domyślna wartość: 'no' (wyłączone)

### Use Cases
- 🧪 Testowanie pluginu przed publicznym uruchomieniem
- 🔍 Debugowanie problemów z widgetem
- 🎨 Sprawdzanie wyglądu i pozycji widgetu
- ✅ Weryfikacja funkcjonalności oceniania

## [1.0.4] - 2025-11-19

### Added
- ✅ **Schema.org structured data** for SEO and Google Rich Snippets
- ✅ **JSON-LD markup** with Product and AggregateRating types
- ✅ **Microdata attributes** (itemprop) in HTML widget for backward compatibility
- ✅ Integration with WooCommerce's built-in schema
- ✅ Support for Google Rich Results (star ratings in search)
- ✅ Proper differentiation between `reviewCount` (text reviews) and `ratingCount` (all ratings)

### Changed
- New class `Ihumbak_WRS_Schema_Markup` for handling structured data
- Template updated with microdata attributes (itemprop, itemscope, itemtype)
- Meta tags added for ratingValue, bestRating, worstRating, ratingCount

### SEO Features
- ✅ Google Rich Snippets compatible
- ✅ Schema.org Product markup
- ✅ AggregateRating with proper counts
- ✅ Price and availability information
- ✅ Product image, SKU, brand support
- ✅ Valid for Google Search Console testing

## [1.0.3] - 2025-11-18

### Fixed
- Fixed success message not displaying immediately after rating
- Success message element is now always present in DOM (hidden by default)
- Updated JavaScript to show success message from server response
- Success message now displays text from response or uses default text
- Fixed JavaScript condition to properly detect successful responses

### Changed
- Template now always renders success message container (hidden when no rating)
- Improved `handleSuccess()` to update message text from response

## [1.0.2] - 2025-11-18

### Fixed
- Fixed `invalid_nonce` error in REST API endpoint
- Changed nonce handling to use WordPress REST API standard (`wp_rest` nonce)
- Nonce is now sent in HTTP header `X-WP-Nonce` instead of request body
- Removed nonce parameter from REST API endpoint registration
- Updated JavaScript to use `beforeSend` for setting nonce header

### Changed
- REST API authentication now follows WordPress REST API best practices
- Simplified nonce generation - uses single `wp_rest` nonce for all requests

## [1.0.1] - 2025-11-18

### Fixed
- Fixed fatal error when `global $product` is a string instead of object
- Added proper type checking and fallback to `wc_get_product()` in Assets Manager
- Added proper type checking and fallback to `wc_get_product()` in Frontend Render
- Added `is_a()` check to verify product is a WC_Product instance

## [1.0.0] - 2025-11-18

### Added
- Initial release
- Quick rating system with star widget
- Integration with WooCommerce reviews
- Combined average calculation
- REST API endpoints for rating submission and statistics
- Admin panel for managing ratings
- Settings page with customization options
- Rate limiting and security features
- Responsive design for mobile devices
- Support for logged-in and anonymous users
- Custom database table for ratings
- Caching for improved performance
- Distribution statistics (5★, 4★, 3★, 2★, 1★)
- Admin column in product list showing quick ratings
- Nonce verification for security
- IP-based rate limiting
- User-friendly error messages
- Success notifications
- Customizable star colors
- Customizable widget position
- Customizable text messages

### Security
- Nonce verification in AJAX requests
- Input sanitization and validation
- Rate limiting (1 rating per 10 minutes per IP)
- SQL injection prevention
- XSS protection
- CSRF protection

### Performance
- Transient caching for statistics
- Optimized database queries
- Efficient indexing
- Lazy loading of assets

### Compatibility
- WordPress 5.9+
- WooCommerce 6.0+
- PHP 7.4+
- PHP 8.0+
- PHP 8.1+

## Future Plans

### [1.1.0] - Planned
- [ ] Internationalization (i18n) support
- [ ] Additional language translations
- [ ] Email notifications for new ratings
- [ ] Rating moderation system
- [ ] Export ratings to CSV
- [ ] Advanced statistics dashboard
- [ ] Rating widget shortcode
- [ ] Integration with review reminder emails

### [1.2.0] - Planned
- [ ] Half-star ratings option
- [ ] Rating categories (quality, value, etc.)
- [ ] Rating images/photos
- [ ] Social sharing of ratings
- [ ] Rating badges for top-rated products
- [ ] Bulk rating management
- [ ] Import/export ratings

## Notes

- All dates are in YYYY-MM-DD format
- Version numbering follows Semantic Versioning (SemVer)
- [Unreleased] section tracks upcoming features
