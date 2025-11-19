# Plan Prac - WooCommerce Quick Ratings & Reviews

## Faza 1: Przygotowanie (1-2 dni)

### 1.1 Struktura projektu
- [ ] Crear strukturę katalogów:
  - `/ihumbak-woo-rating-stars/` - główny katalog
  - `/includes/` - główne klasy
  - `/admin/` - panel administratora
  - `/public/` - frontend
  - `/assets/` - CSS, JS
  - `/templates/` - szablony
  - `/database/` - migracje DB
- [ ] Stworzyć plik `ihumbak-woo-rating-stars.php` (główny plik pluginu)
- [ ] Stworzyć `plugin-header.php` z metadanymi pluginu
- [ ] Stworzyć `.gitignore`

### 1.2 Konfiguracja podstawowa
- [ ] Zdefiniować stałe pluginu (PREFIX, VERSION, PATH)
- [ ] Stworzyć klasy autoloadera
- [ ] Inicjalizacja pluginu (`plugin_loaded` hook)

---

## Faza 2: Backend - Baza danych (2-3 dni)

### 2.1 Migracja bazy danych
- [ ] Stworzyć klasę `Database_Migration`
- [ ] Zaimplementować tworzenie tabeli `wp_woo_quick_ratings`
- [ ] Dodać indeksy (product_id, user_id, ip_address)
- [ ] Zarejestrować hook `register_activation_hook()`

### 2.2 Model ocen
- [ ] Stworzyć klasę `Rating_Model`
  - [ ] `get_all_ratings($product_id)`
  - [ ] `get_user_rating($product_id, $user_id, $ip)`
  - [ ] `add_rating($product_id, $rating, $user_id, $ip, $user_agent)`
  - [ ] `update_rating($rating_id, $new_rating)`
  - [ ] `delete_rating($rating_id)`
  - [ ] `check_rate_limit($ip, $product_id)`

### 2.3 Logika obliczania średniej
- [ ] Stworzyć klasę `Rating_Calculator`
  - [ ] `get_quick_ratings_average($product_id)` - średnia z ocen
  - [ ] `get_reviews_average($product_id)` - średnia z recenzji WooCommerce
  - [ ] `get_combined_average($product_id)` - średnia połączona
  - [ ] `get_rating_distribution($product_id)` - rozkład (5★, 4★ itd.)

---

## Faza 3: Backend - REST API (3-4 dni)

### 3.1 Rejestracja endpointów
- [ ] Stworzyć klasę `REST_API_Handler`
- [ ] Zarejestrować `POST /wp-json/woo-quick-ratings/v1/rate`
  - [ ] Walidacja: ocena 1-5, product_id, nonce
  - [ ] Check: czy użytkownik już ocenił
  - [ ] Check: rate limiting (1 ocena na 10 minut)
  - [ ] Zwrot: sukces/błąd + komunikat
- [ ] Zarejestrować `GET /wp-json/woo-quick-ratings/v1/stats/{product_id}`
  - [ ] Zwrot: średnia, liczba ocen, rozkład

### 3.2 Bezpieczeństwo API
- [ ] Nonce verification
- [ ] Sanitization danych wejściowych
- [ ] Walidacja product_id (czy produkt istnieje)
- [ ] Rate limiting per IP
- [ ] CORS headers

### 3.3 Opcjonalna autoryzacja
- [ ] Logika: jeśli włączyć opcję "wymagaj logowania"
  - [ ] Sprawdzenie `is_user_logged_in()`
  - [ ] Zwrot 403 jeśli nie zalogowany

---

## Faza 4: Backend - Integracja WooCommerce (2-3 dni)

### 4.1 Modyfikacja średniej oceny
- [ ] Stworzyć klasę `WooCommerce_Integration`
- [ ] Hook: `woocommerce_product_get_average_rating()` - zwrot średniej połączonej
- [ ] Hook: `woocommerce_product_get_rating_count()` - zwrot liczby wszystkich ocen

### 4.2 Admin panel
- [ ] Stworzyć klasę `Admin_Panel`
- [ ] Menu: "Oceny" w WordPress Admin
- [ ] Lista ocen dla produktu z filtrami (data, gwiazdy, użytkownik)
- [ ] Akcje: usunięcie oceny, edycja
- [ ] Statystyki: liczba ocen, średnia, rozkład

### 4.3 Kolumna w liście produktów
- [ ] Dodać kolumnę "Oceny" w liście produktów
- [ ] Wyświetlić: średnią + liczę ocen

---

## Faza 5: Frontend - Widżet i Interakcja (3-4 dni)

### 5.1 Szablon widżetu
- [ ] Stworzyć `templates/widget-stars.php`
  - [ ] 5 interaktywnych gwiazdek (SVG lub ⭐)
  - [ ] Hover effect: podświetlenie
  - [ ] Licznik ocen obok gwiazdek
  - [ ] Tekst: "Oceń ten produkt"
- [ ] Szablon: widok po kliknięciu gwiazdki
  - [ ] Wyświetlenie: "Dziękuję za ocenę!"
  - [ ] Możliwość zmiany (klik ponownie)

### 5.2 Hook do strony produktu
- [ ] Stworzyć klasę `Frontend_Render`
- [ ] Funkcja: `woo_qr_render_stars_widget()`
- [ ] Hook: `woocommerce_single_product_summary` (priority: 7)
  - [ ] Wyświetlenie widżetu obok ceny
- [ ] Alternatywa: custom hook dla flexibilności

### 5.3 JavaScript - obsługa interakcji
- [ ] Stworzyć `assets/js/rating-widget.js`
  - [ ] Event listener na klik gwiazdki
  - [ ] Wyświetlenie hover effect
  - [ ] AJAX call do REST API
  - [ ] Animacja: potwierdzenie oceny
  - [ ] Obsługa błędów
  - [ ] Loading state
- [ ] Pobranie localStoraga: czy użytkownik już ocenił (offline)

### 5.4 CSS - stylizacja
- [ ] Stworzyć `assets/css/rating-widget.css`
  - [ ] Gwiazdki: rozmiar, kolor, hover
  - [ ] Responsivność (mobile)
  - [ ] Animacje: fade-in, scale
  - [ ] Tooltip: "Kliknij, aby ocenić"

### 5.5 Załadowanie assets
- [ ] Hook: `wp_enqueue_scripts()`
  - [ ] Załaduj JS tylko na stronie produktu
  - [ ] Załaduj CSS tylko na stronie produktu
  - [ ] Inline JS: nonce, product_id, AJAX URL

---

## Faza 6: Admin Settings (2 dni)

### 6.1 Strona ustawień
- [ ] Stworzyć `admin/settings-page.php`
- [ ] Ustawienia:
  - [ ] Włącz/wyłącz system ocen
  - [ ] Wymagaj logowania (checkbox)
  - [ ] Pozycja widżetu (select: przed ceną / po cenie / custom)
  - [ ] Wyświetlaj liczbę ocen (checkbox)
  - [ ] Kolor gwiazdek (color picker)
  - [ ] Tekst przycisków (text input)

### 6.2 Zapis ustawień
- [ ] Funkcje: `get_option()`, `update_option()`
- [ ] Walidacja i sanityzacja
- [ ] Default values

---

## Faza 7: Testy i debugowanie (3-4 dni)

### 7.1 Testy manualne
- [ ] Test na WooCommerce 8.x+ (ostatnia wersja)
- [ ] Test: klik na gwiazdkę - dodanie oceny
- [ ] Test: zmiana oceny - update oceny
- [ ] Test: rate limiting - blokada po 10 minutach
- [ ] Test: zalogowany użytkownik vs anonimowy
- [ ] Test: średnia oceny (recenzje + oceny)
- [ ] Test: mobile - responsywność
- [ ] Test: admin panel - lista ocen, usuwanie

### 7.2 Testy bezpieczeństwa
- [ ] Nonce verification
- [ ] Injection attacks (SQL, XSS)
- [ ] CSRF protection
- [ ] Rate limiting bypass
- [ ] Permissions check

### 7.3 Optymalizacja
- [ ] Cachowanie średniej oceny (transient)
- [ ] Zmniejszenie query'a do DB
- [ ] Minifikacja JS/CSS

### 7.4 Kompatybilność
- [ ] Test: PHP 7.4+, 8.0+, 8.1+
- [ ] Test: WordPress 5.9+
- [ ] Test: WooCommerce 6.0+
- [ ] Test: różne motywy (Storefront, itp.)

---

## Faza 8: Dokumentacja i publikacja (1-2 dni)

### 8.1 Dokumentacja
- [ ] README.md - instrukcja instalacji
- [ ] CHANGELOG.md - historia zmian
- [ ] Komentarze w kodzie - funkcje public
- [ ] Admin panel - help text

### 8.2 Publikacja
- [ ] Przygotowanie do WordPress Plugin Repository (opcjonalnie)
- [ ] SVN structure (trunk, tags, branches)
- [ ] Banner i ikona pluginu (300x300px)
- [ ] Screenshots z instrukcjami

---

## Timeline (szacunkowy)

| Faza | Dni | Data początkowa | Data końcowa |
|------|-----|-----------------|--------------|
| 1. Przygotowanie | 2 | 2025-11-18 | 2025-11-19 |
| 2. Baza danych | 3 | 2025-11-20 | 2025-11-22 |
| 3. REST API | 4 | 2025-11-23 | 2025-11-26 |
| 4. WooCommerce Integration | 3 | 2025-11-27 | 2025-11-29 |
| 5. Frontend & Widget | 4 | 2025-11-30 | 2025-12-03 |
| 6. Admin Settings | 2 | 2025-12-04 | 2025-12-05 |
| 7. Testy | 4 | 2025-12-06 | 2025-12-09 |
| 8. Dokumentacja | 2 | 2025-12-10 | 2025-12-11 |
| **RAZEM** | **24 dni** | **2025-11-18** | **2025-12-11** |

---

## Priorytety

### High (wymagane):
- Baza danych + model
- REST API + ocena
- Widżet + interakcja
- Średnia połączona

### Medium (ważne):
- Admin panel
- Ustawienia pluginu
- Bezpieczeństwo (nonce, sanityzacja)

### Low (nice-to-have):
- Cachowanie
- Screenshots do WordPress Repo
- i18n (tłumaczenia)

---

## Notatki

- Upewnij się, że kod jest skompatybilny z najnowszą wersją WooCommerce
- Testuj na różnych urządzeniach (mobile, tablet, desktop)
- Dodaj logging dla debugowania
- Przygotuj migrację dla starszych wersji pluginu (jeśli będzie update)

