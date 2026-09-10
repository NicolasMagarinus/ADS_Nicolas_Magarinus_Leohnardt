# Próximos passos

Pendências levantadas na varredura de 10/set/2026. São **28 itens**, nenhum de severidade alta —
os altos foram todos fechados. Cada um traz o arquivo e a linha onde mexer.

---

## Antes de tudo: montar o ambiente noutra máquina

O `.env` não vai no Git, e o banco de desenvolvimento é um container que existe só na máquina onde
foi criado. Numa máquina nova, nada disso está montado.

### 1. Subir o Postgres

```bash
docker run -d --name drinkerito-db -p 5434:5432 \
  -e POSTGRES_PASSWORD=drinkerito -e POSTGRES_USER=drinkerito -e POSTGRES_DB=drinkerito \
  postgres:17-alpine

docker exec drinkerito-db psql -U drinkerito -d drinkerito \
  -c "CREATE DATABASE drinkerito_test OWNER drinkerito;"
```

A imagem é `postgres:17` de propósito: é a mesma versão do Railway, e as consultas usam recursos
específicos do PostgreSQL.

### 2. Criar o `.env`

Copie de uma máquina onde ele já exista, ou monte a partir daqui. Estas são as chaves que importam
para rodar localmente:

```dotenv
APP_NAME=Drinkerito
APP_ENV=local
APP_KEY=                      # php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5434
DB_DATABASE=drinkerito
DB_USERNAME=drinkerito
DB_PASSWORD=drinkerito

# O mailer 'log' escreve em nivel debug: com LOG_LEVEL=info o e-mail
# nunca aparece em storage/logs, e voce fica sem entender por que.
MAIL_MAILER=log
MAIL_FROM_ADDRESS="nao-responda@drinkerito.test"
MAIL_FROM_NAME="${APP_NAME}"
LOG_LEVEL=debug

OPENAI_API_KEY=               # so para o chatbot e os comandos de IA
OPENAI_MODEL=gpt-4o-mini
CLOUDINARY_URL=               # so para upload de imagem
GOOGLE_CLIENT_ID=             # so para login com Google
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
```

Transformar isso num `.env.example` de verdade é o item **SEC-05** abaixo.

### 3. Instalar e migrar

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan test          # 32 testes devem passar
php artisan serve
```

### 4. Popular o catálogo (opcional)

O banco local sobe vazio. Para trazer os dados do Railway:

```bash
PGPASSWORD='<senha do Railway>' pg_dump -h <host>.proxy.rlwy.net -p <porta> \
  -U postgres -d railway --no-owner --no-acl \
| PGPASSWORD=drinkerito psql -h 127.0.0.1 -p 5434 -U drinkerito -d drinkerito
```

**Nunca aponte o `.env` local direto para o Railway.** Os testes usam `RefreshDatabase`, que roda
`migrate:fresh` — apontado para a produção, ele derruba o catálogo inteiro.

---

## O que já foi feito

Seis commits, do mais antigo para o mais novo:

| Commit | O quê |
|---|---|
| `95237af` | `CLAUDE.md` com as orientações do projeto |
| `ca3a0f1` | Remove a integração com a TheCocktailDB (estava quebrada e fora de uso) |
| `1680726` | Preserva o tipo da bebida na aprovação; fecha XSS no Meu Bar e força bruta no login |
| `7d0707c` | Funde ingredientes duplicados; FKs, índice e unique em `bebida_ingrediente` |
| `76eacb0` | Suíte de testes contra Postgres |
| `39c1a9a` | Recuperação de senha por código no e-mail |

---

## Ordem sugerida

1. **SEC-05, SEC-03 e SEC-04** — menos de uma hora somados, e fecham a seção de segurança.
2. **Os bugs pequenos** (BUG-05 a BUG-07) — meia hora, e BUG-05 destrava usar Eloquent onde hoje há SQL cru.
3. **QA-02 e QA-05** — limpeza barata, com teste já cobrindo a área.
4. **FEAT-02 (Meu Bar persistente)** — a de maior valor por esforço entre as que sobraram.
5. O resto, conforme o tempo.

Escreva o teste antes da correção. A suíte está montada e as três áreas críticas já estão cobertas —
`php artisan test --filter=<Nome>` roda em menos de um segundo.

---

## Segurança (3)

### SEC-05 · Não existe `.env.example` · baixo

Quem clonar não descobre que precisa de OpenAI, Cloudinary, Google OAuth e agora também do container
de banco. O `composer.json` inclusive tem um script que copia `.env.example` na instalação.

**Fazer:** commitar um `.env.example` com todas as chaves e valores vazios, usando a seção de setup
acima como base.

### SEC-03 · Admin conferido à mão em cada método · médio

`app/Http/Controllers/CadastroBebidaController.php` — três cópias de:

```php
if (!Auth::user()->id_admin) {
    abort(403, 'Acesso não autorizado.');
}
```

No dia em que alguém adicionar um quarto método ao grupo `admin.` e esquecer a linha, o painel fica
aberto para qualquer usuário logado.

**Fazer:** um middleware `admin` aplicado no `Route::prefix('admin')` de `routes/web.php`. A proteção
passa a valer por rota, não por lembrança. Já existe teste (`AprovacaoBebidaTest::test_usuario_comum_nao_aprova`)
que continua tendo de passar.

### SEC-04 · Configuração de produção no Railway · médio

Duas variáveis no painel do Railway:

- `SESSION_SECURE_COOKIE=true` — o site é HTTPS e o cookie de sessão está sendo emitido sem a flag.
- `MAIL_FROM_ADDRESS` com um endereço real — hoje está vazio, e **sem isso a recuperação de senha
  estoura 500 em produção** com `An email must have a "From" header`. O `MAIL_MAILER` também precisa
  voltar para `smtp` com credenciais válidas.

Confirme também que `APP_DEBUG=false` lá.

---

## Bugs (3)

### BUG-05 · `Avaliacao::user()` aponta para coluna inexistente · médio

`app/Models/Avaliacao.php:28`

```php
return $this->belongsTo(User::class);          // procura user_id
return $this->belongsTo(User::class, 'id_usuario');  // correto
```

Não explode hoje porque a tela de detalhe monta as avaliações com `DB::table`. Com a relação
funcionando, aquele SQL cru em `Bebida::getBebida()` pode virar um `with('user')`.

### BUG-06 · Relação morta em `BebidaIngrediente` · baixo

`app/Models/BebidaIngrediente.php:19-22` — `bebidaCadastro()` declara a chave `cd_bebida_cadastro`,
que não existe nessa tabela (ela vive em `cadastro_bebida_ingrediente`). Resíduo de quando as duas
eram uma só.

**Fazer:** remover, e declarar no lugar as relações que faltam de verdade: `bebida()` e `ingrediente()`.

### BUG-07 · Favoritar id inexistente devolve 500 · baixo

`app/Http/Controllers/FavoritoController.php:29` — `alternar()` não valida a bebida, e a rota não tem
`whereNumber`. Um id qualquer vira violação de chave estrangeira, e o front, que espera JSON, recebe
uma página de erro.

**Fazer:** `Bebida::findOrFail($cd_bebida)` no topo e `->whereNumber('cd_bebida')` na rota, como
`bebida.show` já faz.

---

## Banco (1)

### DB-04 · Migrations sem rollback · baixo

`down()` vazio ou incompleto em:

- `2025_11_08_023129_drop_column_id_externo_from_bebida_table.php:22`
- `2025_11_12_231741_alter_table_bebida_change_column_type.php:24`

`migrate:rollback` passa por elas sem fazer nada e deixa o banco num estado que não corresponde a
nenhuma versão.

Também não existe seeder de bebidas: depois de um `migrate:fresh` o catálogo só volta chamando a
OpenAI, o que custa dinheiro. Um seeder com 10 ou 20 receitas fixas resolveria — e serviria de massa
de teste.

---

## Qualidade (5)

### QA-02 · Números mágicos de tipo e status · médio

`id_tipo` 1/2 e `id_status` 0/1/2 aparecem crus em controllers, views e SQL, sem nada que documente o
significado. O bug do tipo fixo na aprovação foi filho direto disso: `'id_tipo' => 1` não parece
errado quando se lê a linha isolada.

**Fazer:** dois enums do PHP 8.1 — `TipoBebida` e `StatusCadastro` — com um método `label()` que as
views usam. O badge de status em `perfil/index.blade.php` vira uma linha em vez de três `@elseif`.

### QA-03 · Todo o JavaScript mora dentro das views · médio

364 linhas em `partials/chatbot.blade.php`, 345 em `meubar/index.blade.php`. Vite e Tailwind estão
configurados e não são usados: o layout carrega Bootstrap, jQuery, Select2 e SweetAlert por CDN e
nunca chama `@vite`. Sem cache, sem versionamento e sem reaproveitar código entre telas — por isso o
`escapeHtml` existia só no chatbot, que foi a raiz do XSS no Meu Bar.

**Fazer:** não precisa migrar tudo de uma vez. Comece movendo chatbot e Meu Bar para
`resources/js/`, adicione `@vite` no layout e deixe os utilitários compartilhados num módulo só.

### QA-04 · `error_log()` no lugar do logger · baixo

`app/Http/Controllers/ChatbotController.php:176` escreve no log do PHP em vez do canal da aplicação —
e é justamente o `catch` do chatbot, o erro que você mais vai querer investigar. Fora do
`storage/logs`, some do `php artisan pail`.

**Fazer:** `Log::error()` com contexto (id do usuário, mensagem enviada). O `GoogleController` já faz
certo, use de referência.

### QA-05 · Acentos removidos duas vezes na busca · baixo

`app/Http/Controllers/SearchController.php:23` e `:55-68` — `limpaString()` é um mapa manual de 60
acentos aplicado ao termo digitado, e logo depois o SQL chama `unaccent()` nos dois lados da
comparação. O segundo já resolve o problema inteiro.

**Fazer:** apagar `limpaString()` e passar o termo original. Menos 14 linhas.

### QA-07 · Todas as páginas têm o mesmo `<title>` · baixo

`resources/views/layouts/app.blade.php:6` — o título está fixo, então a aba diz "Drinkerito - Sua rede
social de receitas de bebidas" mesmo na página de uma Caipirinha. Sem `meta description` e sem Open
Graph: o botão de compartilhar existe, mas o link colado no WhatsApp não mostra nem o nome do drink.

**Fazer:** `@yield('title', 'Drinkerito')` no layout e uma `@section('title')` por página. Nas telas
de bebida, `og:title`, `og:description` e `og:image` — a imagem já está no Cloudinary.

---

## Performance (5)

Nenhum dói com o catálogo atual (50 bebidas). São problemas que aparecem com crescimento.

| Item | Onde | O quê |
|---|---|---|
| PERF-01 · médio | `PerfilController.php:19`, `CadastroBebidaController.php:82` | `->get()` sem paginação; o perfil ainda traz `ds_preparo` inteiro só para cortar em 120 caracteres. Use `->paginate(10)` — o tema Bootstrap 5 da paginação já está configurado |
| PERF-02 · médio | `HomeController.php:13-45` | Três `GROUP BY` sobre o catálogo inteiro em toda visita à página mais acessada. `Cache::remember(..., 600, ...)` resolve; `CACHE_DRIVER=file` basta |
| PERF-03 · baixo | `app/Models/Bebida.php:42` | `ORDER BY RANDOM()` ordena a tabela toda para devolver uma linha. Sorteie o `cd_bebida` primeiro, depois monte a query completa |
| PERF-04 · baixo | `GerarBebidasAI.php:145-188` | Geração de imagem em série: cada drink espera o DALL·E e o upload. Vire Job na fila — o `composer dev` já sobe um `queue:listen` |
| PERF-05 · baixo | `search`, `favoritos`, `meubar` | Imagens do Cloudinary em tamanho cheio (até 1024px) para exibir em 200px. `loading="lazy"` e `w_400,f_auto,q_auto` na URL |

---

## Funcionalidades (11)

Ordenadas por retorno sobre esforço.

### FEAT-02 · Meu Bar que não se perde · impacto alto, esforço baixo

`MeuBarController.php:12` e `:18-27` — os ingredientes do usuário vivem só na sessão
(`meubar_ingredientes`). Trocou de celular, deslogou ou a sessão expirou: perdeu tudo. É a
funcionalidade mais original do site, e a única que não guarda nada.

**Fazer:** tabela `usuario_ingrediente` (`id_usuario`, `cd_ingrediente`, unique composta). Mantenha a
sessão para o visitante não logado e migre o conteúdo dela no login — o endpoint `sync-session` já
faz metade do caminho.

### FEAT-03 · Filtros de verdade na busca · impacto alto, esforço médio

`SearchController.php:25-28` — filtrar por "não alcoólico" depende de um regex tentando adivinhar
isso no texto digitado, e o chatbot chega a instruir o usuário a digitar essa frase exata. Qualquer
variação não prevista cai na busca textual e não filtra nada.

**Fazer:** facetas de verdade — tipo, nota mínima, número de ingredientes, ingrediente específico —
como parâmetros de query. O regex vira código morto.

### FEAT-04 · Avisar quando a bebida for aprovada ou rejeitada · impacto médio, esforço baixo

O usuário envia uma receita e nunca mais é avisado; precisa lembrar de voltar ao perfil. O motivo da
rejeição já é gravado e exibido lá, só falta ele descobrir que existe.

**Fazer:** uma Notification do Laravel por e-mail — o `User` já usa `Notifiable`, e o envio de e-mail
agora está montado por causa da recuperação de senha.

### FEAT-05 · Editar o próprio perfil · impacto médio, esforço baixo

Só dá para trocar a senha. Não dá para corrigir o próprio nome, nem quem entrou pelo Google e veio
com o nome da conta Google. O upload de avatar reaproveita o fluxo Cloudinary do cadastro de bebida.

### FEAT-06 · Página por ingrediente · impacto médio, esforço baixo

`HomeController.php:26-35` já mostra os quatro ingredientes mais usados, com imagem, e eles não levam
a lugar nenhum. A coluna `ingrediente.ds_imagem` existe e é preenchida pelo comando de IA.

**Fazer:** rota `/ingrediente/{cd}` listando os drinks que o usam. Páginas indexáveis de graça, a
partir de dado que você já tem.

### FEAT-07 · Chatbot com memória da conversa · impacto médio, esforço baixo

`ChatbotController.php:52-70` — cada mensagem vai para a OpenAI sozinha. "E uma versão sem álcool?"
logo depois de uma receita não faz sentido para o modelo. Com limite de 5 perguntas por dia, cada uma
desperdiçada pesa.

**Fazer:** guardar as últimas 6 mensagens na sessão e enviar junto. Dá para usar os favoritos do
usuário no system prompt também.

### FEAT-08 · Coleções de drinks · impacto alto, esforço médio

Favorito é binário. "Drinks de verão", "Para a festa de sábado" — listas nomeadas são o que
transforma favoritos em algo que se compartilha. Tabelas `colecao` + `colecao_bebida`, com flag de
pública/privada. Coleção pública com URL própria é conteúdo indexável gerado pelo usuário.

### FEAT-10 · Histórico de moderação · impacto médio, esforço médio

`CadastroBebidaController.php:82-88` lista só `id_status = 0`. Depois de aprovar não há como rever,
desfazer um engano ou saber quem decidiu o quê — não existe nem campo de quem moderou.

**Fazer:** abas Pendentes / Aprovadas / Rejeitadas, mais `id_moderador` e `dt_moderacao` na tabela.

### FEAT-09 · Escalar receita e converter medidas · impacto médio, esforço alto

"Fazer para 4 pessoas" e alternar entre ml, oz e dose. Exige separar `ds_medida` em quantidade +
unidade, hoje tudo numa string só — e como o import externo vinha do inglês, esse campo está
bagunçado. Uma migration de limpeza vem antes.

### FEAT-11 · Perfil público e seguir usuários · impacto alto, esforço alto

O título da página diz "sua rede social de receitas de bebidas", mas não existe nenhuma relação entre
usuários. Comece pequeno: perfil público em `/u/{id}` com as bebidas aprovadas e as avaliações da
pessoa. Seguir e feed vêm depois, se fizer sentido.

### FEAT-12 · Funcionar offline (PWA) · impacto médio, esforço médio

O contexto de uso é alguém preparando um drink com o celular na bancada, muitas vezes com internet
ruim. Manifest, ícones e um service worker cacheando as telas já visitadas. Depende de QA-03 (build
via Vite) para ficar organizado.

---

## Uma pendência de dados, não de código

Duas coisas que a varredura encontrou no catálogo e que nenhuma correção resolve sozinha:

- As bebidas aprovadas **antes** de `1680726` continuam marcadas como alcoólicas, porque o tipo era
  fixo em 1. São 50 bebidas no total, dá para revisar na mão em poucos minutos.
- Existe receita com ingrediente claramente errado — apareceu "Mojito — falta: Polenta" ao testar o
  Meu Bar. Provavelmente da geração por IA. Vale uma passada de curadoria.
