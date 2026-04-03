---
name: commit
description: Przegląda zmiany w plikach, dodaje do stage, generuje wiadomość commit i commituje zmiany w repozytorium git.
disable-model-invocation: true
allowed-tools: Bash Read Glob Grep
---

# Git Commit

Przejrzyj zmiany w repozytorium i utwórz commit.

## Kroki

1. **Zbierz informacje** — uruchom rownolegle:
   - `git status` — zmodyfikowane, dodane i usuniete pliki (nigdy nie uzywaj flagi `-uall`)
   - `git diff` oraz `git diff --cached` — przejrzyj zmiany staged i unstaged
   - `git log --oneline -5` — sprawdz styl ostatnich commitow

2. **Dodaj pliki do stage:**
   - Dodawaj konkretne pliki po nazwie (`git add <plik1> <plik2>`)
   - NIGDY nie uzywaj `git add -A` ani `git add .`
   - Nie dodawaj plikow z sekretami (.env, credentials.json, itp.) — ostrzez uzytkownika jesli takie istnieja
   - Jesli nie ma zadnych zmian do zacommitowania — poinformuj uzytkownika i zakoncz

3. **Napisz wiadomosc commita:**
   - Krotka (1-2 zdan), opisujaca CO i DLACZEGO zostalo zmienione
   - W jezyku angielskim
   - Odpowiedni prefiks: `add` (nowa funkcja), `update` (rozszerzenie istniejącej), `fix` (naprawa bledu), `refactor`, `remove`, `docs`, itp.
   - Wiadomosc powinna skupiac sie na "dlaczego" a nie "co"

4. **Utworz commit** przez HEREDOC:
   ```
   git commit -m "$(cat <<'EOF'
   <wiadomosc>

   Co-Authored-By: Claude <noreply@anthropic.com>
   EOF
   )"
   ```

5. **Zweryfikuj** — uruchom `git status` po commicie aby potwierdzic sukces.

6. **Jesli pre-commit hook zawiedzie** — napraw problem i utworz NOWY commit (nigdy nie uzywaj `--amend`, bo commit nie zostal utworzony).

## Zasady bezpieczenstwa

- NIGDY nie pushuj do remote bez wyraznej prosby uzytkownika
- NIGDY nie uzywaj `--no-verify` ani `--no-gpg-sign`
- NIGDY nie modyfikuj git config
- NIGDY nie uzywaj `git add -i` ani `git rebase -i` (wymagaja interakcji)
- Twórz NOWE commity zamiast amend, chyba ze uzytkownik wyraznie o to prosi
