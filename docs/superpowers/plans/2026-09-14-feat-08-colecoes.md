# FEAT-08 — Coleções de drinks: plano de implementação

> **Para quem executa:** SUB-SKILL OBRIGATÓRIA: use `superpowers:subagent-driven-development`
> (recomendado) ou `superpowers:executing-plans` para implementar tarefa a tarefa. Os passos usam
> checkbox (`- [ ]`) para acompanhamento.

**Objetivo:** permitir que a pessoa agrupe drinks em listas nomeadas, privadas ou públicas, e que a
lista pública tenha URL própria indexável.

**Arquitetura:** duas tabelas novas (`colecao` + `colecao_bebida`) sem tocar em `favorito`; um
`ColecaoController` no estilo Eloquent paginado do `IngredienteController`; URL híbrida
`/colecao/{id}-{slug}` em que o id resolve e o slug é derivado do nome, com 301 para a forma
canônica.

**Stack:** Laravel 12, PHP 8.2, PostgreSQL (only), Blade + Bootstrap 5, JS estático em `public/js/`
via diretiva `@js`. Sem Vite (ver QA-06).

**Spec:** `docs/superpowers/specs/2026-09-14-feat-08-colecoes-design.md`

## Restrições globais

Valem para toda tarefa deste plano. Vêm do `CLAUDE.md` e da spec.

- **PostgreSQL only.** Nada de SQLite. Os testes rodam contra `drinkerito_test`; suba o banco com
  `docker start drinkerito-db` antes.
- **Nomes em pt-BR.** UI, identificadores e comentários em português, como o resto do projeto.
- **Prefixos húngaros:** `cd_` chave, `nm_` nome, `ds_` texto, `id_` flag/enum, `qt_` contagem,
  `dt_` data. Tabela no singular.
- **PK não é `id`:** todo model declara `$table` e `$primaryKey`, e toda relação passa a chave
  estrangeira na mão.
- **`bebida.cd_bebida` é `increments` (int4).** O FK é `unsignedInteger`, **não**
  `unsignedBigInteger` — o `favorito` errou esse tipo e o Postgres engoliu; não copie de lá.
- **Toda migration tem `down()` que funciona.** `php artisan migrate:reset` precisa desfazer limpo.
- **Seções de meta na forma inline** `@section('nome', $valor)` — o `partials/meta.blade.php`
  imprime com `{!! !!}` porque o valor já chega escapado.
- **`og:image` fica sem transformação**; imagens de tela usam `@imagem($url, $largura)`.
- **JS de página vai para `public/js/`** via `@js('arquivo.js')`, lendo um objeto de config de um
  `<script>` inline curto. Nada de Tailwind: o CSS é `public/css/custom.css`.
- **Coluna nova em formulário entra em `lang/pt_BR/validation.php`**, bloco `attributes`.
- **Rodar `vendor/bin/pint` nos arquivos criados** antes de cada commit (o projeto todo está fora do
  padrão; não formate o que você não tocou).
- Comandos: `php artisan test --filter=<Nome>` roda em menos de um segundo.

---

## Estrutura de arquivos

**Criados:**

| Arquivo | Responsabilidade |
|---|---|
| `database/migrations/2026_09_14_100000_create_colecao_table.php` | tabela `colecao` |
| `database/migrations/2026_09_14_100100_create_colecao_bebida_table.php` | tabela `colecao_bebida` |
| `app/Models/Colecao.php` | model + a regra da URL canônica (`slug`, `url()`) |
| `app/Models/ColecaoBebida.php` | pivô |
| `app/Http/Controllers/ColecaoController.php` | índice, show, CRUD, endpoints JSON |
| `resources/views/colecao/index.blade.php` | `/colecoes` |
| `resources/views/colecao/show.blade.php` | `/colecao/{id}-{slug}` |
| `resources/views/partials/modal-colecao.blade.php` | modal "adicionar a coleção" |
| `public/js/colecao.js` | JS do modal |
| `tests/Feature/ColecaoModeloTest.php` | cascatas e a regra do slug |
| `tests/Feature/ColecaoUrlTest.php` | a canonicalização 301 |
| `tests/Feature/ColecaoVisibilidadeTest.php` | privada, índice ≥3, noindex |
| `tests/Feature/ColecaoBebidaTest.php` | endpoints de adicionar/remover |
| `tests/Feature/ColecaoCrudTest.php` | criar, renomear, apagar, limite de 50 |

**Modificados:**

| Arquivo | O quê |
|---|---|
| `resources/views/partials/meta.blade.php` | emite `<link rel="canonical">` (Tarefa 1) |
| `tests/Feature/MetaTagsTest.php` | testes do canonical |
| `routes/web.php` | rotas de coleção |
| `app/Http/Controllers/PerfilController.php` | contador e lista de coleções |
| `resources/views/perfil/index.blade.php` | card de estatísticas e seção "Minhas Coleções" |
| `resources/views/bebida/show.blade.php` | terceiro botão + include do modal |
| `lang/pt_BR/validation.php` | `attributes` das colunas novas |
| `docs/proximos-passos.md` | FEAT-08 sai do backlog |

---

## Task 1: `<link rel="canonical">` no partial de meta

Pré-requisito da URL híbrida, e **commit próprio**: o partial vale para o site inteiro, não só para
coleções. Se isto quebrar, quebra em toda página — por isso a suíte inteira roda antes do commit.

**Files:**
- Modify: `resources/views/partials/meta.blade.php`
- Test: `tests/Feature/MetaTagsTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: seção Blade opcional `canonical` — `@section('canonical', $url)` sobrescreve o padrão.
  O padrão é `url()->current()`, mais `?page=N` quando `N > 1`.

- [ ] **Step 1: Escreva os testes que falham**

Acrescente ao fim da classe em `tests/Feature/MetaTagsTest.php`:

```php
    public function test_pagina_emite_canonical_da_url_atual(): void
    {
        $this->get(route('ingrediente.index'))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('ingrediente.index').'">', false);
    }

    public function test_canonical_da_pagina_2_carrega_o_page(): void
    {
        // Sem o ?page aqui, o canonical declara a página 2 duplicata da 1 e
        // manda o buscador descartar tudo que vem depois da primeira.
        $this->get(route('ingrediente.index').'?page=2')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('ingrediente.index').'?page=2">', false);
    }

    public function test_canonical_ignora_parametro_que_nao_seja_pagina(): void
    {
        $this->get(route('ingrediente.index').'?utm_source=whatsapp')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('ingrediente.index').'">', false);
    }
```

- [ ] **Step 2: Rode e confirme que falham**

Run: `php artisan test --filter=MetaTagsTest`
Expected: FAIL nos três novos — a string `rel="canonical"` não existe no HTML.

- [ ] **Step 3: Implemente**

Em `resources/views/partials/meta.blade.php`, dentro do bloco `@php`, logo depois da linha
`$robots = trim($__env->yieldContent('robots'));`:

```php
    // Fecha a URL híbrida das coleções: /colecao/12 e /colecao/12-nome-velho
    // redirecionam 301 para a forma canônica, e o canonical diz qual é ela
    // para quem linkar a forma antiga.
    //
    // O e() mora aqui, e não no {!! !!} lá embaixo, porque o conteúdo de
    // seção já chega escapado (a regra do topo deste arquivo) e url()->current()
    // chega cru. Escapando os dois no mesmo ponto, a saída sai escapada
    // exatamente uma vez.
    $canonical = trim($__env->yieldContent('canonical'));

    if ($canonical === '') {
        $atual = url()->current();
        $pagina = (int) request()->query('page');

        // Página 2 não é duplicata da 1.
        $canonical = e($pagina > 1 ? $atual.'?page='.$pagina : $atual);
    }
```

Logo depois da linha `<meta name="description" ...>`, acrescente:

```blade
    <link rel="canonical" href="{!! $canonical !!}">
```

E troque a linha do `og:url`, que hoje repete o cálculo, para usar o mesmo valor:

```blade
    <meta property="og:url" content="{!! $canonical !!}">
```

- [ ] **Step 4: Rode os testes do arquivo**

Run: `php artisan test --filter=MetaTagsTest`
Expected: PASS, inclusive os testes antigos de `og:url`.

- [ ] **Step 5: Rode a suíte inteira**

Run: `composer test`
Expected: PASS. Este partial entra em toda página que estende `layouts.app`; uma regressão aqui
aparece em qualquer teste que faça `assertSee` no `<head>`.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint resources/views/partials/meta.blade.php tests/Feature/MetaTagsTest.php
git add resources/views/partials/meta.blade.php tests/Feature/MetaTagsTest.php
git commit -m "Emite canonical em todas as páginas"
```

---

## Task 2: tabelas e models

**Files:**
- Create: `database/migrations/2026_09_14_100000_create_colecao_table.php`
- Create: `database/migrations/2026_09_14_100100_create_colecao_bebida_table.php`
- Create: `app/Models/Colecao.php`
- Create: `app/Models/ColecaoBebida.php`
- Test: `tests/Feature/ColecaoModeloTest.php`

**Interfaces:**
- Consumes: nada.
- Produces:
  - `Colecao` — `$table='colecao'`, `$primaryKey='cd_colecao'`,
    `$fillable=['id_usuario','nm_colecao','ds_colecao','id_publica']`, cast `id_publica => boolean`.
  - `Colecao::getSlugAttribute(): string` — `Str::slug($this->nm_colecao)`, podendo ser `''`.
  - `Colecao::parametroUrl(): string` — `"12-drinks-de-verao"`, ou `"12"` quando o slug é vazio.
  - `Colecao::url(): string` — a URL absoluta canônica. **Só use depois da Tarefa 3**, que cria a
    rota `colecao.show`.
  - `Colecao::usuario()` (belongsTo `User`, `id_usuario`), `Colecao::bebidas()` (belongsToMany
    `Bebida` via `colecao_bebida`).
  - `ColecaoBebida` — `$table='colecao_bebida'`, `$primaryKey='cd_colecao_bebida'`,
    `$fillable=['cd_colecao','cd_bebida']`.

- [ ] **Step 1: Escreva o teste que falha**

Crie `tests/Feature/ColecaoModeloTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\Colecao;
use App\Models\ColecaoBebida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O slug da URL é derivado do nome, não guardado em coluna: se ele virasse
 * coluna, todo rename teria de ressincronizá-lo, e slug velho no banco é
 * justamente o defeito que a URL híbrida existe para não ter.
 *
 * As duas cascatas também estão aqui porque falham caladas: apagar a conta
 * deixando coleção órfã só aparece quando alguém abre o link meses depois.
 */
class ColecaoModeloTest extends TestCase
{
    use RefreshDatabase;

    private function bebida(string $nome = 'Mojito'): Bebida
    {
        return Bebida::create([
            'nm_bebida' => $nome,
            'ds_preparo' => 'Macere hortelã e limão, complete com rum.',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Cubano.',
        ]);
    }

    private function colecao(User $dono, string $nome = 'Drinks de verão'): Colecao
    {
        return Colecao::create([
            'id_usuario' => $dono->id,
            'nm_colecao' => $nome,
            'id_publica' => true,
        ]);
    }

    public function test_o_parametro_da_url_junta_id_e_slug(): void
    {
        $colecao = $this->colecao(User::factory()->create());

        $this->assertSame('drinks-de-verao', $colecao->slug);
        $this->assertSame($colecao->cd_colecao.'-drinks-de-verao', $colecao->parametroUrl());
    }

    public function test_nome_sem_letra_nenhuma_cai_para_o_id_puro(): void
    {
        // Str::slug('???') devolve '', e "12-" é URL feia com hífen solto.
        $colecao = $this->colecao(User::factory()->create(), '???');

        $this->assertSame('', $colecao->slug);
        $this->assertSame((string) $colecao->cd_colecao, $colecao->parametroUrl());
    }

    public function test_apagar_o_usuario_leva_as_colecoes_junto(): void
    {
        $dono = User::factory()->create();
        $colecao = $this->colecao($dono);
        ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $this->bebida()->cd_bebida]);

        $dono->delete();

        $this->assertDatabaseCount('colecao', 0);
        $this->assertDatabaseCount('colecao_bebida', 0);
    }

    public function test_apagar_a_bebida_a_tira_das_colecoes(): void
    {
        $colecao = $this->colecao(User::factory()->create());
        $bebida = $this->bebida();
        ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $bebida->cd_bebida]);

        $bebida->delete();

        $this->assertDatabaseCount('colecao_bebida', 0);
        $this->assertDatabaseCount('colecao', 1);
    }

    public function test_a_mesma_bebida_nao_entra_duas_vezes_na_colecao(): void
    {
        $colecao = $this->colecao(User::factory()->create());
        $bebida = $this->bebida();
        ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $bebida->cd_bebida]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $bebida->cd_bebida]);
    }
}
```

- [ ] **Step 2: Rode e confirme que falha**

Run: `php artisan test --filter=ColecaoModeloTest`
Expected: FAIL com `Class "App\Models\Colecao" not found`.

- [ ] **Step 3: Crie as duas migrations**

`database/migrations/2026_09_14_100000_create_colecao_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listas nomeadas de drinks. O favorito continua sendo o salvar rápido e
 * binário, em tabela própria: coleção é outra coisa, curada e compartilhável.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colecao', function (Blueprint $table) {
            $table->increments('cd_colecao');
            $table->unsignedBigInteger('id_usuario');
            $table->string('nm_colecao', 60);
            $table->string('ds_colecao', 200)->nullable();
            $table->boolean('id_publica')->default(false);
            $table->timestamps();

            $table->foreign('id_usuario')->references('id')->on('users')->onDelete('cascade');

            // Ninguém tem duas "Drinks de verão".
            $table->unique(['id_usuario', 'nm_colecao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colecao');
    }
};
```

`database/migrations/2026_09_14_100100_create_colecao_bebida_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colecao_bebida', function (Blueprint $table) {
            $table->increments('cd_colecao_bebida');
            $table->unsignedInteger('cd_colecao');

            // unsignedInteger, não unsignedBigInteger: bebida.cd_bebida é
            // increments (int4). A tabela favorito declarou bigint e o
            // Postgres aceitou, mas o tipo fica descasado — não copie de lá.
            $table->unsignedInteger('cd_bebida');
            $table->timestamps();

            $table->foreign('cd_colecao')->references('cd_colecao')->on('colecao')->onDelete('cascade');
            $table->foreign('cd_bebida')->references('cd_bebida')->on('bebida')->onDelete('cascade');

            $table->unique(['cd_colecao', 'cd_bebida']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colecao_bebida');
    }
};
```

- [ ] **Step 4: Crie os dois models**

`app/Models/Colecao.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Colecao extends Model
{
    protected $table = 'colecao';

    protected $primaryKey = 'cd_colecao';

    protected $fillable = [
        'id_usuario',
        'nm_colecao',
        'ds_colecao',
        'id_publica',
    ];

    protected $casts = [
        'id_publica' => 'boolean',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function bebidas()
    {
        return $this->belongsToMany(Bebida::class, 'colecao_bebida', 'cd_colecao', 'cd_bebida');
    }

    /**
     * Parte legível da URL, derivada do nome.
     *
     * Não é coluna de propósito: guardada, precisaria ser ressincronizada em
     * todo rename, e slug velho no banco é o defeito que a URL híbrida existe
     * para não ter. Como quem resolve a coleção é o id, recalcular sempre
     * nunca erra.
     */
    public function getSlugAttribute(): string
    {
        return Str::slug($this->nm_colecao);
    }

    /**
     * O parâmetro canônico da rota: "12-drinks-de-verao".
     *
     * Nome sem letra nenhuma ("???") deixa o Str::slug vazio, e aí a URL é só
     * o id — "12-" teria um hífen solto no fim.
     */
    public function parametroUrl(): string
    {
        $slug = $this->slug;

        return $slug === '' ? (string) $this->cd_colecao : $this->cd_colecao.'-'.$slug;
    }

    /**
     * URL absoluta canônica. Depende da rota colecao.show (Tarefa 3).
     */
    public function url(): string
    {
        return route('colecao.show', $this->parametroUrl());
    }
}
```

`app/Models/ColecaoBebida.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColecaoBebida extends Model
{
    protected $table = 'colecao_bebida';

    protected $primaryKey = 'cd_colecao_bebida';

    protected $fillable = [
        'cd_colecao',
        'cd_bebida',
    ];

    public function colecao()
    {
        return $this->belongsTo(Colecao::class, 'cd_colecao');
    }

    public function bebida()
    {
        return $this->belongsTo(Bebida::class, 'cd_bebida');
    }
}
```

- [ ] **Step 5: Rode o teste**

Run: `php artisan test --filter=ColecaoModeloTest`
Expected: PASS, menos `test_o_parametro_da_url_junta_id_e_slug` se ele tocar em `url()` — ele não
toca, usa `parametroUrl()`. Se algum falhar por `Route [colecao.show] not defined`, você chamou
`url()` cedo demais; a rota só nasce na Tarefa 3.

- [ ] **Step 6: Confirme que o rollback desfaz limpo**

Run: `php artisan migrate:reset && php artisan migrate`
Expected: sem erro nos dois sentidos. O `CLAUDE.md` exige isso de toda migration.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app/Models/Colecao.php app/Models/ColecaoBebida.php \
  database/migrations/2026_09_14_1000*.php tests/Feature/ColecaoModeloTest.php
git add app/Models/Colecao.php app/Models/ColecaoBebida.php \
  database/migrations/2026_09_14_1000*.php tests/Feature/ColecaoModeloTest.php
git commit -m "Cria as tabelas e os models de coleção"
```

---

## Task 3: a URL híbrida — rota, show e o 301

O coração da decisão desta feature, e o que quebra mais calado: ninguém percebe um canonical errado
olhando a tela.

**Files:**
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/ColecaoController.php`
- Create: `resources/views/colecao/show.blade.php`
- Test: `tests/Feature/ColecaoUrlTest.php`

**Interfaces:**
- Consumes: `Colecao::parametroUrl()`, `Colecao::url()`, `Colecao::bebidas()` (Tarefa 2).
- Produces:
  - rota nomeada `colecao.show`, parâmetro `{colecao}` restrito a `[0-9]+(-.*)?`;
  - `ColecaoController::show(string $colecao)` — 200 na canônica, 301 em qualquer outra forma,
    404 para id inexistente e para privada de terceiro.

- [ ] **Step 1: Escreva o teste que falha**

Crie `tests/Feature/ColecaoUrlTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Colecao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A URL é híbrida: /colecao/12-drinks-de-verao. O id é quem resolve, o slug é
 * derivado do nome. Qualquer outra forma redireciona 301 para a canônica, e é
 * isso que faz renomear a coleção não quebrar link já publicado.
 *
 * Quebra em silêncio: com o 301 ausente, as quatro formas respondem 200 e o
 * buscador indexa quatro URLs com o mesmo conteúdo.
 */
class ColecaoUrlTest extends TestCase
{
    use RefreshDatabase;

    private function colecaoPublica(string $nome = 'Drinks de verão'): Colecao
    {
        return Colecao::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_colecao' => $nome,
            'id_publica' => true,
        ]);
    }

    public function test_a_forma_canonica_responde_200(): void
    {
        $colecao = $this->colecaoPublica();

        $this->get('/colecao/'.$colecao->cd_colecao.'-drinks-de-verao')
            ->assertOk()
            ->assertSee('Drinks de verão');
    }

    public function test_so_o_id_redireciona_para_a_canonica(): void
    {
        $colecao = $this->colecaoPublica();

        $this->get('/colecao/'.$colecao->cd_colecao)
            ->assertStatus(301)
            ->assertRedirect($colecao->url());
    }

    public function test_slug_antigo_redireciona_para_a_canonica(): void
    {
        $colecao = $this->colecaoPublica();
        $colecao->update(['nm_colecao' => 'Drinks de inverno']);

        $this->get('/colecao/'.$colecao->cd_colecao.'-drinks-de-verao')
            ->assertStatus(301)
            ->assertRedirect(route('colecao.show', $colecao->cd_colecao.'-drinks-de-inverno'));
    }

    public function test_lixo_no_lugar_do_slug_redireciona_para_a_canonica(): void
    {
        $colecao = $this->colecaoPublica();

        $this->get('/colecao/'.$colecao->cd_colecao.'-qualquer-coisa')
            ->assertStatus(301)
            ->assertRedirect($colecao->url());
    }

    public function test_id_inexistente_da_404(): void
    {
        $this->get('/colecao/999999-o-que-for')->assertNotFound();
    }

    public function test_a_canonica_aponta_para_ela_mesma(): void
    {
        $colecao = $this->colecaoPublica();

        $this->get($colecao->url())
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$colecao->url().'">', false);
    }
}
```

- [ ] **Step 2: Rode e confirme que falha**

Run: `php artisan test --filter=ColecaoUrlTest`
Expected: FAIL com `Route [colecao.show] not defined`.

- [ ] **Step 3: Declare a rota**

Em `routes/web.php`, junto das outras rotas públicas (logo abaixo do bloco de `/ingrediente`):

```php
Route::get('/colecao/{colecao}', [ColecaoController::class, 'show'])
    ->name('colecao.show')->where('colecao', '[0-9]+(-.*)?');
```

E o `use App\Http\Controllers\ColecaoController;` no topo, junto dos outros.

- [ ] **Step 4: Escreva o controller**

Crie `app/Http/Controllers/ColecaoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Bebida;
use App\Models\Colecao;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ColecaoController extends Controller
{
    /**
     * Página da coleção.
     *
     * O parâmetro é híbrido: o id manda, o slug é enfeite. Qualquer forma que
     * não seja a canônica sai 301 — id puro, slug de antes do rename, ou lixo
     * no fim. Assim o link publicado continua valendo depois de renomear, sem
     * duplicar conteúdo no índice do buscador.
     */
    public function show(string $colecao)
    {
        // "12-drinks-de-verao" => 12. A restrição da rota garante que começa
        // com dígito, então o cast nunca vira 0 por engano.
        $registro = Colecao::with('usuario')->findOrFail((int) $colecao);

        // 404, e não 403: 403 confirmaria que a coleção existe.
        if (! $registro->id_publica && $registro->id_usuario !== Auth::id()) {
            abort(404);
        }

        if ($colecao !== $registro->parametroUrl()) {
            return redirect()->to($registro->url(), 301);
        }

        $bebidas = Bebida::query()
            ->select(
                'bebida.*',
                DB::raw('COALESCE(ROUND(AVG(avaliacao.id_nota), 1), 0) AS nota'),
                DB::raw('COUNT(avaliacao.id_nota) AS qt_avaliacao'),
                DB::raw('MIN(cb.created_at) AS dt_adicionado')
            )
            ->join('colecao_bebida as cb', 'cb.cd_bebida', '=', 'bebida.cd_bebida')
            ->leftJoin('avaliacao', 'bebida.cd_bebida', '=', 'avaliacao.cd_bebida')
            ->where('cb.cd_colecao', $registro->cd_colecao)
            // Agrupar só pela PK basta no PostgreSQL (dependência funcional),
            // e é o que permite ordenar por MIN(cb.created_at) sem arrastar a
            // coluna para o GROUP BY. O projeto é Postgres only.
            ->groupBy('bebida.cd_bebida')
            ->orderBy('dt_adicionado')
            ->paginate(12);

        return view('colecao.show', ['colecao' => $registro, 'bebidas' => $bebidas]);
    }
}
```

- [ ] **Step 5: Escreva a view**

Crie `resources/views/colecao/show.blade.php`:

```blade
@extends('layouts.app')

@section('titulo', $colecao->nm_colecao)

@section('content')
<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
            <li class="breadcrumb-item">Coleções</li>
            <li class="breadcrumb-item active" aria-current="page">{{ $colecao->nm_colecao }}</li>
        </ol>
    </nav>

    <div class="mb-4">
        <h1 class="h3 mb-1">{{ $colecao->nm_colecao }}</h1>
        @if($colecao->ds_colecao)
            <p class="text-muted mb-1">{{ $colecao->ds_colecao }}</p>
        @endif
        <p class="text-muted small mb-0">
            {{ $bebidas->total() }} {{ $bebidas->total() === 1 ? 'drink' : 'drinks' }}
            · por {{ $colecao->usuario->name }}
            @unless($colecao->id_publica)
                <span class="badge bg-secondary ms-1"><i class="bi bi-lock-fill me-1"></i>Privada</span>
            @endunless
        </p>
    </div>

    @if($bebidas->isEmpty())
        <div class="alert alert-light border text-center py-5">
            <i class="bi bi-collection fs-2 d-block mb-2 text-muted"></i>
            <p class="mb-0">Esta coleção ainda não tem nenhum drink.</p>
        </div>
    @else
        <div class="row">
            @foreach($bebidas as $bebida)
                <div class="col-6 col-md-3 mb-4">
                    <a href="{{ route('bebida.show', $bebida->cd_bebida) }}" class="text-decoration-none text-dark">
                        <div class="card drink-card h-100">
                            <img src="@imagem($bebida->ds_imagem, 400)"
                                 class="card-img-top" alt="{{ $bebida->nm_bebida }}" height="200"
                                 style="object-fit: cover;" loading="lazy">
                            <div class="card-body">
                                <h5 class="card-title h6">{{ $bebida->nm_bebida }}</h5>
                                <p class="card-text text-muted small mb-0">
                                    @if($bebida->qt_avaliacao > 0)
                                        <i class="bi bi-star-fill text-warning"></i> {{ $bebida->nota }}
                                        <span class="ms-1">({{ $bebida->qt_avaliacao }})</span>
                                    @else
                                        Sem avaliações
                                    @endif
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-center">
            {{ $bebidas->links() }}
        </div>
    @endif
</div>
@endsection
```

No breadcrumb, "Coleções" é **texto, não link**: a rota `colecao.index` só nasce na Tarefa 4, e
apontar para ela agora deixaria um 500 no caminho entre as duas tarefas. A Tarefa 4 transforma em
link.

- [ ] **Step 6: Rode o teste**

Run: `php artisan test --filter=ColecaoUrlTest`
Expected: PASS nos seis. O `test_a_canonica_aponta_para_ela_mesma` só passa porque a Tarefa 1 já
está no lugar.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app/Http/Controllers/ColecaoController.php tests/Feature/ColecaoUrlTest.php routes/web.php
git add app/Http/Controllers/ColecaoController.php resources/views/colecao/show.blade.php \
  routes/web.php tests/Feature/ColecaoUrlTest.php
git commit -m "Página da coleção com URL híbrida e 301 para a canônica"
```

---

## Task 4: índice `/colecoes`, visibilidade e meta

**Files:**
- Modify: `app/Http/Controllers/ColecaoController.php`
- Create: `resources/views/colecao/index.blade.php`
- Modify: `resources/views/colecao/show.blade.php`
- Test: `tests/Feature/ColecaoVisibilidadeTest.php`

**Interfaces:**
- Consumes: `ColecaoController::show()` e a rota `colecao.index` (Tarefa 3).
- Produces: `ColecaoController::index()` — públicas com ≥3 bebidas, paginadas de 24,
  `updated_at` desc.

- [ ] **Step 1: Escreva o teste que falha**

Crie `tests/Feature/ColecaoVisibilidadeTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\Colecao;
use App\Models\ColecaoBebida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Índice cheio de página magra é pior que índice menor: é a mesma regra que o
 * /ingredientes já aplica ao deixar ingrediente órfão de fora. A coleção
 * pública com menos de 3 bebidas continua acessível por link, mas sai com
 * noindex.
 *
 * A privada responde 404, e não 403, porque 403 confirmaria que ela existe.
 */
class ColecaoVisibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private function colecaoCom(int $qtBebidas, bool $publica = true, ?User $dono = null): Colecao
    {
        $colecao = Colecao::create([
            'id_usuario' => ($dono ?? User::factory()->create())->id,
            'nm_colecao' => 'Coleção '.uniqid(),
            'id_publica' => $publica,
        ]);

        for ($i = 0; $i < $qtBebidas; $i++) {
            $bebida = Bebida::create([
                'nm_bebida' => 'Drink '.uniqid(),
                'ds_preparo' => 'Misture e sirva.',
                'id_tipo' => TipoBebida::Alcoolica,
                'ds_bebida' => 'Teste.',
            ]);
            ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $bebida->cd_bebida]);
        }

        return $colecao;
    }

    public function test_privada_da_404_para_estranho(): void
    {
        $privada = $this->colecaoCom(3, publica: false);

        $this->actingAs(User::factory()->create())->get($privada->url())->assertNotFound();
        $this->get($privada->url())->assertNotFound();
    }

    public function test_privada_abre_para_o_dono(): void
    {
        $dono = User::factory()->create();
        $privada = $this->colecaoCom(3, publica: false, dono: $dono);

        $this->actingAs($dono)->get($privada->url())->assertOk();
    }

    public function test_indice_lista_so_publica_com_tres_ou_mais(): void
    {
        $cheia = $this->colecaoCom(3);
        $magra = $this->colecaoCom(2);
        $privada = $this->colecaoCom(5, publica: false);

        $resposta = $this->get(route('colecao.index'))->assertOk();

        $resposta->assertSee($cheia->nm_colecao);
        $resposta->assertDontSee($magra->nm_colecao);
        $resposta->assertDontSee($privada->nm_colecao);
    }

    public function test_publica_magra_responde_200_com_noindex(): void
    {
        $magra = $this->colecaoCom(2);

        $this->get($magra->url())
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_publica_cheia_nao_leva_noindex(): void
    {
        $cheia = $this->colecaoCom(3);

        $this->get($cheia->url())
            ->assertOk()
            ->assertDontSee('name="robots"', false);
    }

    public function test_privada_leva_noindex_para_o_dono(): void
    {
        $dono = User::factory()->create();
        $privada = $this->colecaoCom(5, publica: false, dono: $dono);

        $this->actingAs($dono)->get($privada->url())
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false);
    }
}
```

- [ ] **Step 2: Rode e confirme que falha**

Run: `php artisan test --filter=ColecaoVisibilidadeTest`
Expected: FAIL — o índice provisório da Tarefa 3 não lista nada, e a view não emite `robots`.

- [ ] **Step 3: Declare a rota e implemente o índice**

Em `routes/web.php`, junto das outras rotas públicas, imediatamente acima da rota de `colecao.show`:

```php
Route::get('/colecoes', [ColecaoController::class, 'index'])->name('colecao.index');
```

Em `app/Http/Controllers/ColecaoController.php`, a constante no topo da classe:

```php
    /**
     * Mínimo de bebidas para a coleção pública entrar no índice e sair do
     * noindex. Abaixo disso é página magra.
     */
    public const MINIMO_PARA_INDICE = 3;
```

E o método. Note o `has('bebidas', '>=', ...)`, e **não** um `having(COUNT(*))`: o `withCount` é
subconsulta, não agregação de `GROUP BY`, então um `having` ali não enxergaria a contagem.

```php
    /**
     * Índice das coleções públicas.
     *
     * Só entram as com no mínimo 3 bebidas, pela mesma razão que
     * IngredienteController::index deixa ingrediente órfão de fora: índice
     * cheio de página magra é pior que índice menor. A coleção pública magra
     * continua acessível por link direto e pelo perfil do dono.
     */
    public function index()
    {
        $colecoes = Colecao::query()
            ->with('usuario')
            ->withCount('bebidas')
            ->where('id_publica', true)
            ->has('bebidas', '>=', self::MINIMO_PARA_INDICE)
            ->orderByDesc('updated_at')
            ->paginate(24);

        return view('colecao.index', compact('colecoes'));
    }
```

E, agora que a rota existe, transforme o "Coleções" do breadcrumb de
`resources/views/colecao/show.blade.php` em link:

```blade
            <li class="breadcrumb-item"><a href="{{ route('colecao.index') }}">Coleções</a></li>
```

- [ ] **Step 4: Crie a view do índice**

Crie `resources/views/colecao/index.blade.php`:

```blade
@extends('layouts.app')

@section('titulo', 'Coleções')
@section('descricao', 'Listas de drinks montadas pela comunidade do Drinkerito.')

@section('content')
<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
            <li class="breadcrumb-item active" aria-current="page">Coleções</li>
        </ol>
    </nav>

    <h1 class="h3 mb-4">Coleções</h1>

    @if($colecoes->isEmpty())
        <div class="alert alert-light border text-center py-5">
            <i class="bi bi-collection fs-2 d-block mb-2 text-muted"></i>
            <p class="mb-0">Nenhuma coleção pública ainda.</p>
        </div>
    @else
        <div class="row">
            @foreach($colecoes as $colecao)
                <div class="col-12 col-md-6 col-lg-4 mb-4">
                    <a href="{{ $colecao->url() }}" class="text-decoration-none text-dark">
                        <div class="card h-100">
                            <div class="card-body">
                                <h2 class="card-title h5 mb-1">{{ $colecao->nm_colecao }}</h2>
                                <p class="card-text text-muted small mb-2">
                                    {{ $colecao->bebidas_count }} drinks · por {{ $colecao->usuario->name }}
                                </p>
                                @if($colecao->ds_colecao)
                                    <p class="card-text small mb-0">{{ Str::limit($colecao->ds_colecao, 100) }}</p>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-center">
            {{ $colecoes->links() }}
        </div>
    @endif
</div>
@endsection
```

- [ ] **Step 5: Acrescente as seções de meta à view da coleção**

No topo de `resources/views/colecao/show.blade.php`, logo abaixo do `@section('titulo', ...)` que já
está lá:

```blade
@section('descricao', $colecao->ds_colecao
    ?: $bebidas->total().' drinks reunidos na coleção '.$colecao->nm_colecao.', no Drinkerito.')

@if($bebidas->total() > 0 && $bebidas->first()->ds_imagem)
    {{-- Sem transformação: o WhatsApp e o Facebook querem a imagem grande. --}}
    @section('og_imagem', $bebidas->first()->ds_imagem)
@endif

@if(! $colecao->id_publica || $bebidas->total() < \App\Http\Controllers\ColecaoController::MINIMO_PARA_INDICE)
    {{-- Privada nunca vai ao índice; pública magra é conteúdo fino, e o
         tratamento é o mesmo que ingrediente/show dá à página sem receita. --}}
    @section('robots', 'noindex')
@endif
```

- [ ] **Step 6: Rode os testes**

Run: `php artisan test --filter=ColecaoVisibilidadeTest && php artisan test --filter=ColecaoUrlTest`
Expected: PASS nos dois arquivos.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app/Http/Controllers/ColecaoController.php tests/Feature/ColecaoVisibilidadeTest.php
git add app/Http/Controllers/ColecaoController.php resources/views/colecao/ tests/Feature/ColecaoVisibilidadeTest.php
git commit -m "Índice de coleções públicas e regra de visibilidade"
```

---

## Task 5: CRUD no perfil, validação e tradução

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/ColecaoController.php`
- Modify: `app/Http/Controllers/PerfilController.php:17-47`
- Modify: `resources/views/perfil/index.blade.php`
- Modify: `lang/pt_BR/validation.php` (bloco `attributes`)
- Test: `tests/Feature/ColecaoCrudTest.php`

**Interfaces:**
- Consumes: `Colecao`, `ColecaoController::MINIMO_PARA_INDICE`.
- Produces:
  - `ColecaoController::LIMITE_POR_USUARIO = 50`;
  - `store(Request)`, `update(Request, int $cd_colecao)`, `destroy(int $cd_colecao)`;
  - `PerfilController::index()` passa `$colecoes` (com `bebidas_count`) e `$cntColecoes` à view.

- [ ] **Step 1: Escreva o teste que falha**

Crie `tests/Feature/ColecaoCrudTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Colecao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coleção pública é superfície indexável criada por usuário, e superfície
 * indexável sem teto é convite a script — daí o limite por conta.
 *
 * O unique (id_usuario, nm_colecao) é quem garante de verdade que ninguém tem
 * duas listas com o mesmo nome; a regra de validação existe para a pessoa ler
 * uma mensagem em vez de um erro de banco.
 */
class ColecaoCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_colecao(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->post(route('colecao.store'), [
                'nm_colecao' => 'Drinks de verão',
                'ds_colecao' => 'Para os dias quentes.',
                'id_publica' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('colecao', [
            'id_usuario' => $usuario->id,
            'nm_colecao' => 'Drinks de verão',
            'id_publica' => true,
        ]);
    }

    public function test_nome_repetido_do_mesmo_usuario_e_recusado(): void
    {
        $usuario = User::factory()->create();
        Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs($usuario)
            ->post(route('colecao.store'), ['nm_colecao' => 'Drinks de verão'])
            ->assertSessionHasErrors('nm_colecao');

        $this->assertDatabaseCount('colecao', 1);
    }

    public function test_duas_pessoas_podem_ter_o_mesmo_nome(): void
    {
        Colecao::create(['id_usuario' => User::factory()->create()->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs(User::factory()->create())
            ->post(route('colecao.store'), ['nm_colecao' => 'Drinks de verão'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('colecao', 2);
    }

    public function test_recusa_a_colecao_acima_do_limite(): void
    {
        $usuario = User::factory()->create();

        for ($i = 0; $i < 50; $i++) {
            Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Lista '.$i]);
        }

        $this->actingAs($usuario)
            ->post(route('colecao.store'), ['nm_colecao' => 'A quinquagésima primeira'])
            ->assertSessionHasErrors('nm_colecao');

        $this->assertDatabaseCount('colecao', 50);
    }

    public function test_renomear_muda_a_url_canonica(): void
    {
        $usuario = User::factory()->create();
        $colecao = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs($usuario)
            ->put(route('colecao.update', $colecao->cd_colecao), [
                'nm_colecao' => 'Drinks de inverno',
                'id_publica' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(
            $colecao->cd_colecao.'-drinks-de-inverno',
            $colecao->fresh()->parametroUrl()
        );
    }

    public function test_nao_mexe_na_colecao_de_outra_pessoa(): void
    {
        $colecao = Colecao::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_colecao' => 'Drinks de verão',
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('colecao.update', $colecao->cd_colecao), ['nm_colecao' => 'Sequestrada'])
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->delete(route('colecao.destroy', $colecao->cd_colecao))
            ->assertNotFound();

        $this->assertDatabaseHas('colecao', ['nm_colecao' => 'Drinks de verão']);
    }

    public function test_apaga_a_propria_colecao(): void
    {
        $usuario = User::factory()->create();
        $colecao = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs($usuario)
            ->delete(route('colecao.destroy', $colecao->cd_colecao))
            ->assertRedirect();

        $this->assertDatabaseCount('colecao', 0);
    }

    public function test_visitante_nao_cria(): void
    {
        $this->post(route('colecao.store'), ['nm_colecao' => 'Drinks de verão'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('colecao', 0);
    }
}
```

- [ ] **Step 2: Rode e confirme que falha**

Run: `php artisan test --filter=ColecaoCrudTest`
Expected: FAIL com `Route [colecao.store] not defined`.

- [ ] **Step 3: Declare as rotas**

Em `routes/web.php`, dentro do grupo `Route::middleware(['auth'])` que já tem `/profile` e
`/favoritos`:

```php
    Route::post('/colecoes', [ColecaoController::class, 'store'])->name('colecao.store');
    Route::put('/colecao/{cd_colecao}', [ColecaoController::class, 'update'])
        ->name('colecao.update')->whereNumber('cd_colecao');
    Route::delete('/colecao/{cd_colecao}', [ColecaoController::class, 'destroy'])
        ->name('colecao.destroy')->whereNumber('cd_colecao');
```

- [ ] **Step 4: Implemente os três métodos**

Em `app/Http/Controllers/ColecaoController.php`, junto da constante que já existe:

```php
    /**
     * Teto de coleções por conta. Coleção pública é superfície indexável
     * criada por usuário, e superfície indexável sem teto é convite a script.
     */
    public const LIMITE_POR_USUARIO = 50;
```

E os métodos (com `use Illuminate\Http\Request;` e `use Illuminate\Validation\Rule;` no topo):

```php
    public function store(Request $request)
    {
        $dados = $this->validar($request);

        if (Colecao::where('id_usuario', Auth::id())->count() >= self::LIMITE_POR_USUARIO) {
            return back()->withInput()->withErrors([
                'nm_colecao' => 'Você chegou ao limite de '.self::LIMITE_POR_USUARIO.' coleções.',
            ]);
        }

        $colecao = Colecao::create($dados + ['id_usuario' => Auth::id()]);

        return redirect()->to($colecao->url())->with('sucesso', 'Coleção criada.');
    }

    public function update(Request $request, int $cd_colecao)
    {
        $colecao = $this->minhaColecao($cd_colecao);
        $colecao->update($this->validar($request, $colecao));

        return redirect()->to($colecao->fresh()->url())->with('sucesso', 'Coleção atualizada.');
    }

    public function destroy(int $cd_colecao)
    {
        // O cascade de colecao_bebida cuida dos vínculos.
        $this->minhaColecao($cd_colecao)->delete();

        return redirect()->route('perfil.index')->with('sucesso', 'Coleção apagada.');
    }

    /**
     * Carrega a coleção exigindo que seja de quem está autenticado.
     *
     * 404, e não 403, pelo mesmo motivo do show: 403 confirma que existe.
     */
    private function minhaColecao(int $cd_colecao): Colecao
    {
        return Colecao::where('cd_colecao', $cd_colecao)
            ->where('id_usuario', Auth::id())
            ->firstOrFail();
    }

    private function validar(Request $request, ?Colecao $colecao = null): array
    {
        $unico = Rule::unique('colecao', 'nm_colecao')->where('id_usuario', Auth::id());

        if ($colecao) {
            $unico = $unico->ignore($colecao->cd_colecao, 'cd_colecao');
        }

        $dados = $request->validate([
            'nm_colecao' => ['required', 'string', 'max:60', $unico],
            'ds_colecao' => ['nullable', 'string', 'max:200'],
            'id_publica' => ['nullable', 'boolean'],
        ], [
            'nm_colecao.required' => 'Dê um nome à coleção.',
            'nm_colecao.unique' => 'Você já tem uma coleção com esse nome.',
        ]);

        // Checkbox desmarcado não chega no request.
        $dados['id_publica'] = $request->boolean('id_publica');

        return $dados;
    }
```

- [ ] **Step 5: Traduza os nomes das colunas**

Em `lang/pt_BR/validation.php`, no bloco `attributes`, em ordem alfabética junto dos que já estão:

```php
        'ds_colecao' => 'descrição da coleção',
        'id_publica' => 'visibilidade',
        'nm_colecao' => 'nome da coleção',
```

- [ ] **Step 6: Rode o teste**

Run: `php artisan test --filter=ColecaoCrudTest`
Expected: PASS nos oito.

- [ ] **Step 7: Passe as coleções ao perfil**

Em `app/Http/Controllers/PerfilController.php`, no `index()`, depois do `$cntAvaliacoes`:

```php
        $colecoes = Colecao::where('id_usuario', $user->id)
            ->withCount('bebidas')
            ->orderBy('nm_colecao')
            ->get();
```

Troque o `return` para incluir as duas variáveis novas:

```php
        return view('perfil.index', compact(
            'user', 'arrBebida', 'cntFavoritos', 'cntAvaliacoes', 'colecoes'
        ));
```

E o `use App\Models\Colecao;` no topo.

- [ ] **Step 8: Mostre as coleções no perfil**

Em `resources/views/perfil/index.blade.php`, no card de estatísticas, logo abaixo da linha de
"Receitas" (por volta da linha 40):

```blade
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted"><i class="bi bi-collection me-2"></i>Coleções</span>
                        <span class="badge bg-primary rounded-pill">{{ $colecoes->count() }}</span>
                    </div>
```

E, abaixo da seção "Minhas Receitas" (depois do `@endif`/`</div>` que a fecha, por volta da linha
115), a seção nova:

```blade
                <h3 class="mb-3 mt-5">Minhas Coleções</h3>

                <button class="btn btn-sm btn-outline-primary mb-3"
                        data-bs-toggle="modal" data-bs-target="#novaColecaoModal">
                    <i class="bi bi-plus-lg me-1"></i>Nova coleção
                </button>

                @forelse($colecoes as $colecao)
                    <div class="card mb-2">
                        <div class="card-body d-flex justify-content-between align-items-center py-2">
                            <div>
                                <a href="{{ $colecao->url() }}" class="text-decoration-none">
                                    {{ $colecao->nm_colecao }}
                                </a>
                                <span class="text-muted small ms-2">{{ $colecao->bebidas_count }} drinks</span>
                                @if($colecao->id_publica)
                                    <span class="badge bg-success ms-1">Pública</span>
                                @else
                                    <span class="badge bg-secondary ms-1">Privada</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('colecao.destroy', $colecao->cd_colecao) }}"
                                  onsubmit="return confirm('Apagar a coleção {{ $colecao->nm_colecao }}?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-muted">Nenhuma coleção ainda.</p>
                @endforelse

                <div class="modal fade" id="novaColecaoModal" tabindex="-1">
                    <div class="modal-dialog">
                        <form class="modal-content" method="POST" action="{{ route('colecao.store') }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Nova coleção</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label" for="nm_colecao">Nome</label>
                                    <input class="form-control" id="nm_colecao" name="nm_colecao"
                                           maxlength="60" required value="{{ old('nm_colecao') }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="ds_colecao">Descrição (opcional)</label>
                                    <input class="form-control" id="ds_colecao" name="ds_colecao"
                                           maxlength="200" value="{{ old('ds_colecao') }}">
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1"
                                           id="id_publica" name="id_publica">
                                    <label class="form-check-label" for="id_publica">
                                        Pública — qualquer pessoa com o link pode ver
                                    </label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-primary" type="submit">Criar</button>
                            </div>
                        </form>
                    </div>
                </div>
```

- [ ] **Step 9: Confirme que o perfil não quebrou**

Run: `php artisan test --filter=PerfilConsultaTest && php artisan test --filter=PerfilEdicaoTest`
Expected: PASS nos dois.

- [ ] **Step 10: Commit**

```bash
vendor/bin/pint app/Http/Controllers/ColecaoController.php app/Http/Controllers/PerfilController.php \
  tests/Feature/ColecaoCrudTest.php lang/pt_BR/validation.php
git add app/Http/Controllers/ColecaoController.php app/Http/Controllers/PerfilController.php \
  resources/views/perfil/index.blade.php lang/pt_BR/validation.php routes/web.php \
  tests/Feature/ColecaoCrudTest.php
git commit -m "CRUD de coleções no perfil"
```

---

## Task 6: adicionar drink à coleção — endpoints, modal e JS

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/ColecaoController.php`
- Create: `resources/views/partials/modal-colecao.blade.php`
- Create: `public/js/colecao.js`
- Modify: `resources/views/bebida/show.blade.php:54-64`
- Test: `tests/Feature/ColecaoBebidaTest.php`

**Interfaces:**
- Consumes: `Colecao`, `ColecaoBebida`, `minhaColecao()` (Tarefa 5).
- Produces:
  - `GET /colecoes/para-bebida/{cd_bebida}` → `{"colecoes":[{"cd_colecao":1,"nm_colecao":"...","contem":true}]}`
  - `POST /colecao/{cd_colecao}/bebida/{cd_bebida}/alternar` → `{"success":true,"contem":false,"message":"..."}`

- [ ] **Step 1: Escreva o teste que falha**

Crie `tests/Feature/ColecaoBebidaTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\Colecao;
use App\Models\ColecaoBebida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O modal é chamado por fetch e espera JSON. Um id que não existe não pode
 * virar violação de chave estrangeira — é o mesmo defeito que o FavoritoTest
 * já cobre para a estrela: o front recebe página de erro onde espera JSON e
 * trava sem dizer nada.
 */
class ColecaoBebidaTest extends TestCase
{
    use RefreshDatabase;

    private function bebida(string $nome = 'Mojito'): Bebida
    {
        return Bebida::create([
            'nm_bebida' => $nome,
            'ds_preparo' => 'Macere e complete com rum.',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Cubano.',
        ]);
    }

    public function test_alterna_a_bebida_nos_dois_sentidos(): void
    {
        $usuario = User::factory()->create();
        $colecao = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);
        $bebida = $this->bebida();

        $this->actingAs($usuario)
            ->postJson(route('colecao.bebida.alternar', [$colecao->cd_colecao, $bebida->cd_bebida]))
            ->assertOk()
            ->assertJson(['contem' => true]);

        $this->assertDatabaseCount('colecao_bebida', 1);

        $this->actingAs($usuario)
            ->postJson(route('colecao.bebida.alternar', [$colecao->cd_colecao, $bebida->cd_bebida]))
            ->assertOk()
            ->assertJson(['contem' => false]);

        $this->assertDatabaseCount('colecao_bebida', 0);
    }

    public function test_bebida_inexistente_devolve_404_em_json(): void
    {
        $usuario = User::factory()->create();
        $colecao = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs($usuario)
            ->postJson(route('colecao.bebida.alternar', [$colecao->cd_colecao, 999999]))
            ->assertNotFound()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('colecao_bebida', 0);
    }

    public function test_nao_escreve_na_colecao_de_outra_pessoa(): void
    {
        $colecao = Colecao::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_colecao' => 'Drinks de verão',
        ]);
        $bebida = $this->bebida();

        $this->actingAs(User::factory()->create())
            ->postJson(route('colecao.bebida.alternar', [$colecao->cd_colecao, $bebida->cd_bebida]))
            ->assertNotFound();

        $this->assertDatabaseCount('colecao_bebida', 0);
    }

    public function test_lista_as_colecoes_marcando_quais_contem_a_bebida(): void
    {
        $usuario = User::factory()->create();
        $bebida = $this->bebida();
        $com = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Com o drink']);
        $sem = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Sem o drink']);
        ColecaoBebida::create(['cd_colecao' => $com->cd_colecao, 'cd_bebida' => $bebida->cd_bebida]);

        $this->actingAs($usuario)
            ->getJson(route('colecao.para-bebida', $bebida->cd_bebida))
            ->assertOk()
            ->assertJsonCount(2, 'colecoes')
            ->assertJsonFragment(['cd_colecao' => $com->cd_colecao, 'nm_colecao' => 'Com o drink', 'contem' => true])
            ->assertJsonFragment(['cd_colecao' => $sem->cd_colecao, 'nm_colecao' => 'Sem o drink', 'contem' => false]);
    }

    public function test_visitante_nao_alterna(): void
    {
        $colecao = Colecao::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_colecao' => 'Drinks de verão',
        ]);

        $this->postJson(route('colecao.bebida.alternar', [$colecao->cd_colecao, $this->bebida()->cd_bebida]))
            ->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Rode e confirme que falha**

Run: `php artisan test --filter=ColecaoBebidaTest`
Expected: FAIL com `Route [colecao.bebida.alternar] not defined`.

- [ ] **Step 3: Declare as rotas**

Em `routes/web.php`, junto das outras rotas de coleção sob `auth`:

```php
    Route::get('/colecoes/para-bebida/{cd_bebida}', [ColecaoController::class, 'paraBebida'])
        ->name('colecao.para-bebida')->whereNumber('cd_bebida');
    Route::post('/colecao/{cd_colecao}/bebida/{cd_bebida}/alternar', [ColecaoController::class, 'alternarBebida'])
        ->name('colecao.bebida.alternar')->whereNumber('cd_colecao')->whereNumber('cd_bebida');
```

- [ ] **Step 4: Implemente os dois métodos**

Em `app/Http/Controllers/ColecaoController.php` (precisa de `use App\Models\ColecaoBebida;`):

```php
    /**
     * As coleções de quem está autenticado, marcando quais já têm a bebida.
     * É o que o modal lê ao abrir.
     */
    public function paraBebida(int $cd_bebida)
    {
        Bebida::findOrFail($cd_bebida);

        $colecoes = Colecao::where('id_usuario', Auth::id())
            ->orderBy('nm_colecao')
            ->get()
            ->map(fn (Colecao $colecao) => [
                'cd_colecao' => $colecao->cd_colecao,
                'nm_colecao' => $colecao->nm_colecao,
                'contem' => ColecaoBebida::where('cd_colecao', $colecao->cd_colecao)
                    ->where('cd_bebida', $cd_bebida)
                    ->exists(),
            ]);

        return response()->json(['colecoes' => $colecoes]);
    }

    public function alternarBebida(int $cd_colecao, int $cd_bebida)
    {
        $colecao = $this->minhaColecao($cd_colecao);

        // Sem isso um id inexistente vira violação de chave estrangeira, e o
        // modal, que espera JSON, recebe uma página de erro.
        Bebida::findOrFail($cd_bebida);

        $vinculo = ColecaoBebida::where('cd_colecao', $colecao->cd_colecao)
            ->where('cd_bebida', $cd_bebida)
            ->first();

        if ($vinculo) {
            $vinculo->delete();

            return response()->json([
                'success' => true,
                'contem' => false,
                'message' => 'Removido de '.$colecao->nm_colecao,
            ]);
        }

        ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $cd_bebida]);

        // O updated_at ordena o índice /colecoes; sem o touch, a coleção que
        // acabou de ganhar drink não sobe.
        $colecao->touch();

        return response()->json([
            'success' => true,
            'contem' => true,
            'message' => 'Adicionado a '.$colecao->nm_colecao,
        ]);
    }
```

- [ ] **Step 5: Rode o teste**

Run: `php artisan test --filter=ColecaoBebidaTest`
Expected: PASS nos cinco.

- [ ] **Step 6: Commit do backend**

```bash
vendor/bin/pint app/Http/Controllers/ColecaoController.php tests/Feature/ColecaoBebidaTest.php
git add app/Http/Controllers/ColecaoController.php routes/web.php tests/Feature/ColecaoBebidaTest.php
git commit -m "Endpoints de adicionar e remover drink da coleção"
```

- [ ] **Step 7: Crie o partial do modal**

Crie `resources/views/partials/modal-colecao.blade.php`:

```blade
{{--
    Modal de "adicionar a coleção", incluído pela página da bebida.

    A lista vem por fetch em vez de vir renderizada: a página da bebida é
    pública e cacheável, e as coleções são de quem está olhando.
--}}
<div class="modal fade" id="colecaoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar a uma coleção</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="colecaoLista" class="mb-3">
                    <p class="text-muted mb-0">Carregando...</p>
                </div>

                <hr>

                <label class="form-label" for="colecaoNova">Nova coleção</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="colecaoNova"
                           maxlength="60" placeholder="Drinks de verão">
                    <button class="btn btn-primary" type="button" id="colecaoCriarBtn">Criar e adicionar</button>
                </div>
                <div class="form-text" id="colecaoErro"></div>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 8: Escreva o JS**

Crie `public/js/colecao.js`:

```javascript
/**
 * Modal de "adicionar a coleção" da página da bebida.
 *
 * A lista chega por fetch: a página é pública, e as coleções são de quem está
 * olhando. O escapeHtml vem do window.Drinkerito, carregado pelo layout — os
 * nomes de coleção são texto livre do usuário.
 */
(function () {
    'use strict';

    const config = window.DrinkeritoColecao;
    if (!config) return;

    const lista = document.getElementById('colecaoLista');
    const erro = document.getElementById('colecaoErro');
    const campoNovo = document.getElementById('colecaoNova');
    const modal = document.getElementById('colecaoModal');

    function cabecalho() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': config.csrfToken,
        };
    }

    function desenhar(colecoes) {
        if (!colecoes.length) {
            lista.innerHTML = '<p class="text-muted mb-0">Você ainda não tem nenhuma coleção.</p>';
            return;
        }

        lista.innerHTML = colecoes.map(function (colecao) {
            const marcado = colecao.contem ? ' checked' : '';
            return '<div class="form-check">'
                + '<input class="form-check-input js-colecao" type="checkbox" value="'
                + colecao.cd_colecao + '" id="colecao-' + colecao.cd_colecao + '"' + marcado + '>'
                + '<label class="form-check-label" for="colecao-' + colecao.cd_colecao + '">'
                + window.Drinkerito.escapeHtml(colecao.nm_colecao)
                + '</label></div>';
        }).join('');

        lista.querySelectorAll('.js-colecao').forEach(function (caixa) {
            caixa.addEventListener('change', function () {
                alternar(caixa.value, caixa);
            });
        });
    }

    function carregar() {
        erro.textContent = '';
        lista.innerHTML = '<p class="text-muted mb-0">Carregando...</p>';

        fetch(config.paraBebidaUrl, { headers: cabecalho() })
            .then(function (r) { return r.json(); })
            .then(function (dados) { desenhar(dados.colecoes); })
            .catch(function () {
                lista.innerHTML = '<p class="text-danger mb-0">Não foi possível carregar suas coleções.</p>';
            });
    }

    function alternar(cdColecao, caixa) {
        const url = config.alternarUrl.replace('__CD__', cdColecao);

        fetch(url, { method: 'POST', headers: cabecalho() })
            .then(function (r) {
                if (!r.ok) throw new Error('falhou');
                return r.json();
            })
            .then(function (dados) {
                caixa.checked = dados.contem;
                erro.textContent = dados.message;
            })
            .catch(function () {
                // Devolve a caixa ao estado de antes: deixá-la marcada mentiria
                // sobre o que está salvo.
                caixa.checked = !caixa.checked;
                erro.textContent = 'Não foi possível salvar. Tente de novo.';
            });
    }

    function criar() {
        const nome = campoNovo.value.trim();
        if (!nome) return;

        erro.textContent = '';

        fetch(config.criarUrl, {
            method: 'POST',
            headers: cabecalho(),
            body: JSON.stringify({ nm_colecao: nome, cd_bebida: config.cdBebida }),
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, dados: d }; }); })
            .then(function (resposta) {
                if (!resposta.ok) {
                    erro.textContent = resposta.dados.message || 'Não foi possível criar a coleção.';
                    return;
                }
                campoNovo.value = '';
                carregar();
            })
            .catch(function () {
                erro.textContent = 'Não foi possível criar a coleção.';
            });
    }

    document.getElementById('colecaoCriarBtn').addEventListener('click', criar);
    modal.addEventListener('show.bs.modal', carregar);
})();
```

- [ ] **Step 9: Faça o `store` responder JSON e já vincular a bebida**

O botão "Criar e adicionar" precisa das duas coisas numa chamada. Em
`app/Http/Controllers/ColecaoController.php`, troque o corpo do `store` por:

```php
    public function store(Request $request)
    {
        $dados = $this->validar($request);

        if (Colecao::where('id_usuario', Auth::id())->count() >= self::LIMITE_POR_USUARIO) {
            $mensagem = 'Você chegou ao limite de '.self::LIMITE_POR_USUARIO.' coleções.';

            return $request->expectsJson()
                ? response()->json(['message' => $mensagem], 422)
                : back()->withInput()->withErrors(['nm_colecao' => $mensagem]);
        }

        $colecao = Colecao::create($dados + ['id_usuario' => Auth::id()]);

        // O modal da página da bebida cria e já adiciona numa tacada.
        if ($cd_bebida = $request->integer('cd_bebida')) {
            Bebida::findOrFail($cd_bebida);
            ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $cd_bebida]);
        }

        return $request->expectsJson()
            ? response()->json(['cd_colecao' => $colecao->cd_colecao])
            : redirect()->to($colecao->url())->with('sucesso', 'Coleção criada.');
    }
```

Acrescente a `tests/Feature/ColecaoCrudTest.php`:

```php
    public function test_cria_por_json_ja_vinculando_a_bebida(): void
    {
        $usuario = User::factory()->create();
        $bebida = \App\Models\Bebida::create([
            'nm_bebida' => 'Mojito',
            'ds_preparo' => 'Macere e complete com rum.',
            'id_tipo' => \App\Enums\TipoBebida::Alcoolica,
            'ds_bebida' => 'Cubano.',
        ]);

        $this->actingAs($usuario)
            ->postJson(route('colecao.store'), [
                'nm_colecao' => 'Drinks de verão',
                'cd_bebida' => $bebida->cd_bebida,
            ])
            ->assertOk()
            ->assertJsonStructure(['cd_colecao']);

        $this->assertDatabaseCount('colecao_bebida', 1);
    }
```

- [ ] **Step 10: Ligue o modal à página da bebida**

Em `resources/views/bebida/show.blade.php`, dentro do bloco `@auth` que já tem o botão de favoritar
(linhas 54-64), logo depois do `</button>` do `favoriteBtn`:

```blade
                            <button class="btn btn-outline-primary me-2"
                                    data-bs-toggle="modal" data-bs-target="#colecaoModal">
                                <i class="bi bi-collection me-1"></i> Adicionar a coleção
                            </button>
```

E, no fim da `@section('content')`, antes do `@endsection`:

```blade
@auth
    @include('partials.modal-colecao')

    <script>
        window.DrinkeritoColecao = {
            csrfToken: '{{ csrf_token() }}',
            cdBebida: {{ $bebida->cd_bebida }},
            paraBebidaUrl: '{{ route("colecao.para-bebida", $bebida->cd_bebida) }}',
            alternarUrl: '{{ route("colecao.bebida.alternar", ["__CD__", $bebida->cd_bebida]) }}',
            criarUrl: '{{ route("colecao.store") }}',
        };
    </script>
    @js('colecao.js')
@endauth
```

O `__CD__` é um marcador que o JS troca pelo id da coleção — `route()` precisa de um valor para
gerar a URL, e este não é numérico de propósito, para não passar por engano.

**Não extraia o JS de favoritar que já está inline nesta view.** É o rabo do QA-03, não é escopo
deste item, e misturar as duas coisas esconde o que este commit fez.

- [ ] **Step 11: Rode a suíte inteira**

Run: `composer test`
Expected: PASS. Se `AssetsJsTest` reclamar, ele confere que todo `@js` aponta para arquivo que
existe — confirme que `public/js/colecao.js` foi criado.

- [ ] **Step 12: Verifique na tela, não só na suíte**

Neste projeto suíte verde já escondeu defeito. Com `composer dev` rodando:

1. Abra uma bebida logado, clique em "Adicionar a coleção", crie uma e marque/desmarque.
2. Abra `/colecao/1` e confirme o 301 para `/colecao/1-<slug>` (`curl -I`).
3. Veja o `<link rel="canonical">` no HTML da página da coleção (`curl -s ... | grep canonical`).
4. Renomeie a coleção no perfil e confirme que o link antigo ainda chega nela.
5. `php artisan migrate:reset && php artisan migrate --seed` — os dois `down()` novos desfazem limpo.

- [ ] **Step 13: Commit**

```bash
vendor/bin/pint app/Http/Controllers/ColecaoController.php tests/Feature/ColecaoCrudTest.php
git add app/Http/Controllers/ColecaoController.php resources/views/partials/modal-colecao.blade.php \
  resources/views/bebida/show.blade.php public/js/colecao.js tests/Feature/ColecaoCrudTest.php
git commit -m "Modal de adicionar drink a uma coleção"
```

---

## Task 7: fechar o item no backlog

**Files:**
- Modify: `docs/proximos-passos.md`

- [ ] **Step 1: Mova o FEAT-08 para "O que já foi feito"**

Em `docs/proximos-passos.md`, quatro edições:

1. No cabeçalho, troque `Restam **9 itens**` por `Restam **8 itens**`.
2. Na tabela "O que já foi feito", acrescente ao fim:

```markdown
| (este) | **FEAT-08** — coleções de drinks, com URL híbrida e índice público |
```

3. Em "Ordem sugerida", apague o item 3 inteiro (o parágrafo que começa com
   `3. **FEAT-08 (coleções de drinks)**`) e renumere o item seguinte.
4. Apague a seção `### FEAT-08 · Coleções de drinks` inteira, até a linha antes de
   `### FEAT-09`, e troque o título da seção `## Funcionalidades (6)` por
   `## Funcionalidades (5)`.

- [ ] **Step 2: Commit**

```bash
git add docs/proximos-passos.md
git commit -m "Fecha o FEAT-08 no backlog"
```
