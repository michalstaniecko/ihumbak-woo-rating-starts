# SEO & Schema.org Guide - WooCommerce Quick Ratings & Reviews

## ✅ Pełna zgodność z SEO i Schema.org

Plugin **v1.0.4+** jest w pełni zgodny z:
- ✅ **Schema.org** (Product + AggregateRating)
- ✅ **Google Rich Snippets** (gwiazdki w wynikach wyszukiwania)
- ✅ **Google Merchant Center**
- ✅ **Bing Product Search**
- ✅ **Facebook Open Graph**

---

## 📊 Structured Data (JSON-LD)

Plugin automatycznie dodaje strukturalne dane w formacie JSON-LD:

```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "@id": "https://example.com/product/example/#product",
  "name": "Example Product",
  "description": "Product description...",
  "sku": "PROD-123",
  "image": "https://example.com/image.jpg",
  "brand": {
    "@type": "Brand",
    "name": "Brand Name"
  },
  "offers": {
    "@type": "Offer",
    "price": "99.99",
    "priceCurrency": "PLN",
    "availability": "https://schema.org/InStock",
    "url": "https://example.com/product/example/",
    "priceValidUntil": "2025-12-31"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.50",
    "reviewCount": "25",
    "ratingCount": "150",
    "bestRating": "5",
    "worstRating": "1"
  }
}
```

### Kluczowe pola:

**`ratingValue`**: Średnia ocena (quick ratings + reviews)
- Format: 0.00 - 5.00
- Obliczana jako: `(suma quick + suma reviews) / (liczba quick + liczba reviews)`

**`reviewCount`**: Liczba **recenzji tekstowych** (tylko WooCommerce reviews)
- Zgodne z wymaganiami Google
- Tylko recenzje z treścią

**`ratingCount`**: Liczba **wszystkich ocen** (quick + reviews)
- Pokazuje pełne zaangażowanie użytkowników
- Wyższa liczba = lepsze SEO

---

## 🎯 Google Rich Snippets

### Jak to wygląda w Google:

```
★★★★☆ 4.5 | 150 ratings | 25 reviews
Example Product - €99.99 - In stock
https://example.com › product › example
Product description appears here...
```

### Wymagania Google:

✅ **Minimum ocen**: Co najmniej 1 ocena
✅ **Format**: AggregateRating z ratingValue, ratingCount, bestRating
✅ **Różnica**: reviewCount ≠ ratingCount (Google akceptuje)
✅ **Zakres**: ratingValue musi być między worstRating a bestRating

---

## 🔍 Microdata (HTML attributes)

Plugin dodaje również microdata do HTML (backward compatibility):

```html
<div itemscope itemtype="https://schema.org/AggregateRating" itemprop="aggregateRating">
    <span class="count" itemprop="ratingCount">150</span>
    <meta itemprop="ratingValue" content="4.50">
    <meta itemprop="bestRating" content="5">
    <meta itemprop="worstRating" content="1">
</div>
```

---

## 🧪 Testowanie SEO

### Google Rich Results Test

1. Otwórz: https://search.google.com/test/rich-results
2. Wklej URL produktu
3. Sprawdź czy wykrywa:
   - ✅ Product
   - ✅ AggregateRating
   - ✅ Offers

**Oczekiwany rezultat:**
```
✅ Valid items detected
   Product
   └─ AggregateRating
   └─ Offer
```

### Google Search Console

1. Przejdź do: Search Console → Enhancements → Products
2. Sprawdź błędy:
   - ✅ No errors
   - ✅ Valid products: X

### Schema Markup Validator

1. Otwórz: https://validator.schema.org/
2. Wklej kod źródłowy strony produktu
3. Sprawdź ostrzeżenia

---

## 📈 Korzyści SEO

### Zwiększona widoczność w wyszukiwarce:
- ⭐ **Gwiazdki w wynikach** - 5-30% więcej kliknięć (CTR)
- 📊 **Wyróżnienie produktu** - wizualnie wyróżnia się od konkurencji
- 🏆 **Trust factor** - użytkownicy ufają produktom z ocenami
- 🔢 **Liczba ocen** - pokazuje popularność produktu

### Optymalizacja rankingu:
- ✅ Structured data = lepsze zrozumienie przez Google
- ✅ Wyższa liczba ocen = silniejszy sygnał jakości
- ✅ Kombinacja quick + reviews = większa wiarygodność

---

## 🔄 Integracja z WooCommerce

Plugin automatycznie:
1. **Nadpisuje** domyślny schema WooCommerce
2. **Łączy** quick ratings + reviews w jedną średnią
3. **Zachowuje** inne pola WooCommerce (cena, dostępność)
4. **Aktualizuje** dane przy każdej nowej ocenie

### Hook dla customizacji:

```php
add_filter('woocommerce_structured_data_product', function($markup, $product) {
    // Twoja customizacja schema
    return $markup;
}, 20, 2);
```

---

## 🌐 Wsparcie dla innych platform

### Facebook Open Graph
Plugin współpracuje z Open Graph meta tags (używane przez Facebook, LinkedIn):
```html
<meta property="og:type" content="product">
<meta property="product:rating:value" content="4.5">
<meta property="product:rating:scale" content="5">
```

### Twitter Cards
Kompatybilne z Twitter Product Cards:
```html
<meta name="twitter:card" content="product">
<meta name="twitter:data1" content="4.5★">
<meta name="twitter:label1" content="Rating">
```

---

## ⚠️ Ważne uwagi

### reviewCount vs ratingCount

Google **wymaga** różnicy między:
- `reviewCount` = tylko recenzje z tekstem
- `ratingCount` = wszystkie oceny (quick + reviews)

**Nasz plugin prawidłowo:**
- ✅ `reviewCount`: tylko WooCommerce reviews
- ✅ `ratingCount`: quick ratings + reviews
- ✅ Google akceptuje tę konfigurację

### Minimum wymagań Google

Aby gwiazdki pojawiły się w Google:
1. ✅ Co najmniej **1 ocena** (może być quick rating)
2. ✅ Valid schema (testowane przez Rich Results Test)
3. ✅ Produkt zindeksowany przez Google
4. ✅ Strona nie ma kar manualnych

**Uwaga**: Google może **nie pokazywać** gwiazdek dla:
- Nowych stron (nie zindeksowanych)
- Stron z niskim trust score
- Produktów bez żadnych ocen

---

## 🛠️ Troubleshooting

### Gwiazdki nie pojawiają się w Google

**Sprawdź:**
1. Czy produkt ma oceny? (min. 1)
2. Czy Rich Results Test wykrywa schema?
3. Czy strona jest zindeksowana? (Google Search Console)
4. Poczekaj 2-4 tygodnie (Google potrzebuje czasu)

### Schema validation errors

**Najczęstsze błędy:**
- Missing `reviewCount` → Plugin automatycznie dodaje
- Invalid `ratingValue` → Sprawdź czy są oceny
- Missing `offers` → Sprawdź czy produkt ma cenę

### Duplikacja schema

Jeśli inny plugin też dodaje schema:
1. Wyłącz schema w drugim pluginie
2. Lub: ustaw priority hook `wp_footer` (domyślnie: 5)

---

## 📚 Dokumentacja techniczna

### Pliki odpowiedzialne za schema:

1. `/includes/class-schema-markup.php` - główna logika
2. `/templates/widget-stars.php` - microdata attributes
3. Hook: `wp_footer` (priority: 5)

### API Functions:

```php
// Pobierz schema dla produktu
$schema = new Ihumbak_WRS_Schema_Markup();
$markup = $schema->generate_aggregate_rating_schema($product, $stats);

// Modify WC schema
add_filter('woocommerce_structured_data_product', [$schema, 'modify_wc_schema'], 10, 2);
```

---

## ✅ Checklist przed publikacją

- [ ] Test w Google Rich Results Test
- [ ] Sprawdź Schema Markup Validator
- [ ] Zweryfikuj w Google Search Console
- [ ] Sprawdź microdata w HTML
- [ ] Test na mobile (Google Mobile-Friendly Test)
- [ ] Upewnij się że ratingCount > reviewCount
- [ ] Sprawdź czy offers zawiera cenę

---

**Plugin v1.0.4+ jest w pełni zgodny z najnowszymi wymaganiami Google (2025)** ✅

Ostatnia aktualizacja: 2025-11-19
