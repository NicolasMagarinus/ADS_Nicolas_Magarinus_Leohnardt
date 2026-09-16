# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`docs/proximos-passos.md` carries the open backlog (28 items with file:line pointers) and the steps to
set the project up on a fresh machine — read it before picking up new work.

Drinkerito — Laravel 12 web app (PHP 8.2) for discovering, rating, submitting and recommending drink recipes. UI, code identifiers and comments are in Portuguese (pt-BR). Deployed on Railway (`railway.toml`, nixpacks — the build runs `npm ci` and `npm run build`, and
the Node version comes from `engines.node` in `package.json`; without it nixpacks picks Node 18).

## Commands

```bash
composer dev                      # serve + queue:listen + pail (logs) + vite, all at once
php artisan serve                 # app only, http://localhost:8000 — needs a prior npm run build

composer test                     # config:clear + artisan test
php artisan test --filter=NomeDoTeste
php artisan test tests/Feature/ExampleTest.php

vendor/bin/pint                   # code style (Laravel preset)

php artisan migrate
php artisan migrate:fresh         # drops everything; the drink catalog is only repopulated by the commands below

npm run dev                       # Vite dev server with HMR; composer dev already starts it
npm run build                     # required before serving without npm run dev
```

`public/build` is gitignored, so a fresh clone has no assets: `npm ci && npm run build` before the
first `php artisan serve`, or every page dies on `ViteManifestNotFoundException`. `composer dev`
runs the Vite dev server alongside, so it needs no build.

Data-population commands (both hit OpenAI and cost money/quota):

```bash
php artisan app:gerar-bebidas-ai --qt_receita=5  # GPT-generated recipes + DALL·E image -> Cloudinary
php artisan app:gerar-ingredientes-ai --qt=50    # ingredient list/images via OpenAI
```

They are not the only way to get a catalog any more: `php artisan db:seed` runs `BebidaSeeder`, which
writes 16 fixed recipes (half of them alcohol-free) without touching OpenAI, and is idempotent. Use it
for development and test data; keep the AI commands for growing the real catalog. The original
TheCocktailDB import was removed in September 2026; the drinks it seeded remain in the database, but
nothing re-fetches them.

Every migration has a working `down()`, so `php artisan migrate:reset` unwinds the schema cleanly —
worth re-checking when you add one. Two of them cannot be exact inverses and say so in a comment:
`bebida.id_externo` comes back nullable (its TheCocktailDB values are gone, so NOT NULL would fail on
any populated table), and `bebida.ds_imagem` going back to `varchar(255)` will fail loudly if a
Cloudinary URL no longer fits, which beats truncating it.

## Database

PostgreSQL only (`DB_CONNECTION=pgsql`). Much of the query logic is raw PostgreSQL and **will not run on SQLite**: `json_agg`/`json_build_object`, `COUNT(...) FILTER (WHERE ...)`, `= ANY(?)` / `<> ALL(?)` with hand-built array literals (`'{1,2,3}'`), the `unaccent` extension (enabled by its own migration) used for accent-insensitive search, and the `f_unaccent()` IMMUTABLE wrapper that backs the unique index on ingredient names. `phpunit.xml` therefore points at a real Postgres database, not SQLite.

`bebida.cd_bebida`, `ingrediente.cd_ingrediente` and `colecao.cd_colecao` are `increments` —
`integer` in Postgres, so 2147483647 is the ceiling. A route's `whereNumber` is `[0-9]+` with no
digit limit, so `/bebida/9999999999` matches the route and only blows up at the bind, where
Postgres refuses it with `SQLSTATE 22003` and the untreated `QueryException` becomes a **500 on a
public route**. Every id coming off a URL therefore goes through `App\Support\Id::validar()`,
which returns the int or 404s. Two things that are easy to get wrong: the controller parameter is
`string` on purpose — typing it `int` makes PHP throw `TypeError` coercing a twenty-digit id
*before* the method runs, a 500 no in-method check can reach — and a route whose parameter is
optional (`{cd_bebida?}`) must accept `?string $x = null`, or the missing argument is its own 500.
This defect came back through five separate doors before the check was centralised.

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

Collection names deliberately do **not** follow that rule: `colecao`'s `unique(id_usuario, nm_colecao)` is accent- and case-sensitive on purpose. An ingredient name is a join key between recipes — two spellings become two rows and Meu Bar stops matching drinks against what the user actually owns — while a collection name joins nothing; the id is what resolves a collection (`Colecao::url()`/`parametroUrl()`, the hybrid `/colecao/{id}-{slug}` route). Don't "fix" this later into an accent/case-insensitive unique index; it would just block "Verão" and "verao" from coexisting for no reason.

### Ingredient pages

`/ingredientes` (`IngredienteController::index`) lists every ingredient that appears in at least one
recipe — orphans are excluded on purpose, since `app:gerar-ingredientes-ai` can create ingredients no
drink ever uses. `/ingrediente/{cd}` shows the drinks that use one. Both are public and paginated
with Eloquent, following `SearchController` rather than the raw-SQL style. An ingredient with no
recipes still answers 200 for a typed URL, but ships `robots=noindex`.

The ingredient list on the drink and random pages links here too, through
`partials/lista-ingredientes.blade.php` — both screens had the same markup duplicated. It expects the
shape `Bebida::getBebida()` returns (`cd_ingrediente`, `nm_ingrediente`, `ds_medida`) and falls back
to plain text when the id is missing. Note that `cadastro_bebida/index.blade.php` lists a *staging*
row's ingredients, which are free text with no id yet, so it does not use this partial.

The home page's "most used ingredients" cards link here. That query had to gain `i.cd_ingrediente` in
both the `SELECT` and the `GROUP BY` (`HomeController.php:27-35`) — it used to group by name alone.

### Home caching

`HomeController` wraps its three catalog-wide `GROUP BY` queries in `Cache::remember` for 10 minutes,
under the keys in `HomeController::CHAVES_CACHE`. `CadastroBebidaController::aprovar` forgets those
keys, because TTL alone would hide a freshly approved drink for ten minutes — exactly when the
moderator goes to check that it worked. The per-user favorites check stays outside the cache: cached
together, one person's home would show another's state.

### Query style

Read-heavy pages bypass Eloquent and use `DB::select` with heredoc SQL that aggregates rating (`AVG(id_nota)`), rating count and ingredient JSON in one round trip: `Bebida::getBebida()` (detail + random — the random path draws a `cd_bebida` with a narrow query first, so the heavy aggregate always runs filtered by id and never builds the ingredient JSON for the whole catalog), `HomeController` (rankings), `MeuBarController::obterBebidasPossiveis` (drinks makeable from owned ingredients, ≤2 missing), `RecomendadasController` (top-5 ingredients from favorites → similar drinks). Eloquent is used for writes and for the paginated `SearchController`, which also carries the search
facets: `tipo`, `nota` (minimum average), `max_ingredientes` and `ingrediente`, all optional query
parameters that combine with each other and with the free-text `q`. An invalid value is ignored
rather than rejected — it is a URL people edit by hand. Two of them are aggregates and therefore live
in `HAVING`, not `WHERE`; the ingredient count uses a scalar subquery instead of another join,
because joining `bebida_ingrediente` beside the `leftJoin` on `avaliacao` multiplies rows and would
make `COUNT(avaliacao.id_nota)` count each rating once per ingredient. A `q` of "alcoólica" or "não
alcoólica" — the phrase that used to be the only way to filter, and that the chatbot instructed —
now 302s to the equivalent `?tipo=`, so old links keep working. Follow the existing style in the file you're editing rather than converting between them.

### Chatbot (`ChatbotController` + `resources/views/partials/chatbot.blade.php`)

Two-tier: `verificarFaq()` matches accent-stripped regexes for navigation/FAQ answers and returns **without** calling OpenAI; only unmatched messages reach the API. **The FAQ tier is skipped entirely once a conversation is open** (`chatbot_historico` non-empty in the session), because its patterns swallow exactly the follow-ups the history exists to serve — "e uma versão sem álcool disso?" matches `sem alcool`, "e com outro ingrediente?" matches `ingrediente`. The trade is deliberate: while a conversation is open, questions the FAQ would have answered for free consume the daily quota.

The last `HISTORICO_MAX` (6) messages ride along with each AI call and are stored in the session. Only real exchanges are recorded — a FAQ answer, a request blocked by the daily limit and a failed API call all leave the history untouched, since with five questions a day a polluted context wastes the few that remain. What is stored as the assistant turn is the cleaned reply the user actually read, which is where the suggested drink's name lives. AI calls are metered per user per day in `chatbot_usage` (`AI_DAILY_LIMIT = 5`, returns HTTP 429 when exhausted). The model may call the `sugerir_receita` tool; the structured recipe is returned to the browser, checked against the catalog and the user's pending submissions, and can be pushed into the staging pipeline via `chatbot.salvar-bebida`. All routes require auth. The entire chatbot UI and its JS live inline in the Blade partial, included globally from the layout.

### Meu Bar

A user's own ingredients live in `usuario_ingrediente` (`id_usuario` + `cd_ingrediente`, unique on
the pair), which is the source of truth — not the session, which no longer holds them at all.
`MeuBarController::salvar` (route `meubar.salvar`) receives the full list of ingredient ids and
reconciles it inside a transaction, so the `created_at` of rows that stay is preserved and the chips
keep their order. The page still mirrors the list into `localStorage` on every write; that mirror
exists so users who had a bar before the table existed keep it (when the server sends an empty list
and the mirror is not empty, the page saves it once), and it must be rewritten on every save — a
stale mirror would resurrect a bar the user just cleared. The screen requires login, as it always
has.

### Moderation panel

`/admin/bebidas` splits into three tabs by `?status=pendentes|aprovadas|rejeitadas` (anything else
falls back to the queue). Pending is a queue and lists oldest first; the two history tabs are history
and list the most recent decision first. Approve and reject buttons render only on the pending tab.
Each decision records `id_moderador` and `dt_moderacao` on `cadastro_bebida`; both are nullable,
because rows decided before those columns existed cannot be backfilled, and the FK is
`nullOnDelete` so removing an admin never deletes the recipes they moderated. The view falls back to
`updated_at` and to "não registrado" for those older rows.

### Moderation notices

Approving or rejecting a submission notifies its author by e-mail
(`App\Notifications\BebidaModerada`, rendered with `emails.bebida-moderada` so it matches the
recovery e-mail's hand-written style rather than Laravel's default template). Two rules the code
depends on, both in `CadastroBebidaController::avisarAutor`: it is called **after** the
`DB::transaction` commits — inside it, a slow SMTP would hold the transaction open and a mailer
exception would roll back a perfectly good approval — and a send failure is logged, never rethrown,
because by then the drink is already in the catalog and a 500 would make the admin approve it twice.
It reads `$cadastro->usuario` without a null check, which is safe only because `id_usuario` is NOT
NULL and its FK cascades; `NotificacaoModeracaoTest` pins that invariant.

`BebidaModerada` implements `ShouldQueue`, so the send is dispatched rather than performed inline.
With `QUEUE_CONNECTION=sync` — the current setting everywhere, including the tests — nothing changes
in practice. What does change is that the whole notification is now serialized, and the suite never
exercises that path; `FilaModeracaoTest` does, against the `database` queue with a real
`queue:work`, because a serialization break fails silently and the author simply never hears back.

### Password recovery

Three steps (`RecuperacaoSenhaController`): request a code, confirm the 6-digit code, choose the new
password. The email under recovery travels in the session, never in the URL or a form field, so the
code check cannot be skipped by editing a parameter, and one person's wrong guesses cannot burn
another's attempts. The code is stored hashed in `password_reset_tokens.token`, expires in 15
minutes, and dies after 5 wrong guesses (the `tentativas` column). A request for an unknown email
returns exactly the same response as a known one.

The third step re-reads `password_reset_tokens` before saving the new password. The session mark
only records that the code *was* checked, not that it still holds: without the re-check, a tab left
open past the 15 minutes would still change the password. Both steps share `expirou()` so the rule
lives in one place.

### Profile editing

`PerfilController::atualizar` (`POST /profile`) changes the user's own `name` and `ds_avatar` — a
Cloudinary URL on `users`, like every other image in the project. The avatar is cropped square
(`fill` + `gravity: face`), unlike the drink upload, which uses `limit` to keep the photo's aspect
ratio. Posting without a file keeps the current avatar instead of clearing it.

**The e-mail is deliberately not editable.** `GoogleController` identifies an account by e-mail
(`firstOrCreate(['email' => ...])`), so changing it would make the next Google login create a second
account and strand the first one's favorites, recipes and ratings. Fixing that needs a `google_id`
column first — it is FEAT-15 in the backlog.

### External services

OpenAI (`openai-php/laravel`, `config/openai.php`, model from `OPENAI_MODEL`), Cloudinary (all drink/ingredient images — uploads return a secure URL stored in `ds_imagem`; no local disk storage), Laravel Socialite for Google login (`GoogleController`, stateless, auto-creates the user).

### Frontend assets

Everything is bundled by **Vite**, and **nothing comes from a CDN** — that was QA-06, done in
September 2026. `public/` holds no hand-written asset any more: the sources live in
`resources/css|js`, and `public/build` is the build output (gitignored, so it does not exist in a
fresh clone).

Six entries, declared in `vite.config.js`. Adding one there is not optional: a view that asks for
an entry the config does not declare passes every test that renders and only fails in production,
where the manifest is real. `AssetsJsTest` cross-checks the two.

| Entry | Where |
|---|---|
| `resources/css/app.css` | the layout, every page |
| `resources/js/app.js` | the layout, every page |
| `resources/css/auth.css` | login, register, the recovery layout |
| `resources/js/chatbot.js` | the chatbot partial |
| `resources/js/meubar.js` | Meu Bar |
| `resources/js/colecao.js` | the drink page |
| `resources/js/cadastro-bebida.js` | the submission form |

`app.css` imports Bootstrap, both icon packs and then **`custom.css` last** — that order is what
keeps the hand-written stylesheet (941 lines, CSS variables at the top) winning over Bootstrap.
Style changes still belong in `custom.css`, now at `resources/css/custom.css`. Tailwind was
removed entirely, with `postcss` and `autoprefixer`: its preflight would reset element styling
across a site built on Bootstrap. Adding a Tailwind class will not work. Pagination is Bootstrap
5-styled via `AppServiceProvider`.

`auth.css` exists because login, register and the recovery screens build their own `<html>` and
never passed through `layouts/app.blade.php`. It deliberately **does not** import `custom.css`:
those screens never received it, and their 472 lines of inline `<style>` were written against
plain Bootstrap. Importing it there would be redesigning five screens, not migrating a pipeline.

Library versions are **pinned on purpose** (`bootstrap-icons` 1.10.0, `@fortawesome/fontawesome-free`
5.15.4): they are what the CDNs used to serve, across 73 and 83 icon usages in 17 views, with no
visual test that would catch one going missing. Font Awesome 6 renames icons, so a bare upgrade
silently empties them. The migration already found two screens carrying FA6 names — `fa-solid
fa-champagne-glasses` in Meu Bar, which was simply not rendering, and `fa-rotate-right` in the
recovery screens, which worked only because those screens loaded a different FA version than the
rest of the site.

Images are requested from Cloudinary at the size they are displayed, through `App\Support\Imagem`
and its `@imagem($url, $largura)` Blade directive — the transformation (`w_N,f_auto,q_auto`) is
inserted after `/upload/`. It returns the shared placeholder when the URL is empty, and leaves alone
any URL that is not Cloudinary's or that already carries a transformation. `window.Drinkerito.imagem`
mirrors it for the screens built in JavaScript. Two rules worth keeping: `og:image` must stay
**untransformed**, because WhatsApp and Facebook want the large image in the link preview; and the
image at the top of the drink and random pages is not `loading="lazy"`, since it is the content the
visitor came for.

Page JavaScript lives in `resources/js/`. Values only the server knows (CSRF token, route URLs,
initial data) stay in a short inline `<script>` per view that defines a config object the module
reads — `window.DrinkeritoChatbot`, `DrinkeritoMeuBar`, `DrinkeritoColecao`. That is what lets the
bulk of the code be a hashed, cacheable file.

**Three globals are still assigned by hand** in `resources/js/app.js`, and removing any of them
breaks one screen, silently and only that one: `window.Drinkerito` (the header's live search),
`window.Swal` (favourites, both `cadastro_bebida` screens, the drink page) and `window.bootstrap`
(the profile's `new bootstrap.Modal`). They exist because the 574 lines of JavaScript still inline
in the views are not modules and read these names off the global scope. `AssetsJsTest` pins all
three; the assertions look at the assignment, not the docblock, because the first version of that
test matched its own comment and passed with the code deleted.

Two ordering rules that bit during the migration and will bite again:

- **A page script that reads `window.Drinkerito` at module level must `import './drinkerito.js'`.**
  `chatbot.js` and `meubar.js` do it in the body of the IIFE, not inside a handler. Under the old
  `@js` directive this worked by tag order in the document; as separate entries it would depend on
  the same fragile order. The import makes the module graph guarantee it, and Rollup pulls the
  helper into one shared 577-byte chunk.
- **An inline `<script>` must not touch `$`, `Swal` or `bootstrap` at top level.** Classic inline
  scripts run *during* parsing; `@vite` emits modules, which are deferred and run *after*. The
  submission form opened with `$(document).ready` and became `$ is not defined` the moment jQuery
  became a module — the whole screen lost its behaviour, with nothing failing anywhere. Wrap the
  block in `DOMContentLoaded`, which fires after the modules. `AssetsJsTest` guards the pattern.

`cadastro-bebida.js` carries jQuery and select2 for the submission form, and is the fiddliest file
in the project for two reasons worth reading its comments before touching: the jQuery global lives
in its own module (`jquery-global.js`) because import declarations are hoisted, so assigning it
between two imports runs too late; and select2, over CommonJS, exports a factory that **must be
called** — importing it is not enough to register `$.fn.select2`.

The test suite runs with `withoutVite()` (`Tests\TestCase`), because `public/build` is gitignored
and absent from a fresh clone; without it all 28 view-rendering test files would die on
`ViteManifestNotFoundException` at once. The cost is that nothing asserts on the rendered asset
HTML, which is why `AssetsJsTest` checks the contract from the source side instead.

### Validation messages

`lang/pt_BR/validation.php` carries the translations, and its `attributes` block maps the Hungarian
column names to readable Portuguese — without it `:attribute` renders as "ds preparo". Add an entry
there whenever a new column reaches a form. One trap: a `Rule::enum` message **cannot** be
overridden with a `'campo.enum'` key. For object rules Laravel builds the custom-message key from
the rule's **class name** (`Validator::validateUsingCustomRule`), so only
`'campo.'.Illuminate\Validation\Rules\Enum::class` would match. Prefer the generic translated
message over coupling a controller to that.

Views extend `layouts.app` and use `@yield('content')`; header/footer/chatbot come from `resources/views/partials/`.

`partials/meta.blade.php`, included from the layout's `<head>`, builds the `<title>`, the meta
description and the Open Graph tags. A page declares only the short name — `@section('titulo', 'Caipirinha')`
— and the partial appends `— Drinkerito`; a page that declares nothing gets the site-wide defaults.
The optional sections are `descricao`, `og_imagem` (emitted only when present, and it drives whether
`twitter:card` is `summary_large_image` or `summary`), `og_tipo` and `robots` (`noindex` on the
screens behind login). **Declare these with the inline form**, `@section('name', $value)`: Blade runs
`e()` on inline section values, so the partial prints them with `{!! !!}` to avoid escaping twice —
a drink named `Gin & Tonic "Especial"` would otherwise reach the browser tab as `&amp;amp;`. Page-specific JS is written inline in each Blade file, with `fetch` + `X-CSRF-TOKEN` for the JSON endpoints.

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
moderation panel, accent-insensitive search, the favorite toggle, the submission form, the enum
casts, the moderation notice surviving a real queue round trip, and the asset contract described
under "Frontend assets". Those carry the logic that costs money, corrupts the catalog, or breaks
silently. Tests fake OpenAI via `OpenAI::fake()` and never reach the real API.

**The suite cannot see a broken front end.** It runs with `withoutVite()`, no browser executes
anything, and the asset tests read sources rather than rendered HTML. Every defect the Vite
migration turned up — select2 never registering, `$ is not defined` wiping out the submission
form, an icon silently absent — was found by rendering a page and running its JavaScript, and
would have shipped green. When you touch assets, render the screens.

`Mail::fake()` intercepts before the message is built, so it never catches a broken email template or
a missing sender. `RecuperacaoSenhaTest` therefore also renders the Mailable for real and asserts
`mail.from.address` is set — a null `MAIL_FROM_ADDRESS` makes every send throw *"An email must have a
From header"*. Note also that the `log` mailer writes at **debug** level, so `LOG_LEVEL=info` silently
swallows the email you are trying to read in `storage/logs`.
