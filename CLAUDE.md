# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Drinkerito — Laravel 12 web app (PHP 8.2) for discovering, rating, submitting and recommending drink recipes. UI, code identifiers and comments are in Portuguese (pt-BR). Deployed on Railway (`railway.toml`, nixpacks).

## Commands

```bash
composer dev                      # serve + queue:listen + pail (logs) + vite, all at once
php artisan serve                 # app only, http://localhost:8000

composer test                     # config:clear + artisan test
php artisan test --filter=NomeDoTeste
php artisan test tests/Feature/ExampleTest.php

vendor/bin/pint                   # code style (Laravel preset)

php artisan migrate
php artisan migrate:fresh         # drops everything; the drink catalog is only repopulated by the commands below

npm run dev / npm run build       # Vite — see "Frontend assets" caveat
```

Data-population commands (both hit OpenAI and cost money/quota):

```bash
php artisan app:gerar-bebidas-ai --qt_receita=5  # GPT-generated recipes + DALL·E image -> Cloudinary
php artisan app:gerar-ingredientes-ai --qt=50    # ingredient list/images via OpenAI
```

These are the only way to repopulate the catalog. The original TheCocktailDB import was removed in
September 2026; the drinks it seeded remain in the database, but nothing re-fetches them.

## Database

PostgreSQL only (`DB_CONNECTION=pgsql`). Much of the query logic is raw PostgreSQL and **will not run on SQLite**: `json_agg`/`json_build_object`, `COUNT(...) FILTER (WHERE ...)`, `= ANY(?)` / `<> ALL(?)` with hand-built array literals (`'{1,2,3}'`), and the `unaccent` extension (enabled by its own migration) used for accent-insensitive search. Note that `phpunit.xml` pins the test suite to in-memory SQLite, so any feature test touching these paths needs a real Postgres connection instead.

### Naming conventions (non-Laravel)

Tables are singular Portuguese names; columns use Hungarian-style prefixes: `cd_` = primary/foreign key, `nm_` = name, `ds_` = description/text, `id_` = enum or boolean flag, `qt_` = count, `dt_` = date. Because PKs are not `id`, every model must declare `$table` and `$primaryKey` explicitly, and relationships must pass the foreign key by hand (`hasMany(Favorito::class, 'cd_bebida')`). `users` is the one stock Laravel table (`id`), which is why join columns are `id_usuario` → `users.id`.

### Enum values (magic numbers used throughout controllers and views)

- `bebida.id_tipo`: `1` = alcoólica, `2` = não alcoólica
- `cadastro_bebida.id_status`: `0` = pendente, `1` = aprovada, `2` = rejeitada (with `ds_motivo_rejeicao`)
- `users.id_admin`: boolean; admin checks are inline `if (!Auth::user()->id_admin) abort(403)` in `CadastroBebidaController` — there is no middleware or policy.

## Architecture

### Drink submission pipeline

`cadastro_bebida` + `cadastro_bebida_ingrediente` are a **staging area**, deliberately separate from the live catalog. Users (or the chatbot) write there with `id_status = 0`; ingredients are stored as free text (`nm_ingrediente`). On admin approval (`CadastroBebidaController::aprovar`) the row is copied into `bebida`, each ingredient name is `Str::title`-normalized and resolved via `Ingrediente::firstOrCreate`, and links land in `bebida_ingrediente`. Only then is the drink visible in search/random/detail. Never write directly to `bebida` from a user-facing path.

### Query style

Read-heavy pages bypass Eloquent and use `DB::select` with heredoc SQL that aggregates rating (`AVG(id_nota)`), rating count and ingredient JSON in one round trip: `Bebida::getBebida()` (detail + random), `HomeController` (rankings), `MeuBarController::obterBebidasPossiveis` (drinks makeable from owned ingredients, ≤2 missing), `RecomendadasController` (top-5 ingredients from favorites → similar drinks). Eloquent is used for writes and for the paginated `SearchController`. Follow the existing style in the file you're editing rather than converting between them.

### Chatbot (`ChatbotController` + `resources/views/partials/chatbot.blade.php`)

Two-tier: `verificarFaq()` matches accent-stripped regexes for navigation/FAQ answers and returns **without** calling OpenAI; only unmatched messages reach the API. AI calls are metered per user per day in `chatbot_usage` (`AI_DAILY_LIMIT = 5`, returns HTTP 429 when exhausted). The model may call the `sugerir_receita` tool; the structured recipe is returned to the browser, checked against the catalog and the user's pending submissions, and can be pushed into the staging pipeline via `chatbot.salvar-bebida`. All routes require auth. The entire chatbot UI and its JS live inline in the Blade partial, included globally from the layout.

### External services

OpenAI (`openai-php/laravel`, `config/openai.php`, model from `OPENAI_MODEL`), Cloudinary (all drink/ingredient images — uploads return a secure URL stored in `ds_imagem`; no local disk storage), Laravel Socialite for Google login (`GoogleController`, stateless, auto-creates the user).

### Frontend assets

Vite, Tailwind and `resources/js|css` exist from the Laravel skeleton but **the layout does not use `@vite`**. `resources/views/layouts/app.blade.php` loads Bootstrap 5, Bootstrap Icons, Font Awesome and SweetAlert2 from CDNs plus `public/css/custom.css` (the single hand-written stylesheet, CSS variables at the top). Style changes belong in `public/css/custom.css`; adding a class from Tailwind will not work. Pagination is Bootstrap 5-styled via `AppServiceProvider`.

Views extend `layouts.app` and use `@yield('content')`; header/footer/chatbot come from `resources/views/partials/`. Page-specific JS is written inline in each Blade file, with `fetch` + `X-CSRF-TOKEN` for the JSON endpoints.

## Tests

Only the default Laravel scaffolding exists in `tests/` — there is no meaningful coverage yet.
