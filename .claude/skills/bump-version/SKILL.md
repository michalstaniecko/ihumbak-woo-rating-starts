---
name: bump-version
description: Bump WordPress plugin version (major, minor, or patch) across all required locations — plugin header, PHP constant, and changelog.
disable-model-invocation: true
argument-hint: [major|minor|patch]
allowed-tools: Read Edit Bash Grep
---

# Bump Plugin Version

Podbij wersje pluginu WordPress we wszystkich wymaganych miejscach.

## Argument

`$ARGUMENTS` — typ bumpa: `major`, `minor` lub `patch` (domyslnie `patch` jesli nie podano).

## Kroki

1. **Odczytaj aktualna wersje** z glownego pliku pluginu:
   - Szukaj wzorca `* Version: X.Y.Z` w naglowku pliku `ihumbak-woo-rating-stars.php`
   - Parsuj wersje na komponenty: major (X), minor (Y), patch (Z)

2. **Oblicz nowa wersje** na podstawie argumentu:
   - `patch` (domyslny): `X.Y.Z` → `X.Y.(Z+1)`
   - `minor`: `X.Y.Z` → `X.(Y+1).0`
   - `major`: `X.Y.Z` → `(X+1).0.0`

3. **Zaktualizuj wersje** we WSZYSTKICH wymaganych miejscach:

   **a) Naglowek pluginu** — `ihumbak-woo-rating-stars.php`:
   ```
   * Version: X.Y.Z
   ```

   **b) Stala PHP** — `ihumbak-woo-rating-stars.php`:
   ```php
   define('IHUMBAK_WRS_VERSION', 'X.Y.Z');
   ```

   **c) CHANGELOG.md** — dodaj nowy wpis na gorze listy zmian:
   ```markdown
   ## [X.Y.Z] - YYYY-MM-DD
   ```
   Uzyj aktualnej daty. Nie dodawaj opisu zmian — to zrobi uzytkownik.

   **d) readme.txt** (jesli istnieje) — zaktualizuj:
   ```
   Stable tag: X.Y.Z
   ```

4. **Wyswietl podsumowanie**:
   - Poprzednia wersja → nowa wersja
   - Lista zaktualizowanych plikow
   - Przypomnienie: "Uzupelnij CHANGELOG.md o opis zmian"
