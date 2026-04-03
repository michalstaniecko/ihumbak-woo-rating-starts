---
name: wordpress-dev
description: WordPress/WooCommerce plugin development - writing PHP classes, hooks, filters, REST API endpoints, admin pages, database migrations, frontend assets, and following WP coding standards.
argument-hint: [opis zadania do wykonania]
---

# WordPress Plugin Development

Skill odpowiedzialny za programowanie pluginow WordPress/WooCommerce. Wykonuj zadanie opisane przez uzytkownika: `$ARGUMENTS`

## Zasady ogolne

1. **WordPress Coding Standards** — stosuj oficjalne standardy kodowania WordPress:
   - PHP: prefixowane funkcje/klasy, snake_case dla funkcji, camelCase dla zmiennych JS
   - Escaping: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()` przy kazdym renderowaniu danych
   - Sanitization: `sanitize_text_field()`, `absint()`, `wp_unslash()` przy kazdym odczycie danych wejsciowych
   - Nonce verification: `wp_verify_nonce()` / `check_ajax_referer()` w kazdym formularzu i AJAX
   - Prepared statements: `$wpdb->prepare()` dla kazdego zapytania SQL z parametrami

2. **Bezpieczenstwo** — nigdy nie ufaj danym od uzytkownika:
   - Waliduj, sanityzuj, escapuj — w tej kolejnosci
   - Sprawdzaj uprawnienia (`current_user_can()`) przed kazdym dzialaniem administracyjnym
   - Uzywaj nonce'ow we wszystkich formularzach i endpointach REST
   - Nigdy nie uzywaj `eval()`, `extract()`, `$$var`

3. **Kompatybilnosc** — PHP 7.4+, WordPress 6.0+, WooCommerce 8.0+:
   - Deklaruj kompatybilnosc z HPOS (`custom_order_tables`) jesli plugin integruje sie z WooCommerce
   - Deklaruj kompatybilnosc z Cart/Checkout Blocks jesli dotyczy
   - Uzywaj `wp_enqueue_script/style()` zamiast bezposredniego linkowania

## Struktura klas i plikow

- Jedna klasa na plik, nazwa pliku: `class-{nazwa-klasy-lowercase}.php`
- Klasa `Prefix_Some_Name` -> plik `class-some-name.php`
- Katalogi: `includes/` (logika), `admin/` (panel admina), `public/` (frontend), `database/` (migracje), `assets/` (CSS/JS), `templates/` (szablony)
- Singleton pattern dla glownej klasy pluginu
- Autoloader SPL mapujacy prefix klas na katalogi

## Hooks i filtry

- Rejestruj hooki w metodzie `init()` lub `__construct()` klasy
- Uzywaj specyficznych hookow zamiast generycznych (np. `woocommerce_product_get_average_rating` zamiast `the_content`)
- Prefixuj nazwy wlasnych hookow: `{plugin_prefix}_{action_name}`
- Przy usuwaniu hookow pamietaj o priorytecie

## Baza danych

- Tabele z prefixem `$wpdb->prefix`
- Migracje przez `dbDelta()` z `includes/upgrade.php`
- Czyszczenie w `uninstall.php` (drop table, delete options, delete transients)
- Transient cache (`set_transient/get_transient`) dla kosztownych zapytan

## REST API

- Namespace: `{plugin-slug}/v1`
- Rejestracja endpointow w `rest_api_init`
- `permission_callback` w kazdym rejestrowanym route
- Walidacja parametrow przez `validate_callback` i `sanitize_callback` w schema args
- Zwracaj `WP_REST_Response` lub `WP_Error`

## Panel administracyjny

- Menu/submenu przez `add_menu_page()` / `add_submenu_page()`
- Settings API: `register_setting()`, `add_settings_section()`, `add_settings_field()`
- Prefixuj opcje w `wp_options`: `{plugin_prefix}_{option_name}`
- Admin notices przez `admin_notices` hook

## Frontend / Assets

- Enqueue skrypty/style tylko tam, gdzie sa potrzebne (sprawdzaj `is_product()`, `is_shop()` itp.)
- `wp_localize_script()` lub `wp_add_inline_script()` dla przekazywania danych do JS
- jQuery jako dependencja jesli potrzebna (nie laduj osobno)
- Uzywaj `wp_enqueue_script()` z `in_footer: true` dla skryptow

## Internacjonalizacja

- Text domain zgodny z nazwa pluginu
- `__()`, `_e()`, `esc_html__()`, `esc_attr__()` dla wszystkich stringow w UI
- Plik POT w katalogu `languages/`

## Testowanie

- Po napisaniu kodu zweryfikuj skladnie PHP: `php -l {plik}`
- Sprawdz czy klasy/funkcje nie sa zduplikowane
- Przetestuj logike SQL na przykladowych danych

## Proces pracy

1. **Przeczytaj** istniejacy kod i zrozum architekture pluginu przed zmianami
2. **Zaplanuj** — jesli zadanie jest zlozone, przedstaw plan przed implementacja
3. **Implementuj** — pisz kod zgodny z powyzszymi zasadami
4. **Zweryfikuj** — sprawdz skladnie, potencjalne bledy, bezpieczenstwo
5. **Podsumuj** — krotko opisz co zostalo zrobione i jakie pliki zostaly zmienione
