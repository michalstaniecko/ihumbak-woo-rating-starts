# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [1.3.0] - 2026-05-17

### Added (issue #12 — multilingual email templates — WPML / Polylang)
- Nowa klasa `Ihumbak_WRS_Multilingual` (`includes/class-multilingual.php`) — statyczny helper detekcji WPML i Polylang. Metody: `is_active()`, `get_provider()`, `get_active_languages()`, `get_default_language()`, `get_order_language(WC_Order)`, `is_valid_lang_code(string)`. Memoizacja wyników w statycznym cache. Gdy żadna wtyczka wielojęzyczna nie jest aktywna — każda metoda zwraca bezpieczny no-op. WPML wygrywa gdy obie wtyczki aktywne jednocześnie.
- Nowa klasa `Ihumbak_WRS_Email_Template_Resolver` (`includes/class-email-template-resolver.php`) — bezstanowy resolver opcji szablonu e-mail z obsługą wielojęzyczności. Metody statyczne: `get_subject(?string $lang)`, `get_heading(?string $lang)`, `get_body(?string $lang)`. Fallback per-pole: brak wtyczki → klucz bazowy; null/domyślny/nieprawidłowy kod → klucz bazowy; pusta wartość zlokalizowana (po trim) → klucz bazowy.
- Nowe opcje WordPress (`wp_options`) per-język dla niedomyślnych języków: `ihumbak_wrs_email_subject_{lang}` (sanitize_text_field), `ihumbak_wrs_email_heading_{lang}` (sanitize_text_field), `ihumbak_wrs_email_body_{lang}` (wp_kses_post). Język domyślny nadal używa kluczy bazowych — brak migracji, brak duplikacji.
- Dynamiczne sekcje ustawień per-język w panelu "Email Review Requests" — po sekcji diagnostyki wyświetlane są dodatkowe sekcje dla każdego niedomyślnego aktywnego języka (tytuł dwujęzyczny z natywną nazwą języka), każda z trzema polami: Temat, Nagłówek, Treść. Sekcje i pola rejestrowane są wyłącznie gdy WPML lub Polylang jest aktywny.
- Cztery nowe renderery w `Ihumbak_WRS_Admin_Email_Settings`: `render_lang_section_intro()`, `render_localized_subject(array)`, `render_localized_heading(array)`, `render_localized_body(array)`. Body używa `wp_editor()` z dopasowanym `textarea_id`/`textarea_name`. Każde pole zawiera notatkę o fallback do języka domyślnego.
- Cleanup per-językowy w `uninstall.php`: `DELETE FROM wp_options WHERE option_name LIKE 'ihumbak_wrs_email_{subject|heading|body}_%'` — bezpieczny, nie usuwa kluczy bazowych (brak trailing `_` segment w kluczu bazowym).

### Added (issue #11 — email send log, opt-in)
- Opt-in log wysyłek e-maili z prośbą o ocenę (domyślnie wyłączony). Nowa opcja `ihumbak_wrs_email_log_enabled` (bool, default `false`) sterująca persystencją wpisów.

### Added (issue #10 — coupon incentive placeholder)
- Nowa opcja `ihumbak_wrs_email_coupon_id` (int, domyślnie `0`) — wybór opublikowanego kuponu WooCommerce dołączanego do wiadomości z prośbą o ocenę. Sanitizacja: `absint`. Cleanup w `uninstall.php`.
- Nowy placeholder `{coupon_code}` — wstawia kod wybranego kuponu do tematu i treści wiadomości. Gdy kupon nie jest skonfigurowany lub nie jest opublikowany, placeholder zastępowany jest pustym ciągiem. / New `{coupon_code}` placeholder — inserts the configured coupon code into the email subject and body. Collapses to empty string when no coupon is configured or the coupon is no longer published.
- Prywatna metoda `Ihumbak_WRS_Email_Sender::resolve_coupon_code(): string` — rozwiązuje kod kuponu z opcji `ihumbak_wrs_email_coupon_id`; zwraca verbatim `post_title` lub pusty ciąg.
- Selektor kuponu w panelu ustawień e-mail (sekcja „Treść wiadomości / Email content") — lista opublikowanych kuponów lub „— brak / none —" gdy WooCommerce jest niedostępny.
- `build_fake_context()` używa skonfigurowanego kuponu gdy dostępny; fallback do przykładowego kodu `PRZYKLAD10` gdy brak konfiguracji.

### Changed (issue #12)
- `Ihumbak_WRS_Email_Sender::process()` — krok 9 zastąpiony wywołaniami `Ihumbak_WRS_Email_Template_Resolver::get_{subject|heading|body}(Multilingual::get_order_language($order))` zamiast bezpośrednich `get_option()`.
- `Ihumbak_WRS_Email_Sender::send_test()` — pobieranie szablonu przeniesione po rozwiązaniu kontekstu zamówienia; `raw_subject/heading/body` rozwiązywane przez resolver z językiem zamówienia (lub null gdy brak zamówienia).
- Wersja: `1.2.1` → `1.3.0`.

## [1.2.1] - 2026-05-16

### Added (issue #21 — WooCommerce email template wrap)
- Nowa opcja `ihumbak_wrs_email_heading` (typ string, domyślnie pusty ciąg) — konfigurowalny nagłówek wewnętrzny wiadomości e-mail wyświetlany jako `<h1>` wewnątrz szablonu WooCommerce. Obsługuje placeholder-y skalarne (identyczne jak temat). Gdy puste — stosowana jest domyślna wartość „Twoja opinia jest dla nas ważna".
- Prywatna metoda `Ihumbak_WRS_Email_Sender::wrap_with_wc_template( string $body_html, string $heading ): string` owijająca wyrenderowaną treść wiadomości w domyślny szablon transakcyjny WooCommerce. Preferuje `WC_Emails::apply_transactional_email_template()` (WC 3.7+), fallback do `style_inline( wrap_message() )`, ostatni fallback do samego `wrap_message()`. Chroniona przed wyjątkami (try/catch Throwable). Gdy WC jest niedostępne — zwraca niezmienione `$body_html`.
- Prywatna metoda `Ihumbak_WRS_Email_Sender::default_heading(): string` zwracająca przetłumaczony domyślny nagłówek.
- Pole „Nagłówek wiadomości / Email heading" w sekcji „Treść wiadomości" panelu ustawień e-mail, umieszczone między Subject a Body.
- Cleanup w `uninstall.php`: usuwanie opcji `ihumbak_wrs_email_heading` przy deinstalacji pluginu.
- Testy CLI w `tests/test-email-template.php`: sekcja 11 (to_plain_text z owiniętem HTML WC) i sekcja 12 (placeholder-y w nagłówku).

### Changed (issue #21)
- `Ihumbak_WRS_Email_Sender::process()` i `send_test()` renderują teraz nagłówek z opcji `ihumbak_wrs_email_heading` (lub wartości domyślnej) i przekazują go do `wrap_with_wc_template()` przed `dispatch_raw()`.
- Wersja: `1.2.0` → `1.2.1`.

## [1.2.0] - 2026-05-16

### Added (issue #9 — follow-up reminders)
- Opcja `ihumbak_wrs_email_followups` umozliwiajaca konfiguracje 0-3 przypomnien (follow-up) po wysylce poczatkowej. Kazde przypomnienie ma wlasne opoznienie (dni) i niezaleznie respektuje reguly pomijania.
- Nowa klasa `Ihumbak_WRS_Email_Followup_Scheduler` nasluchujaca `ihumbak_wrs_email_send_complete` i planujaca kolejny krok przez Action Scheduler.
- UI w ustawieniach: sekcja "Przypomnienia (follow-up)" z repeaterem 0-3 wpisow (dodaj/usun/zmien kolejnosc), realizowanym przez waniliowy JavaScript.
- Meta-box "Wiadomosci z prosba o ocene / Review request emails" na ekranie edycji zamowienia (klasyczny CPT i HPOS). Pokazuje zaplanowane kroki AS dla zamowienia z timestampami, skrocona konfiguracje follow-upow i hint, ze reczna wysylka rowniez uruchamia skonfigurowany lancuch przypomnien.
- Statyczny helper `Ihumbak_WRS_Email_Scheduler::get_pending_steps_for_order( int $order_id ): array` zwracajacy mape `step => timestamp` dla biezacych zaplanowanych zadan AS dla zamowienia.

### Added (issue #8 — admin tools: test send + manual resend)
- New admin tool: test email button on the Email Review Requests settings page. Sends a rendered preview of the current subject/body template to a configurable recipient (defaults to the currently logged-in user). Uses the most recent completed order as sample context, falls back to a hardcoded fake context when no completed orders exist. Requires `manage_woocommerce` capability and a valid nonce. Does not write any persistent state (no log entry, no scheduled job).
- New admin tool: manual resend action on the WooCommerce order edit screen ("Wyślij e-mail z prośbą o ocenę / Send review request email"). Triggers `Ihumbak_WRS_Email_Sender::send_for_order()` for the current order, respecting all skip rules. Skip reason is reported in the admin notice. A private order note is always written for audit trail purposes.
- New class `Ihumbak_WRS_Admin_Email_Tools` (`admin/class-admin-email-tools.php`) providing the test-send form and order-action handler. Both actions are guarded by `manage_woocommerce` + nonce.
- New value object `Ihumbak_WRS_Email_Send_Result` (`includes/class-email-send-result.php`) representing the outcome of an email dispatch. Carries status (sent/skipped/failed), reason (REASON_* constant), translated message, and `is_test` flag — accessed via `get_status()`, `get_reason()`, `get_message()`, `is_test()` getters (properties are private to enforce immutability). Used internally by sender and admin tools.
- New REASON_NO_ITEMS constant on `Ihumbak_WRS_Email_Send_Result` — reported when an order has no `line_item` entries at all (edge case: refunds-only orders). Previously such orders were reported as `REASON_ALL_EXCLUDED`, conflating "nothing to send" with "filtered out by config".
- New action hook `ihumbak_wrs_email_send_complete( Ihumbak_WRS_Email_Send_Result $result, int $order_id, int $step )` fired by the sender after `process()` returns in both AS-driven (`handle_send`) and manual-resend (`send_for_order`) paths. Forward-looking integration point for the future email log feature. Test sends (`send_test`) never fire this hook.

### Added (issue #7 — products list + rating links placeholders)
- New class `Ihumbak_WRS_Email_Product_List` (`includes/class-email-product-list.php`) implementing `{products_list}` and `{rating_links_list}` email template placeholders.
- `{products_list}` renders an HTML `<ul>` of purchased products with links to their product pages.
- `{rating_links_list}` renders an HTML `<ul>` of purchased products with deep-link URLs pointing directly to the rating widget anchor (`#ihumbak-wrs-rate`).
- Rating widget (`templates/widget-stars.php`) now carries `id="ihumbak-wrs-rate"` and `tabindex="-1"` on its root element, making it a stable deep-link target.
- JavaScript (`assets/js/rating-widget.js`): when a page is loaded with `#ihumbak-wrs-rate` in the URL, the widget is smooth-scrolled into view and briefly highlighted.
- CSS (`assets/css/rating-widget.css`): new `@keyframes ihumbak-wrs-deep-link-pulse` animation and `.ihumbak-wrs-widget.ihumbak-wrs-deep-link-highlight` rule for the arrival highlight effect.

### Changed (issue #9)
- `Ihumbak_WRS_Email_Scheduler::STEPS` rozszerzony z `[0]` do `[0, 1, 2, 3]`; dodano stala `MAX_FOLLOWUPS = 3` i statyczna metode `schedule_followup()`. Anulowanie wysylek przy zwrocie/anulowaniu zamowienia czysci rowniez zaplanowane przypomnienia (kroki 1-3).

### Changed (issue #8)
- `Ihumbak_WRS_Email_Sender::process()` now returns `Ihumbak_WRS_Email_Send_Result` (was void). `handle_send()` (AS-driven) and `send_for_order()` (manual resend) consume the result internally to fire the new `ihumbak_wrs_email_send_complete` hook; Action Scheduler still receives `void` and behavior for scheduled retries is unchanged.
- `Ihumbak_WRS_Email_Sender::process()` disambiguates the three consecutive empty-items checks: the first (after `get_items()`) reports `REASON_NO_ITEMS`; the second (after `filter_excluded_items()`) reports `REASON_ALL_EXCLUDED`; the third (after `filter_already_rated_items()`) reports `REASON_ALL_ALREADY_RATED`.
- `Ihumbak_WRS_Email_Sender::dispatch()` refactored into `dispatch_raw(string $to, string $subject, string $body_html): bool` — no longer receives a `WC_Order` argument (removed implicit logging dependency). Used by both `process()` and `send_test()`.
- New public methods on `Ihumbak_WRS_Email_Sender`: `send_for_order(int $order_id, int $step): Ihumbak_WRS_Email_Send_Result` and `send_test(string $recipient, ?int $sample_order_id): Ihumbak_WRS_Email_Send_Result`.
- `admin/class-admin-email-settings.php`: `render_page()` now fires `do_action('ihumbak_wrs_after_email_settings_form')` after the closing `</form>` tag, enabling external injection of the test-send card.

### Changed (issue #7)
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
