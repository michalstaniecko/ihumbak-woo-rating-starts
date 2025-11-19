# WooCommerce Quick Ratings & Reviews - Specyfikacja

## 1. Funkcjonalność

### System ocen (Rating System):
- Widżet gwiazdek pod nazwą produktu na stronie produktu
- Klik na gwiazdkę = natychmiastowa ocena bez tekstu
- Oceny przechowywane w customowej tabeli (nie w systemie komentarzy)
- Użytkownik anonimowy/zalogowany - identyfikacja po IP lub user ID

### System recenzji (Review System):
- Standardowy WooCommerce - recenzje tekstowe z oceną (opcjonalnie)
- Wyświetlanie poniżej ocen

### Średnia ocena:
```
Średnia = (suma ocen z kliknięć + suma ocen z recenzji) / (liczba ocen + liczba recenzji)
```

---

## 2. Baza danych

### Nowa tabela: `wp_woo_quick_ratings`
```sql
CREATE TABLE wp_woo_quick_ratings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    rating INT NOT NULL (1-5),
    user_id INT DEFAULT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (product_id, user_id, ip_address)
);
```

---

## 3. Komponenty pluginu

### A. Backend:
- REST Endpoint: `POST /wp-json/woo-quick-ratings/v1/rate` (dodaj ocenę)
- REST Endpoint: `GET /wp-json/woo-quick-ratings/v1/stats/{product_id}` (pobierz dane)
- Hook do WooCommerce: przeliczanie średniej oceny
- Admin panel: statystyki ocen i recenzji

### B. Frontend:
- JavaScript: interaktywne gwiazdki na stronie produktu
- CSS: stylizacja widżetu
- AJAX: wysyłanie oceny bez przeładowania strony

### C. Integracja z WooCommerce:
- Modyfikacja wyświetlania `woocommerce_product_get_average_rating()`
- Hook: `woocommerce_product_review_comment_form_args` - dostosowanie formularza

---

## 4. Specyfikacja funkcji

| Funkcja | Opis |
|---------|------|
| `woo_qr_get_rating_stats()` | Pobiera ilość ocen i średnią z tabeli |
| `woo_qr_add_rating()` | Dodaje ocenę do tabeli (AJAX) |
| `woo_qr_get_user_rating()` | Sprawdza, czy użytkownik już ocenił |
| `woo_qr_calculate_average()` | Liczy średnią z recenzji + ocen |
| `woo_qr_render_widget()` | Wyświetla widżet gwiazdek |
| `woo_qr_get_reviews_ratings()` | Pobiera średnią tylko z recenzji |

---

## 5. Workflow

```
1. Ładowanie strony produktu
   ↓
2. Pobranie ocen z tabeli woo_quick_ratings
3. Pobranie ocen z recenzji WooCommerce
4. Przeliczenie średniej
   ↓
5. Wyświetlenie:
   - Widżet gwiazdek (interaktywny)
   - Liczba ocen
   - Recenzje tekstowe poniżej
```

---

## 6. Anti-spam / Bezpieczeństwo

- Jedna ocena na użytkownika (lub na IP jeśli anonimowy)
- Możliwość zmiany oceny (update zamiast insert)
- Rate limiting: max 1 ocena na 10 minut z tego samego IP
- Walidacja: ocena musi być 1-5
- Nonce sprawdzenie w AJAX

---

## 7. Admin Panel

- Widok ocen dla każdego produktu
- Możliwość usunięcia oceny
- Statystyki: liczba ocen, średnia, rozkład (ile 5★, 4★ itd.)

---

## 8. Integracja z WooCommerce

### Hooki do wykorzystania:
- `woocommerce_single_product_summary` - wyświetlenie widżetu
- `woocommerce_product_get_rating_html()` - modyfikacja wyświetlania średniej
- `woocommerce_product_get_average_rating()` - przeliczanie średniej
- `wp_footer` - załadowanie skryptów

### REST API:
- Rejestracja endpointów: `register_rest_route()`
- Autoryzacja: nonce/JWT token

---

## 9. Ustawienia pluginu

### Opcje w adminie:
- Włącz/wyłącz system ocen
- Wymagaj logowania do oceniania (tak/nie)
- Pozycja widżetu (powyżej ceny / poniżej ceny / custom hook)
- Wyświetlaj liczę ocen obok gwiazdek (tak/nie)
- Tło widżetu, kolor gwiazdek (CSS)
- Tekst: "Oceń ten produkt", "Dziękuję za ocenę" itd.

