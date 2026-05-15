---
name: work-on-issue
description: Start work using a 3-agent pipeline — Opus (high) plans, Sonnet (high) implements, Opus (high) reviews, with a feedback loop. Works in three modes — (a) bez argumentu → wybór issue z listy otwartych, (b) jeden lub wiele numerów issue (np. "12" albo "12 13 14"), (c) wolny opis zadania bez issue. Use when the user asks to "rozpocznij pracę nad issue N" / "work on issue N" / "rozpocznij pracę" / "popraw X".
argument-hint: [issue-number(s) | free-text description | empty]
disable-model-invocation: true
allowed-tools: Bash(gh issue view *) Bash(gh issue list *) Bash(gh pr *) Bash(git status *) Bash(git diff *) Bash(git log *) Bash(git checkout *) Bash(git switch *) Bash(git branch *) Read Grep Glob
---

# Work on Issue / Task — 3-agent pipeline

Orchestrate work for the `ihumbak-woo-rating-stars` project (WordPress/WooCommerce plugin — PHP 7.4+, jQuery, CSS; quick star-rating widget z własną tabelą DB i REST API).

You are the **orchestrator**. You do not write the implementation yourself — you delegate to subagents and pass artifacts between them. Keep your own context lean: tool output from sub-phases is heavy, so use `Agent` calls (which keep raw output out of your context) rather than Reading every file the implementer touches.

## Input parsing — three modes

Raw arguments: `$ARGUMENTS`

Inspect the argument string and pick exactly one mode:

1. **Empty mode** — `$ARGUMENTS` is empty / whitespace only.
   → Run `gh issue list --state open --limit 30 --json number,title,labels,milestone` (preloaded below), present a short numbered list to the user, and ask which issue (or issues) to work on. Do **not** start the pipeline before the user answers.

2. **Issue-number mode** — every whitespace/comma-separated token is purely numeric (e.g. `12`, `12 13`, `12,13,14`, `#12 #13`). Strip leading `#`.
   → Fetch each issue with `gh issue view <N>`. If any fetch fails, stop and report — do not fabricate. With multiple issues: **one branch, one PR** referencing all numbers (e.g. `feature/issues-12-13-<slug>`), unless the issues are clearly incompatible (different areas / conflicting acceptance criteria) — in that case ask the user whether to split into separate runs.

3. **Free-text mode** — argument is non-empty and contains non-numeric content (e.g. `popraw walidację oceny w REST API`).
   → Treat the text verbatim as the task spec. No `gh issue view`. Branch name: `feature/<slug-from-description>` (lowercase ascii, kebab-case, max ~40 chars). Surface this in your first status line so the user can correct the slug before the planner starts.

If the mode is ambiguous (e.g. `12 popraw bug`), ask the user one short clarifying question instead of guessing.

## Preloaded context

Raw arguments string (orchestrator parses this itself — see „Input parsing"):

```
$ARGUMENTS
```

Always-useful working-tree state:

```!
git status --short
```

```!
git rev-parse --abbrev-ref HEAD
```

Open issues (cheap, helpful regardless of mode — w empty mode to twoja lista do zaprezentowania użytkownikowi; w pozostałych trybach to kontekst pokrewnych ticketów):

```!
gh issue list --state open --limit 30 --json number,title,labels,milestone
```

## Fetching issue bodies

If you detected **issue-number mode**, fetch each issue body yourself with one `gh issue view <N> --json number,title,body,labels,state,assignees,milestone` call per number (run them in parallel — they are independent). If any call fails, stop and report — do not fabricate.

In **empty mode** and **free-text mode** skip this step entirely.

## Stack detection

Decide which area(s) the task touches before picking subagents:

- **PHP / WordPress backend** → `includes/**`, `admin/**`, `database/**`, `ihumbak-woo-rating-stars.php`, `uninstall.php` → use `wordpress-master` (preferred — WP/WC specific) or `voltagent-lang:php-pro` for pure PHP refactor without WP hooks.
- **REST API endpoints** → `includes/class-rest-api-handler.php` (namespace `woo-quick-ratings/v1`) → use `wordpress-master`.
- **Frontend widget** → `public/**`, `assets/js/**`, `assets/css/**`, `templates/**` (jQuery + plain CSS, no build step) → use `wordpress-master` (preferred — knows WP enqueue) or `voltagent-lang:javascript-pro` for complex JS-only logic.
- **Database / migrations** → `database/class-database-migration.php`, schema of `{prefix}_woo_quick_ratings` → use `wordpress-master`; pull in `voltagent-lang:sql-pro` only for complex query optimization.
- **Release / CI** → `.github/workflows/**`, `plugin-update-checker` integration, version bump w `ihumbak-woo-rating-stars.php` + readme → use `devops-engineer`.
- **Multi-area** → pick the dominant area for the implementer; mention secondary concerns in the brief.

If the task is ambiguous about scope (e.g. mówi „dodaj filtr" ale nie wskazuje czy admin czy frontend), **ask the user one clarifying question** before phase 1 instead of guessing.

## Pipeline

Run phases sequentially. Each phase is one `Agent` tool call. Do **not** Read the agent's transcript file mid-flight; wait for the completion notification.

### Phase 1 — PLAN (Opus, high effort)

Spawn a planner. Use `subagent_type: "Plan"` — it inherits Opus and is read-only, perfect for design without touching code.

Brief the planner with:
- **Task source** — depending on mode:
  - Issue-number mode (single): full issue body (title + description + labels + acceptance criteria if present).
  - Issue-number mode (multi): all issue bodies, plus an explicit note that they are being delivered together. Ask the planner to flag if it considers them too unrelated to bundle.
  - Free-text mode: the user's description verbatim, plus a note that there is no GitHub issue — the planner should propose acceptance criteria itself and surface them for user confirmation in the plan.
- Current branch and branching instruction. Default: branch off `main`, name `feature/issue-<N>-<slug>` for single issue, `feature/issues-<N1>-<N2>-<slug>` for multi, `feature/<slug>` for free-text. If already on a relevant feature branch, stay on it.
- Project conventions from `CLAUDE.md`:
  - Class prefix `Ihumbak_WRS_`; autoloader expects files named `class-<kebab-name>.php` w `includes/` | `admin/` | `public/` | `database/`.
  - Text domain `ihumbak-woo-rating-stars`; docs/comments po polsku, identyfikatory po angielsku.
  - Options prefix `ihumbak_wrs_`; transient cache keys `ihumbak_wrs_{type}_{product_id}` — **must be cleared** whenever rating logic mutates state.
  - REST namespace `woo-quick-ratings/v1` — nonce + login/admin gating via plugin options.
  - WooCommerce filters used: `woocommerce_product_get_average_rating`, `woocommerce_product_get_rating_count`, `woocommerce_product_get_rating_html` — uważać na rekurencję (patrz commit `e10cafc`).
  - Admin code loaded only in `is_admin()`; frontend only public-side (see `init_components()` w głównym pliku).
  - HPOS + Cart/Checkout Blocks compatibility declared — nie regresować.
  - No build step (plain CSS/JS), no Composer, no npm, no test framework configured — kontrola jakości to ręczny code review + sanity-check w przeglądarce.
  - `uninstall.php` musi pozostać spójny z migracjami (drop table + delete options).
- Relevant files to skim: `CLAUDE.md`, `ihumbak-woo-rating-stars.php` (bootstrap), `readme.txt`, `includes/class-autoloader.php`.
- Demand a deliverable plan with: file-by-file changes, schema/migration needs (jeśli kolumna w `{prefix}_woo_quick_ratings` — pamiętać o `dbDelta` + bump opcji wersji DB jeśli istnieje + sync `uninstall.php`), transient-cache invalidation points, WC meta sync (`_wc_average_rating`, `_wc_rating_count`), i18n strings (text domain), manual QA steps (single product page, admin list, REST endpoint via curl/Postman), risks. Plan musi zawierać explicit **acceptance checks** the reviewer will verify (w free-text mode planner sam je proponuje; w issue mode pochodzą z issue).
- Tell it to think carefully (high effort) and to NOT write any code.

In **free-text mode**, after the plan returns, surface the proposed acceptance criteria to the user and get a quick confirmation before moving to Phase 2 — this prevents the implementer from delivering the wrong thing when there is no issue body to anchor on.

Capture the returned plan verbatim — it is the contract for phases 2 and 3.

### Phase 2 — IMPLEMENT (Sonnet, high effort)

Spawn the implementer. Pick `subagent_type` from stack detection above. Pass `model: "sonnet"`.

Brief the implementer with:
- The full plan from Phase 1 (verbatim, as the contract).
- The issue number(s) `#<N>` (or "no issue — ad-hoc task: <short description>" in free-text mode) and branch instruction.
- Hard rules:
  - Follow `CLAUDE.md`.
  - Respect WordPress coding standards (escape output: `esc_html_e`, `esc_attr`, `wp_kses_post`; sanitize input: `sanitize_text_field`, `absint`, etc.; prepared SQL via `$wpdb->prepare`).
  - Class naming + file naming must match the autoloader (`Ihumbak_WRS_Foo_Bar` → `class-foo-bar.php` w odpowiednim katalogu).
  - REST endpoints: rejestrować w `rest_api_init`, używać `permission_callback`, walidować rate-limit i login flag wg plugin options.
  - Cache: po każdej mutacji ocen wyczyścić odpowiednie transienty `ihumbak_wrs_*` dla produktu; rozważyć efekt na WC meta sync.
  - WC filters: nie wpadać w rekurencję (patrz `e10cafc`) — przy obliczaniu combined values używać surowych danych z DB, nie ponownego wywołania filtra.
  - Bump wersji w `ihumbak-woo-rating-stars.php` (header + `IHUMBAK_WRS_VERSION`) + `readme.txt` `Stable tag` **tylko jeśli plan to przewiduje** (zwykle przy release-ready PR).
  - i18n: każdy user-facing string przez `__()` / `_e()` / `esc_html__()` z text domain `ihumbak-woo-rating-stars`.
  - Nie wprowadzać nowych zależności (Composer/npm) bez wyraźnego zgłoszenia w summary.
- Tell it to think carefully (Sonnet high) and to report a concise summary of: files changed, jakie hooki/filtry dotknięte, jakie transienty czyszczone, manualne QA steps wykonane lub do wykonania, anything skipped vs the plan and why.
- Tell it **not to commit** unless the user has previously authorized auto-commit — final commit + PR is a user-confirmed step at the end.

After it returns, spot-check the diff with `git diff --stat` and `git status` (these are cheap and worth doing) before moving on.

### Phase 3 — REVIEW (Opus, high effort)

Spawn `subagent_type: "code-reviewer"` with `model: "opus"`.

Brief the reviewer with:
- The Phase 1 plan (the contract). In free-text mode, include the user-confirmed acceptance criteria explicitly.
- The Phase 2 implementer summary.
- The diff (`git diff main...HEAD` or against the parent branch).
- Project-specific review checklist:
  - **Security**: output escaping przy każdym echo / template; sanitization na inputach; `$wpdb->prepare` dla SQL; nonce + `permission_callback` w REST; capability checks w admin (`current_user_can`).
  - **WP / WC integration**: poprawne użycie hooków (priority, removed/re-added jeśli dotyczy); brak rekurencji w `woocommerce_product_get_*` filtrach; HPOS + blocks compat nadal deklarowane; admin code tylko w `is_admin()`.
  - **Autoloader**: nowe klasy mają prefiks `Ihumbak_WRS_` i właściwą nazwę pliku w jednym z katalogów obsługiwanych przez autoloader.
  - **Database**: zmiany schematu przez `dbDelta`; `uninstall.php` zaktualizowany; SQL używa `$wpdb->prefix`; brak surowych nazw tabel.
  - **Cache**: transient invalidation kompletna — nie ma ścieżki, która zostawi stale dane; klucze zgodne z konwencją `ihumbak_wrs_{type}_{product_id}`.
  - **WC meta sync**: `_wc_average_rating` i `_wc_rating_count` aktualizowane przy zmianie quick ratings i przy zmianach komentarzy WC.
  - **i18n**: text domain spójny; wszystkie user-facing stringi przetłumaczalne.
  - **Front-end**: jQuery only (brak nowych zależności); assets enqueued tylko na single product page; nonce przekazywany przez `wp_localize_script`.
  - **Wersjonowanie / release**: jeśli plan przewidywał bump, to header + stała + `readme.txt` są spójne.
- Demand a structured verdict: **APPROVE** or **CHANGES_REQUESTED** with an itemized list of blocking issues (file:line + concrete fix), plus optional non-blocking suggestions.

### Decision

- **APPROVE** → report the green-light to the user with: branch name, files changed, manual QA status, reviewer's optional suggestions. Ask the user whether to commit and open a PR against `main`. Do not push or create the PR without explicit confirmation.
- **CHANGES_REQUESTED** → loop back to Phase 2 with **only the blocking issues** as the new brief (not the full plan again). Cap at **2 review iterations**. If the third review still requests changes, stop and escalate to the user with a summary of unresolved issues — do not keep looping.

## Reporting back to the user

State the chosen mode in your first status line (e.g. „Tryb: opis zadania — branch `feature/<slug>`", „Tryb: 3 issue (#12, #13, #14) — bundle do jednego PR"). Between phases, send one short status line („Plan gotowy, startuję implementację"). At the end, report:
- Verdict (approved / escalated).
- Branch + diff stat.
- Manual QA steps performed / pending.
- Issue references (or "no issue — ad-hoc" in free-text mode).
- Open follow-ups (non-blocking reviewer notes, deferred work).
- Suggested next action (commit + PR against `main`, or address X then re-run).

## Guardrails

- Never run destructive git ops (`reset --hard`, `push --force`, `branch -D`) without user confirmation.
- Never auto-merge or auto-push.
- In issue-number mode, if any `gh issue view <N>` failed, stop and tell the user — do not fabricate issue content.
- In empty mode, never start the pipeline before the user picks issue(s) or supplies a description.
- If the working tree was dirty at start, ask the user how to proceed (stash, commit, or abort) before creating a new branch.
- PR base branch is `main`.
- Brak skonfigurowanych testów automatycznych — code review + manualne QA w przeglądarce to jedyna kontrola jakości; nie udawaj że istnieje `phpunit` / `pytest`.
