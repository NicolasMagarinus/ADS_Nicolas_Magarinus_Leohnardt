# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`docs/proximos-passos.md` carries the open backlog (28 items with file:line pointers) and the steps to
set the project up on a fresh machine — read it before picking up new work.

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

PostgreSQL only (`DB_CONNECTION=pgsql`). Much of the query logic is raw PostgreSQL and **will not run on SQLite**: `json_agg`/`json_build_object`, `COUNT(...) FILTER (WHERE ...)`, `= ANY(?)` / `<> ALL(?)` with hand-built array literals (`'{1,2,3}'`), the `unaccent` extension (enabled by its own migration) used for accent-insensitive search, and the `f_unaccent()` IMMUTABLE wrapper that backs the unique index on ingredient names. `phpunit.xml` therefore points at a real Postgres database, not SQLite.

### Naming conventions (non-Laravel)

Tables are singular Portuguese names; columns use Hungarian-style prefixes: `cd_` = primary/foreign key, `nm_` = name, `ds_` = description/text, `id_` = enum or boolean flag, `qt_` = count, `dt_` = date. Because PKs are not `id`, every model must declare `$table` and `$primaryKey` explicitly, and relationships must pass the foreign key by hand (`hasMany(Favorito::class, 'cd_bebida')`). `users` is the one stock Laravel table (`id`), which is why join columns are `id_usuario` → `users.id`.

### Enum values

The two coded columns are PHP enums in `app/Enums/`, cast on the models, each with a `label()` the
views use:

- `bebida.id_tipo` / `cadastro_bebida.id_tipo` → `TipoBebida`: `1` = alcoólica, `2` = não alcoólica
- `cadastro_bebida.id_status` → `StatusCadastro`: `0` = pendente, `1` = aprovada, `2` = rejeitada
  (with `ds_motivo_rejeicao`); it also carries `classeBadge()` and `icone()` for the profile badge

The backing values are what is already stored in the database and must not change. Reading these
columns through Eloquent now yields an enum, so `$bebida->id_status == 0` no longer matches —
compare against a case. The raw `DB::select` queries are unaffected and still return integers.

- `users.id_admin`: boolean, enforced by the `admin` middleware (`App\Http\Middleware\GarantirAdmin`,
  aliased in `bootstrap/app.php`) on the `Route::prefix('admin')` group — not by checks inside the
  controller methods.

## Architecture

### Drink submission pipeline

`cadastro_bebida` + `cadastro_bebida_ingrediente` are a **staging area**, deliberately separate from the live catalog. Users (or the chatbot) write there with `id_status = 0`; ingredients are stored as free text (`nm_ingrediente`). On admin approval (`CadastroBebidaController::aprovar`) the row is copied into `bebida` — carrying `id_tipo` and `ds_bebida` across — each ingredient name is resolved through `Ingrediente::normalizar()`, and links land in `bebida_ingrediente`. Only then is the drink visible in search/random/detail. Never write directly to `bebida` from a user-facing path.

`Ingrediente::normalizar()` is the single write path for ingredient names: it matches ignoring accent and case (the same rule as the `ingrediente_nome_unico` index), so `Agua`, `Água` and `ÁGUA` always resolve to one row. Both AI commands go through it too. Writing to `ingrediente` any other way will eventually hit the unique index.

### Query style

Read-heavy pages bypass Eloquent and use `DB::select` with heredoc SQL that aggregates rating (`AVG(id_nota)`), rating count and ingredient JSON in one round trip: `Bebida::getBebida()` (detail + random), `HomeController` (rankings), `MeuBarController::obterBebidasPossiveis` (drinks makeable from owned ingredients, ≤2 missing), `RecomendadasController` (top-5 ingredients from favorites → similar drinks). Eloquent is used for writes and for the paginated `SearchController`. Follow the existing style in the file you're editing rather than converting between them.

### Chatbot (`ChatbotController` + `resources/views/partials/chatbot.blade.php`)

Two-tier: `verificarFaq()` matches accent-stripped regexes for navigation/FAQ answers and returns **without** calling OpenAI; only unmatched messages reach the API. AI calls are metered per user per day in `chatbot_usage` (`AI_DAILY_LIMIT = 5`, returns HTTP 429 when exhausted). The model may call the `sugerir_receita` tool; the structured recipe is returned to the browser, checked against the catalog and the user's pending submissions, and can be pushed into the staging pipeline via `chatbot.salvar-bebida`. All routes require auth. The entire chatbot UI and its JS live inline in the Blade partial, included globally from the layout.

### Password recovery

Three steps (`RecuperacaoSenhaController`): request a code, confirm the 6-digit code, choose the new
password. The email under recovery travels in the session, never in the URL or a form field, so the
code check cannot be skipped by editing a parameter, and one person's wrong guesses cannot burn
another's attempts. The code is stored hashed in `password_reset_tokens.token`, expires in 15
minutes, and dies after 5 wrong guesses (the `tentativas` column). A request for an unknown email
returns exactly the same response as a known one.

### External services

OpenAI (`openai-php/laravel`, `config/openai.php`, model from `OPENAI_MODEL`), Cloudinary (all drink/ingredient images — uploads return a secure URL stored in `ds_imagem`; no local disk storage), Laravel Socialite for Google login (`GoogleController`, stateless, auto-creates the user).

### Frontend assets

Vite, Tailwind and `resources/js|css` exist from the Laravel skeleton but **the layout does not use `@vite`**. `resources/views/layouts/app.blade.php` loads Bootstrap 5, Bootstrap Icons, Font Awesome and SweetAlert2 from CDNs plus `public/css/custom.css` (the single hand-written stylesheet, CSS variables at the top). Style changes belong in `public/css/custom.css`; adding a class from Tailwind will not work. Pagination is Bootstrap 5-styled via `AppServiceProvider`.

Views extend `layouts.app` and use `@yield('content')`; header/footer/chatbot come from `resources/views/partials/`. Page-specific JS is written inline in each Blade file, with `fetch` + `X-CSRF-TOKEN` for the JSON endpoints.

## Tests

Local development and the test suite both run against a Postgres container, kept separate from the
deployed Railway database:

```bash
docker start drinkerito-db          # postgres:17-alpine on port 5434
php artisan test                    # uses drinkerito_test on the same container
php artisan test --filter=MeuBarTest
```

`phpunit.xml` carries the test connection, so no `.env.testing` is needed. Feature tests use
`RefreshDatabase`, which runs every migration — including the ingredient merge — against
`drinkerito_test`. Pointing that connection at any database you care about will drop its tables.

Coverage is deliberately narrow: the chatbot's daily AI quota, the approval pipeline (drink type and
ingredient normalization), the Meu Bar ingredient matching, password recovery, admin access to the
moderation panel, accent-insensitive search, the favorite toggle, the submission form, and the enum
casts. Those carry the logic that costs money, corrupts the catalog, or breaks silently. Tests fake OpenAI via
`OpenAI::fake()` and never reach the real API.

`Mail::fake()` intercepts before the message is built, so it never catches a broken email template or
a missing sender. `RecuperacaoSenhaTest` therefore also renders the Mailable for real and asserts
`mail.from.address` is set — a null `MAIL_FROM_ADDRESS` makes every send throw *"An email must have a
From header"*. Note also that the `log` mailer writes at **debug** level, so `LOG_LEVEL=info` silently
swallows the email you are trying to read in `storage/logs`.
