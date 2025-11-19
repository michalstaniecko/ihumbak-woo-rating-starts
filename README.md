# WooCommerce Quick Ratings & Reviews

System szybkich ocen gwiazdkowych dla WooCommerce z integracją standardowych recenzji.

## Opis

Plugin umożliwia użytkownikom błyskawiczne ocenianie produktów poprzez kliknięcie gwiazdek bez konieczności pisania pełnej recenzji. Oceny są przechowywane w dedykowanej tabeli i łączone ze standardowymi recenzjami WooCommerce w celu obliczenia końcowej średniej.

## Funkcje

- ⭐ **Szybkie ocenianie** - Kliknij gwiazdkę, aby natychmiast ocenić produkt
- 🔄 **Integracja z WooCommerce** - Łączenie ocen z recenzjami w jedną średnią
- 👤 **Wsparcie dla gości i użytkowników** - Ocenianie bez logowania (opcjonalnie)
- 🛡️ **Bezpieczeństwo** - Rate limiting, nonce verification, walidacja
- 📊 **Panel administracyjny** - Pełne statystyki i zarządzanie ocenami
- 🎨 **Personalizacja** - Konfigurowalny wygląd i pozycja widżetu
- 📱 **Responsywność** - Działa na wszystkich urządzeniach

## Wymagania

- WordPress 5.9 lub nowszy
- WooCommerce 6.0 lub nowszy
- PHP 7.4 lub nowszy

## Instalacja

1. Pobierz plugin i rozpakuj do katalogu `/wp-content/plugins/`
2. Aktywuj plugin w sekcji 'Wtyczki' w WordPress
3. Przejdź do **Quick Ratings > Settings** aby skonfigurować plugin

## Konfiguracja

### Podstawowe ustawienia

- **Enable Quick Ratings** - Włącz/wyłącz system ocen
- **Require Login** - Wymagaj logowania do oceniania
- **Widget Position** - Pozycja widżetu (po tytule, przed/po cenie)
- **Show Rating Count** - Wyświetlaj liczbę ocen
- **Star Color** - Kolor gwiazdek
- **Custom Text** - Personalizacja tekstów

### Bezpieczeństwo

Plugin automatycznie:
- Ogranicza oceny do 1 na użytkownika/IP
- Stosuje rate limiting (1 ocena na 10 minut)
- Waliduje wszystkie dane wejściowe
- Weryfikuje nonce w żądaniach AJAX

## REST API

### POST `/wp-json/woo-quick-ratings/v1/rate`

Dodaje lub aktualizuje ocenę produktu.

**Parametry:**
- `product_id` (integer, wymagane) - ID produktu
- `rating` (integer, wymagane) - Ocena 1-5
- `nonce` (string, wymagane) - Nonce token

**Odpowiedź:**
```json
{
  "success": true,
  "message": "Dziękujemy za ocenę!",
  "rating_id": 123,
  "stats": {
    "average": 4.5,
    "total_count": 150,
    "quick_count": 100,
    "review_count": 50,
    "distribution": {
      "5": 80,
      "4": 40,
      "3": 20,
      "2": 5,
      "1": 5
    }
  }
}
```

### GET `/wp-json/woo-quick-ratings/v1/stats/{product_id}`

Pobiera statystyki ocen dla produktu.

**Odpowiedź:**
```json
{
  "average": 4.5,
  "total_count": 150,
  "quick_count": 100,
  "review_count": 50,
  "user_rating": 5,
  "distribution": {
    "5": 80,
    "4": 40,
    "3": 20,
    "2": 5,
    "1": 5
  }
}
```

## Panel administratora

### Wszystkie oceny

- Lista produktów z ocenami
- Statystyki: liczba ocen, średnia, rozkład
- Możliwość przeglądania szczegółów każdego produktu

### Szczegóły produktu

- Kombinowana średnia (oceny + recenzje)
- Rozkład ocen (5★, 4★, 3★, 2★, 1★)
- Lista wszystkich ocen z datami i użytkownikami
- Możliwość usuwania ocen

### Lista produktów

- Dodatkowa kolumna "Quick Ratings" z liczbą ocen i średnią

## Struktura bazy danych

Plugin tworzy tabelę `wp_woo_quick_ratings`:

```sql
CREATE TABLE wp_woo_quick_ratings (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    product_id bigint(20) unsigned NOT NULL,
    rating tinyint(1) unsigned NOT NULL,
    user_id bigint(20) unsigned DEFAULT NULL,
    ip_address varchar(45) DEFAULT NULL,
    user_agent text DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY product_id (product_id),
    KEY user_id (user_id),
    KEY ip_address (ip_address)
);
```

## Hooki i filtry

### Actions

- `ihumbak_wrs_after_rating_added` - Po dodaniu oceny
- `ihumbak_wrs_after_rating_updated` - Po aktualizacji oceny
- `ihumbak_wrs_after_rating_deleted` - Po usunięciu oceny

### Filters

- `ihumbak_wrs_widget_template` - Modyfikacja szablonu widżetu
- `ihumbak_wrs_rate_limit_minutes` - Zmiana czasu rate limiting (domyślnie 10)
- `ihumbak_wrs_combined_average` - Modyfikacja kombinowanej średniej

## FAQ

### Czy mogę wyłączyć standardowe recenzje WooCommerce?

Tak, możesz wyłączyć recenzje w ustawieniach WooCommerce. Plugin będzie działał samodzielnie z quick ratings.

### Czy oceny są liczone do średniej WooCommerce?

Tak! Plugin integruje się z systemem WooCommerce i modyfikuje średnią ocenę, łącząc quick ratings z recenzjami.

### Czy mogę zmienić wygląd widżetu?

Tak, możesz:
- Zmienić kolor gwiazdek w ustawieniach
- Dodać własne style CSS
- Zmienić pozycję widżetu

### Co się stanie z ocenami po odinstalowaniu?

Przy deaktywacji dane są zachowane. Przy pełnym odinstalowaniu (usunięciu plików) tabela zostanie usunięta.

## Changelog

Zobacz [CHANGELOG.md](CHANGELOG.md) dla pełnej historii zmian.

## Wsparcie

W przypadku problemów:
1. Sprawdź, czy WooCommerce jest zainstalowany i aktywny
2. Upewnij się, że masz najnowszą wersję WordPress i WooCommerce
3. Sprawdź logi błędów w WordPress

## Licencja

GPL v2 lub nowsza - https://www.gnu.org/licenses/gpl-2.0.html

## Autor

iHumbak - https://ihumbak.com

## Kredyty

Stworzone z ❤️ dla społeczności WooCommerce
