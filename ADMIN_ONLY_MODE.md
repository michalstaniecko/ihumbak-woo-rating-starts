# Admin Only Mode (Debug) - Dokumentacja

## 📋 Opis funkcji

**Admin Only Mode** to specjalny tryb debugowania, który pozwala administratorom testować widget ocen przed udostępnieniem go wszystkim użytkownikom.

## 🎯 Cel

Umożliwia:
- 🧪 **Testowanie** - sprawdź jak działa widget przed włączeniem na produkcji
- 🔍 **Debugowanie** - rozwiązuj problemy bez wpływu na użytkowników
- 🎨 **Projektowanie** - dopasuj wygląd i pozycję widgetu
- ✅ **Weryfikacja** - upewnij się że wszystko działa poprawnie

## ⚙️ Jak włączyć?

### Krok 1: Przejdź do ustawień
1. WordPress Admin → **Quick Ratings** → **Settings**
2. Znajdź opcję: **"Admin Only Mode (Debug)"**

### Krok 2: Włącz tryb
- ✅ Zaznacz checkbox
- 💾 Kliknij **"Save Changes"**

### Krok 3: Testuj
- Widget będzie widoczny **tylko dla Ciebie** (admin)
- Zwykli użytkownicy **nie zobaczą** widgetu

## 🔐 Kto widzi widget w tym trybie?

### ✅ Widoczny dla:
- Administratorów (role: `administrator`)
- Użytkowników z uprawnieniem `manage_options`

### ❌ Niewidoczny dla:
- Zwykłych użytkowników
- Klientów (role: `customer`)
- Edytorów (role: `editor`)
- Gości (niezalogowanych)

## 👁️ Jak wygląda dla admina?

Gdy tryb jest włączony, admin widzi żółte ostrzeżenie nad widgetem:

```
┌───────────────────────────────────────────────────┐
│ 🔒 Admin Only Mode:                               │
│ Only you (admin) can see this widget.            │
│ Regular users cannot see it.                      │
└───────────────────────────────────────────────────┘
```

## 🔧 Implementacja techniczna

### Sprawdzanie w kodzie:

```php
// Frontend Render
if (get_option('ihumbak_wrs_admin_only') === 'yes' && !current_user_can('manage_options')) {
    return; // Nie pokazuj widgetu
}
```

### Pliki zmodyfikowane:
1. `/public/class-frontend-render.php` - sprawdzanie przed renderowaniem
2. `/public/class-assets-manager.php` - sprawdzanie przed ładowaniem CSS/JS
3. `/templates/widget-stars.php` - wyświetlanie ostrzeżenia dla admina
4. `/admin/class-admin-settings.php` - opcja w ustawieniach

## 📊 Przypadki użycia

### 1. Nowy sklep (przed startem)
```
Scenariusz: Konfigurujesz nowy sklep
→ Włącz Admin Only Mode
→ Przetestuj widget na produktach
→ Sprawdź pozycję i wygląd
→ Wyłącz gdy wszystko działa
```

### 2. Aktualizacja wyglądu
```
Scenariusz: Chcesz zmienić kolor gwiazdek
→ Włącz Admin Only Mode
→ Zmień ustawienia
→ Sprawdź na froncie (tylko Ty widzisz)
→ Jak OK - wyłącz tryb
```

### 3. Debugowanie problemu
```
Scenariusz: Klient zgłasza problem z widgetem
→ Włącz Admin Only Mode
→ Odtwórz problem jako admin
→ Napraw
→ Przetestuj
→ Wyłącz tryb
```

### 4. Staging → Production
```
Scenariusz: Przenosisz z staging na produkcję
→ Na staging: tryb wyłączony
→ Na production: włącz Admin Only Mode
→ Zweryfikuj działanie
→ Wyłącz tryb dla wszystkich
```

## ⚠️ Ważne uwagi

### Ostrzeżenia:
- ⚠️ **Nie zapomnij wyłączyć** po testowaniu!
- ⚠️ Widget **naprawdę nie będzie** widoczny dla użytkowników
- ⚠️ Oceny będą działać, ale tylko admin może oceniać
- ⚠️ Schema.org markup będzie generowany normalnie

### Zachowanie innych funkcji:
- ✅ Panel administracyjny: działa normalnie
- ✅ REST API: działa normalnie (ale tylko admin może użyć)
- ✅ Statystyki: normalnie zbierane
- ✅ SEO schema: generowany dla wszystkich

## 🔄 Jak wyłączyć?

1. WordPress Admin → **Quick Ratings** → **Settings**
2. **Odznacz** checkbox "Admin Only Mode (Debug)"
3. Kliknij **"Save Changes"**
4. Widget będzie widoczny dla wszystkich

## 📝 Checklist testowania

Przed wyłączeniem Admin Only Mode, sprawdź:

- [ ] Widget wyświetla się w odpowiednim miejscu
- [ ] Gwiazdki działają (klik = ocena)
- [ ] Komunikat "Thank you" pokazuje się natychmiast
- [ ] Liczba ocen aktualizuje się po ocenie
- [ ] Kolor gwiazdek jest poprawny
- [ ] Responsywność (mobile/tablet) działa
- [ ] Nie ma błędów w konsoli JavaScript
- [ ] REST API odpowiada poprawnie
- [ ] Schema.org markup jest poprawny (Google Rich Results Test)

## 🛠️ Troubleshooting

### Problem: Widget nie pokazuje się nawet dla admina

**Rozwiązanie:**
1. Sprawdź czy "Enable Quick Ratings" jest włączony
2. Sprawdź czy jesteś zalogowany jako admin
3. Wyczyść cache przeglądarki (Ctrl+Shift+R)
4. Sprawdź cache WordPress (jeśli używasz)

### Problem: Inne role (np. Shop Manager) nie widzą

**To normalne!** Tylko administratorzy z uprawnieniem `manage_options` widzą widget.

**Rozwiązanie:** Dodaj custom kod w `functions.php`:
```php
add_filter('ihumbak_wrs_can_see_admin_widget', function($can_see) {
    return current_user_can('manage_woocommerce'); // Też dla Shop Managers
});
```

### Problem: Zapomniałem wyłączyć tryb na produkcji

**Nie panikuj!** 
1. Szybkie wyłączenie przez bazę danych:
```sql
UPDATE wp_options 
SET option_value = 'no' 
WHERE option_name = 'ihumbak_wrs_admin_only';
```

2. Lub usuń opcję:
```sql
DELETE FROM wp_options 
WHERE option_name = 'ihumbak_wrs_admin_only';
```

## 💡 Dobre praktyki

### ✅ DO:
- Testuj przed wyłączeniem
- Sprawdź na różnych przeglądarkach
- Przetestuj mobile
- Weryfikuj w Google Rich Results Test

### ❌ DON'T:
- Nie zostawiaj włączonego na produkcji
- Nie używaj jako "feature" (to debug mode!)
- Nie polegaj na tym dla kontroli dostępu (użyj "Require Login")

## 🎓 FAQ

**Q: Czy mogę dać dostęp innym rolom?**
A: Tak, użyj custom filter (patrz: Troubleshooting)

**Q: Czy to wpływa na SEO?**
A: Nie, schema.org jest generowany normalnie dla wszystkich.

**Q: Czy oceny są zapisywane w tym trybie?**
A: Tak, oceny admina są normalnie zapisywane.

**Q: Jak długo mogę mieć włączony ten tryb?**
A: Dowolnie długo, ale to tryb **testowy**, nie funkcja produkcyjna.

---

**Wersja:** 1.0.5+
**Ostatnia aktualizacja:** 2025-11-19
