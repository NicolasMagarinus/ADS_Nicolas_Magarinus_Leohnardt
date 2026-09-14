# FEAT-08 — Coleções de drinks

**Data:** 14/set/2026 · **Status:** desenho aprovado, implementação não iniciada

O desenho tinha sido começado e interrompido antes do código. Este documento fecha as
perguntas que estavam em aberto no `docs/proximos-passos.md` e serve de base para o plano
de implementação.

---

## Por que

Favorito é binário: a pessoa marca ou não marca. "Drinks de verão", "Para a festa de
sábado" — listas nomeadas são o que transforma um favorito num objeto que se compartilha.
Coleção pública com URL própria é conteúdo indexável gerado pelo usuário, que é o retorno
que justifica o item.

---

## Decisões

### 1. A URL é híbrida: `/colecao/{id}-{slug}`

`/colecao/12-drinks-de-verao`. O **id é o que resolve**; o slug é decorativo.

O projeto hoje é estritamente numérico (`/bebida/{cd}`, `/ingrediente/{cd}`, todos com
`whereNumber`) e não usa `Str::slug` em lugar nenhum. O slug puro daria a melhor URL para
busca, mas transforma o renomear numa porta de mão única — ou o link publicado morre, ou
nasce uma tabela de redirects — e obriga a inventar regra de colisão para duas pessoas com
"Drinks de verão". O numérico puro não custa nada e não entrega nada: a justificativa do
item é SEO.

O híbrido fica com os dois: palavras na URL, e o link antigo nunca quebra porque o id
continua resolvendo. Todas estas formas respondem **301 para a forma canônica**:

| Entrada | Resultado |
|---|---|
| `/colecao/12-drinks-de-verao` (canônica) | 200 |
| `/colecao/12` | 301 → canônica |
| `/colecao/12-nome-antes-do-rename` | 301 → canônica |
| `/colecao/12-qualquer-lixo` | 301 → canônica |
| `/colecao/999-o-que-for` (não existe) | 404 |

Restrição de rota: `->where('colecao', '[0-9]+(-.*)?')`.

### 2. O slug **não** é coluna

É derivado de `nm_colecao` com `Str::slug()` na hora de montar a URL. Guardar o slug criaria
a obrigação de ressincronizá-lo com o nome em todo `update`, e slug velho no banco é
exatamente o defeito que a forma híbrida existe para não ter. Como quem resolve é o id, o
slug pode ser recalculado sempre e nunca fica errado.

### 3. Favoritos e coleções **convivem**, independentes

A estrela continua sendo o salvar rápido e binário, na tabela `favorito`. Coleção é outra
coisa: lista nomeada e curada. Uma bebida pode estar nas duas, numa só ou em nenhuma.

Absorver os favoritos numa coleção padrão daria um conceito só para o usuário, mas `favorito`
é lido por **13 arquivos**, incluindo dois que não são tela: `RecomendadasController` (top-5
ingredientes dos favoritos → drinks parecidos) e `HomeController` (a checagem por usuário que
fica fora do cache de propósito). Não vale arriscar código que funciona para economizar um
botão. Como estão separados, o FEAT-08 é enviável sozinho e **nenhum dos 13 arquivos é
tocado**.

O custo aceito: a página da bebida passa a ter dois jeitos de salvar, a estrela e "adicionar
a...".

### 4. Descoberta: índice só com coleção pública de ≥3 bebidas

`/colecoes` lista as públicas com no mínimo 3 bebidas. É a mesma regra que
`IngredienteController::index` já aplica ao excluir ingrediente órfão: índice cheio de página
magra é pior que índice menor.

Dois estados só, pública ou privada — **não** entra o "não listada". A pública com menos de 3
bebidas continua acessível por link direto e pelo perfil do dono, mas sai com
`robots=noindex`, o mesmo tratamento que `ingrediente/show.blade.php` dá à página sem receita
nenhuma.

Privada responde **404** para quem não é o dono, não 403: 403 confirma que a coleção existe.

### 5. Limite de 50 coleções por usuário

Uma constante e uma linha de validação. Não é sobre o catálogo de hoje ter 50 bebidas — é que
coleção pública é superfície indexável criada por usuário, e superfície indexável sem teto é
convite a script. Sem limite de bebidas por coleção.

---

## Modelo de dados

No estilo do `usuario_ingrediente` (prefixos húngaros do projeto: `cd_` chave, `nm_` nome,
`ds_` texto, `id_` flag).

```
colecao
  cd_colecao    increments               PK
  id_usuario    unsignedBigInteger       FK users.id, onDelete cascade
  nm_colecao    string(60)
  ds_colecao    string(200) nullable     -- uma linha de contexto, opcional
  id_publica    boolean default false    -- flag, como users.id_admin
  timestamps
  unique(id_usuario, nm_colecao)         -- ninguém tem duas "Drinks de verão"

colecao_bebida
  cd_colecao_bebida  increments          PK
  cd_colecao         unsignedInteger     FK colecao.cd_colecao, onDelete cascade
  cd_bebida          unsignedInteger     FK bebida.cd_bebida,   onDelete cascade
  timestamps
  unique(cd_colecao, cd_bebida)
```

**Atenção ao tipo do FK.** `bebida.cd_bebida` é `increments` (int4). A tabela `favorito`
declarou `unsignedBigInteger('cd_bebida')` — o Postgres aceita, mas é tipo descasado. Copiar
a forma do `favorito` repete o erro: aqui é `unsignedInteger`.

Sem coluna de ordenação: a lista sai por `created_at`, como o `/favoritos` já faz. Reordenar
à mão é arrastar-e-soltar, que não está no enunciado.

Os dois `down()` são `dropIfExists`, e `php artisan migrate:reset` tem de desfazer limpo —
requisito do CLAUDE.md para toda migration.

**Models:** como as PKs não são `id`, `Colecao` e `ColecaoBebida` declaram `$table` e
`$primaryKey`, e as relações passam a chave estrangeira na mão
(`hasMany(ColecaoBebida::class, 'cd_colecao')`).

---

## Rotas

```php
// Públicas
Route::get('/colecoes', [ColecaoController::class, 'index'])->name('colecao.index');
Route::get('/colecao/{colecao}', [ColecaoController::class, 'show'])
    ->name('colecao.show')->where('colecao', '[0-9]+(-.*)?');

// Sob auth
Route::post('/colecoes', [ColecaoController::class, 'store'])->name('colecao.store');
Route::put('/colecao/{cd_colecao}', [ColecaoController::class, 'update'])->name('colecao.update');
Route::delete('/colecao/{cd_colecao}', [ColecaoController::class, 'destroy'])->name('colecao.destroy');
Route::post('/colecao/{cd_colecao}/bebida/{cd_bebida}/alternar',
    [ColecaoController::class, 'alternarBebida'])->name('colecao.bebida.alternar');
Route::get('/colecoes/para-bebida/{cd_bebida}',
    [ColecaoController::class, 'paraBebida'])->name('colecao.para-bebida');
```

`show` extrai `(int)` do começo do parâmetro, carrega pelo id, monta a URL canônica e
redireciona 301 se o que veio não bate.

`paraBebida` devolve as coleções da pessoa com a marca de quais já contêm aquela bebida — é o
que o modal lê ao abrir.

---

## Controllers

`ColecaoController`, no estilo Eloquent paginado do `IngredienteController` — **não** no SQL
cru das telas de leitura pesada. `FavoritoController::alternar` é o modelo do endpoint JSON:
mesmo formato de resposta, e `findOrFail` antes de escrever, para que um id inexistente vire
404 em JSON e não violação de FK numa página de erro.

`alternarBebida`, `update` e `destroy` conferem que a coleção é do usuário autenticado.

---

## Validação

`nm_colecao` obrigatório, máximo 60, único por usuário (a regra de validação existe para dar
mensagem decente; quem garante de verdade é o `unique` da tabela). `ds_colecao` opcional,
máximo 200. `id_publica` booleano. A criação recusa a 51ª coleção.

O CLAUDE.md exige o passo que é fácil esquecer: **`lang/pt_BR/validation.php` ganha
`nm_colecao`, `ds_colecao` e `id_publica` no bloco `attributes`**, senão `:attribute` renderiza
como "nm colecao" na tela.

---

## Telas

**Página da bebida** (`resources/views/bebida/show.blade.php`) — terceiro botão ao lado de
Favoritar e Compartilhar, seguindo o `@auth` / `@else` que já está no arquivo (linhas 54-64):
autenticado abre o modal, visitante vai para o login. O modal lista as coleções com checkbox e
traz "Nova coleção" no rodapé, criando e já adicionando numa tacada.

**Perfil** (`resources/views/perfil/index.blade.php`) — contador "Coleções" no card de
estatísticas, ao lado de Favoritos e Receitas, e uma seção "Minhas Coleções" abaixo de "Minhas
Receitas": nome, quantidade de bebidas, badge pública/privada. É daqui que se cria, renomeia,
troca a visibilidade e apaga. Não há tela de CRUD separada: o perfil já é o lugar das coisas
da pessoa.

**`/colecoes`** — paginado de 24 como o `/ingredientes`, ordenado por `updated_at` desc.

**`/colecao/{id}-{slug}`** — cards das bebidas, autor, descrição, e o botão de compartilhar.

**JavaScript:** o código novo vai para `public/js/colecao.js` via `@js`, com URLs e CSRF num
objeto de config inline, como manda o CLAUDE.md. O JS de favoritar que já está inline nessa
view **não** é extraído: é o rabo do QA-03, não é escopo deste item, e misturar as duas coisas
esconde o que o commit fez.

---

## Meta e SEO

A `colecao.show` declara `titulo` (nome da coleção), `descricao` (`ds_colecao`, ou uma frase
gerada com o número de bebidas), `og_imagem` (imagem da primeira bebida, **sem transformação**
— o CLAUDE.md exige `og:image` no tamanho grande) e `robots=noindex` quando é pública com menos
de 3 bebidas ou quando é privada. Todas na **forma inline** `@section('nome', $valor)`, pela
regra de escape do `partials/meta.blade.php`.

**Dependência fora do FEAT-08:** a URL híbrida exige `<link rel="canonical">`, que
`partials/meta.blade.php` **não emite hoje**. Ele passa a valer para o site inteiro, não só
para coleções — o que é bom, mas é mudança global e merece **commit próprio**, antes do resto.

---

## Testes

Seguindo o critério estreito do projeto: o que custa dinheiro, corrompe catálogo ou quebra
calado.

- **`ColecaoUrlTest`** — `/colecao/12`, `/colecao/12-nome-velho` e `/colecao/12-lixo` dão 301
  para a canônica, e a canônica dá 200; id inexistente dá 404. É a decisão central deste
  desenho e quebra em silêncio: ninguém percebe um canonical errado olhando a tela.
- **`ColecaoVisibilidadeTest`** — privada dá 404 para estranho e 200 para o dono; pública dá
  200 para qualquer um; o índice não lista a de 2 bebidas; a pública magra responde 200 com
  `noindex`.
- **`ColecaoBebidaTest`** — adicionar duas vezes não duplica (o unique); remover funciona;
  adicionar na coleção de outra pessoa é recusado; apagar o usuário leva as coleções junto e
  apagar a bebida a tira das coleções (as duas cascatas).

**Verificação além da suíte**, porque neste projeto suíte verde já escondeu defeito: abrir as
telas com `php artisan serve` e conferir o canonical no HTML renderizado, e rodar
`php artisan migrate:reset` para confirmar que os dois `down()` desfazem limpo.

---

## Fora de escopo

Reordenar bebidas na coleção (arrastar-e-soltar), capa de coleção, colaboração entre usuários,
e o perfil público `/u/{id}` — esse é o FEAT-11.
