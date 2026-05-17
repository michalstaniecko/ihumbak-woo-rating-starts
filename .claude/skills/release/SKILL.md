---
name: release
description: Wydaje nową wersję pluginu — bumpuje wersję (major/minor/patch), commituje zmiany, tworzy tag `vX.Y.Z` i pushuje commit + tag do remote.
disable-model-invocation: true
argument-hint: [major|minor|patch]
allowed-tools: Read Edit Bash Grep
---

# Release Plugin Version

Pełen flow wydania nowej wersji pluginu: bump → commit → tag → push.

## Argument

`$ARGUMENTS` — typ bumpa: `major`, `minor` lub `patch` (domyślnie `patch` jeśli nie podano).

## Wymagania wstępne (sprawdź ZANIM zaczniesz)

Wykonaj równolegle:

1. `git status --porcelain` — drzewo robocze MUSI być czyste. Jeśli są niezacommitowane zmiany — przerwij i poproś użytkownika o ich zacommitowanie lub stash.
2. `git rev-parse --abbrev-ref HEAD` — jesteś na `main`. Jeśli nie — przerwij i ostrzeż użytkownika.
3. `git fetch --tags` — pobierz aktualne tagi z remote, aby uniknąć kolizji.

Jeśli którykolwiek warunek nie jest spełniony — **NIE kontynuuj**, zgłoś problem użytkownikowi.

## Kroki

### 1. Bump wersji

Odczytaj aktualną wersję z `ihumbak-woo-rating-stars.php` (wzorzec `* Version: X.Y.Z`) i oblicz nową:

- `patch` (domyślny): `X.Y.Z` → `X.Y.(Z+1)`
- `minor`: `X.Y.Z` → `X.(Y+1).0`
- `major`: `X.Y.Z` → `(X+1).0.0`

Sprawdź, że tag `vX.Y.Z` (nowa wersja) **nie istnieje** lokalnie ani na remote (`git tag -l "vX.Y.Z"` oraz `git ls-remote --tags origin "refs/tags/vX.Y.Z"`). Jeśli istnieje — przerwij.

Zaktualizuj wersję we WSZYSTKICH wymaganych miejscach:

**a) Nagłówek pluginu** — `ihumbak-woo-rating-stars.php`:
```
* Version: X.Y.Z
```

**b) Stała PHP** — `ihumbak-woo-rating-stars.php`:
```php
define('IHUMBAK_WRS_VERSION', 'X.Y.Z');
```

**c) CHANGELOG.md** — wstaw nowy nagłówek bezpośrednio pod linią `## [Unreleased]`:
```markdown
## [X.Y.Z] - YYYY-MM-DD
```
Użyj dzisiejszej daty (ISO `YYYY-MM-DD`). Nie modyfikuj treści wpisów — opis zmian uzupełnia użytkownik (lub jest już obecny pod `[Unreleased]`, w takim razie przenieś go pod nowy nagłówek).

**d) `readme.txt`** — jeśli plik istnieje, zaktualizuj `Stable tag: X.Y.Z`.

### 2. Commit

Wykonaj `git status` oraz `git diff` aby zweryfikować, że zmodyfikowane są **wyłącznie** pliki z kroku 1.

Dodaj konkretne pliki po nazwie (NIE `git add -A` / `git add .`):

```bash
git add ihumbak-woo-rating-stars.php CHANGELOG.md
# opcjonalnie readme.txt jeśli został zmodyfikowany
```

Utwórz commit przez HEREDOC:

```bash
git commit -m "$(cat <<'EOF'
chore(release): vX.Y.Z

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

Jeśli pre-commit hook zawiedzie — napraw problem, ponownie dodaj pliki i utwórz **NOWY** commit (nigdy `--amend`).

### 3. Tag

Utwórz annotowany tag wskazujący na świeży commit:

```bash
git tag -a "vX.Y.Z" -m "Release vX.Y.Z"
```

### 4. Push

Wypchnij commit, a następnie tag (osobne komendy, w tej kolejności):

```bash
git push origin main
git push origin "vX.Y.Z"
```

Jeśli push commita zawiedzie (np. behind remote) — **NIE** pushuj taga. Zgłoś problem użytkownikowi, pozostaw tag lokalnie (użytkownik zdecyduje, czy pullować/usuwać).

### 5. Podsumowanie

Wypisz:
- Poprzednia wersja → nowa wersja
- Lista zaktualizowanych plików
- Hash commita (`git rev-parse --short HEAD`)
- Nazwa taga (`vX.Y.Z`)
- Potwierdzenie pushu commita i taga
- Przypomnienie: "Uzupełnij CHANGELOG.md, jeśli sekcja jest pusta" (tylko jeśli pod nowym nagłówkiem brak treści).

## Zasady bezpieczeństwa

- NIGDY nie używaj `--no-verify`, `--no-gpg-sign`, `--force`, `--force-with-lease` bez wyraźnej prośby użytkownika.
- NIGDY nie modyfikuj git config.
- NIGDY nie wykonuj `git reset --hard`, `git push --force` na `main`, ani nie kasuj tagów na remote bez zgody.
- Nie commituj plików z sekretami (`.env`, `credentials.json` itp.).
- Jeśli cokolwiek pójdzie nie tak po stronie remote (push odrzucony, tag już istnieje na remote) — **zatrzymaj się i zapytaj użytkownika**, nie próbuj naprawiać automatycznie.
