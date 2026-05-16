# Historia Wersji - WooCommerce Quick Ratings & Reviews

## [Unreleased] — issue #10: Coupon placeholder

### Dodane
- Opcja `ihumbak_wrs_email_coupon_id` (int, domyślnie 0) — wybór kuponu WooCommerce
  dołączanego do wiadomości z prośbą o ocenę.
- Placeholder `{coupon_code}` — zaimplementowany (wcześniej oczekiwał).
- Prywatna metoda `Ihumbak_WRS_Email_Sender::resolve_coupon_code()` — rozwiązuje
  kod kuponu; zwraca verbatim `post_title` gdy kupon jest opublikowany lub pusty ciąg.
- Selektor kuponu w ustawieniach e-mail (sekcja "Treść wiadomości / Email content").
- `build_fake_context()` używa skonfigurowanego kuponu lub fallback `PRZYKLAD10`.

**Zmienione pliki:**
- `includes/class-email-template.php`
- `includes/class-email-sender.php`
- `admin/class-admin-email-settings.php`
- `uninstall.php`
- `CHANGELOG.md`
- `VERSION_HISTORY.md`

---

## [Unreleased] — issue #7: Rating deep links

### Dodane
- Klasa `Ihumbak_WRS_Email_Product_List` (`includes/class-email-product-list.php`):
  wdraża placeholdery `{products_list}` (lista linków do stron produktów)
  i `{rating_links_list}` (lista linków z kotwicą `#ihumbak-wrs-rate`).
- Widget (`templates/widget-stars.php`): dodano `id="ihumbak-wrs-rate"` i `tabindex="-1"`
  do korzenia widgetu jako stabilna kotwica deep-link.
- JS (`assets/js/rating-widget.js`): obsługa `window.location.hash === '#ihumbak-wrs-rate'` —
  płynne przewinięcie do widgetu i 2-sekundowe podświetlenie przy wejściu z linku z emaila.
- CSS (`assets/css/rating-widget.css`): animacja `ihumbak-wrs-deep-link-pulse` i klasa
  `.ihumbak-wrs-widget.ihumbak-wrs-deep-link-highlight`.

### Zmienione
- `Ihumbak_WRS_Email_Sender`: nowa metoda `build_html_context()` zastępuje inline
  blok escapowania w `process()`; kontekst tematu podstawia puste ciągi dla obu nowych kluczy.
- `Ihumbak_WRS_Email_Template::KNOWN_PLACEHOLDERS`: dodano `products_list` i `rating_links_list`.
- Admin UI (`admin/class-admin-email-settings.php`): zaktualizowano disclaimer w sekcji
  treści emaila — informuje, że oba placeholdery działają; `{coupon_code}` nadal oczekiwał
  na implementację (zrealizowane w issue #10).

**Zmienione pliki:**
- `includes/class-email-product-list.php` (nowy)
- `includes/class-email-sender.php`
- `includes/class-email-template.php`
- `templates/widget-stars.php`
- `assets/js/rating-widget.js`
- `assets/css/rating-widget.css`
- `admin/class-admin-email-settings.php`

---

## v1.0.3 (2025-11-18) - CURRENT
**Status:** ✅ Stabilna

### Naprawione
- ✅ Komunikat "Thank you for your rating!" teraz wyświetla się natychmiast po ocenie
- ✅ Element success message zawsze istnieje w DOM (ukryty lub widoczny)
- ✅ Tekst komunikatu aktualizuje się z odpowiedzi serwera

**Zmienione pliki:**
- `/templates/widget-stars.php` - element success zawsze w DOM
- `/assets/js/rating-widget.js` - poprawiono handleSuccess()
- Wersja: 1.0.3

---

## v1.0.2 (2025-11-18)
**Status:** ✅ Działająca (ale bez natychmiastowego komunikatu)

### Naprawione
- ✅ Błąd `invalid_nonce` w endpoint REST API
- ✅ Zmieniono na WordPress REST API standard (`wp_rest` nonce)
- ✅ Nonce wysyłany w headerze `X-WP-Nonce`

**Zmienione pliki:**
- `/includes/class-rest-api-handler.php` - usunięto wp_verify_nonce()
- `/public/class-assets-manager.php` - wp_rest nonce
- `/assets/js/rating-widget.js` - X-WP-Nonce header
- Wersja: 1.0.2

---

## v1.0.1 (2025-11-18)
**Status:** ⚠️ Błąd invalid_nonce

### Naprawione
- ✅ Fatal error gdy `global $product` jest stringiem
- ✅ Dodano sprawdzanie typu i fallback do `wc_get_product()`
- ✅ Dodano `is_a()` check dla WC_Product

**Zmienione pliki:**
- `/public/class-assets-manager.php` - type checking
- `/public/class-frontend-render.php` - type checking
- Wersja: 1.0.1

**Znane problemy:**
- ❌ Endpoint zwraca błąd `invalid_nonce`

---

## v1.0.0 (2025-11-18)
**Status:** ⚠️ Fatal error

### Dodane
- ✅ Pełny system quick ratings
- ✅ Integracja z WooCommerce
- ✅ REST API endpoints
- ✅ Panel administracyjny
- ✅ Frontend widget
- ✅ Bezpieczeństwo i cache

**Znane problemy:**
- ❌ Fatal error: Call to a member function get_id() on string

---

## Podsumowanie Bugfixów

| Wersja | Główny Problem | Status |
|--------|---------------|--------|
| 1.0.0 | Fatal error: get_id() on string | ❌ Krytyczny |
| 1.0.1 | Endpoint invalid_nonce | ⚠️ Wysoki |
| 1.0.2 | Success message po refresh | ⚠️ Średni |
| 1.0.3 | **Wszystko działa** | ✅ Stabilny |

## Aktualna Wersja: 1.0.3

### ✅ Działające funkcje:
- System quick ratings (kliknij gwiazdkę)
- Integracja z recenzjami WooCommerce
- REST API bez błędów nonce
- Komunikat success od razu po ocenie
- Rate limiting (10 min/IP)
- Panel administracyjny
- Statystyki i rozkład ocen
- Responsywny design
- Bezpieczeństwo

### 🔧 Zalecane kolejne kroki:
1. Testy na produkcji
2. Monitoring błędów
3. Zbieranie feedbacku od użytkowników
4. Planowanie v1.1.0 z dodatkowymi funkcjami

---
Ostatnia aktualizacja: 2025-11-18 22:47
