# Bugfix v1.0.2 - Invalid Nonce Error Fix

## Problem
```
Endpoint wp-json/woo-quick-ratings/v1/rate zwraca błąd: invalid_nonce
```

## Przyczyna
Plugin próbował weryfikować nonce za pomocą `wp_verify_nonce()` z custom action, ale WordPress REST API wymaga innego podejścia. REST API ma własny system autoryzacji oparty o nagłówek `X-WP-Nonce`.

## Rozwiązanie
Zmieniono sposób obsługi nonce zgodnie z WordPress REST API best practices.

### Przed (błąd):

**PHP - REST API Handler:**
```php
$nonce = $request->get_param('nonce');

// Verify nonce
if (!wp_verify_nonce($nonce, 'ihumbak_wrs_rate_' . $product_id)) {
    return new WP_Error('invalid_nonce', ...);
}
```

**JavaScript:**
```javascript
$.ajax({
    url: ihumbakWRS.ajax_url + 'rate',
    method: 'POST',
    data: {
        product_id: this.productId,
        rating: rating,
        nonce: ihumbakWRS.nonce  // W body requesta
    }
});
```

**Assets Manager:**
```php
'nonce' => wp_create_nonce('ihumbak_wrs_rate_' . $product_id)
```

### Po (naprawione):

**PHP - REST API Handler:**
```php
// Usunięto manualną weryfikację nonce
// WordPress REST API automatycznie weryfikuje X-WP-Nonce header
public function add_rating($request) {
    $product_id = $request->get_param('product_id');
    $rating = $request->get_param('rating');
    // Brak weryfikacji nonce - WordPress robi to automatycznie
}
```

**JavaScript:**
```javascript
$.ajax({
    url: ihumbakWRS.ajax_url + 'rate',
    method: 'POST',
    beforeSend: function(xhr) {
        xhr.setRequestHeader('X-WP-Nonce', ihumbakWRS.nonce);  // W headerze
    },
    data: {
        product_id: this.productId,
        rating: rating
        // Brak nonce w data
    }
});
```

**Assets Manager:**
```php
'nonce' => wp_create_nonce('wp_rest')  // Standardowy REST API nonce
```

## Zmienione pliki
1. `/includes/class-rest-api-handler.php`
   - Usunięto parametr `nonce` z args
   - Usunięto `wp_verify_nonce()` z `add_rating()`
   
2. `/public/class-assets-manager.php`
   - Zmieniono `wp_create_nonce('ihumbak_wrs_rate_' . $product_id)` na `wp_create_nonce('wp_rest')`
   
3. `/assets/js/rating-widget.js`
   - Dodano `beforeSend` z `X-WP-Nonce` headerem
   - Usunięto `nonce` z `data`
   
4. `/ihumbak-woo-rating-stars.php`
   - Wersja: 1.0.2
   
5. `/CHANGELOG.md`
   - Dodano informację o naprawie

## Jak działa WordPress REST API nonce?

WordPress automatycznie weryfikuje nonce w REST API gdy:
1. Nonce jest wysyłany w nagłówku `X-WP-Nonce`
2. Nonce jest wygenerowany z action `wp_rest`
3. Użytkownik jest zalogowany (dla gości nie jest wymagany)

**Ważne:** Dla anonimowych użytkowników (niezalogowanych) WordPress REST API **nie wymaga** nonce i automatycznie akceptuje request.

## Testy
Po naprawie:
- ✅ Zalogowany użytkownik: nonce weryfikowany automatycznie przez WordPress
- ✅ Anonimowy użytkownik: może oceniać bez nonce (zgodnie z ustawieniem pluginu)
- ✅ Rate limiting działa niezależnie od nonce
- ✅ Brak błędu `invalid_nonce`

## Dodatkowe zabezpieczenia
Plugin nadal ma:
- ✅ Rate limiting (1 ocena / 10 minut / IP)
- ✅ Walidację danych (product_id, rating 1-5)
- ✅ Sprawdzanie czy produkt istnieje
- ✅ Opcję wymagania logowania
- ✅ Sanityzację wszystkich inputów

---
Data naprawy: 2025-11-18
