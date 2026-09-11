# Próximos passos

Pendências levantadas na varredura de 10/set/2026. Restam **11 itens**, nenhum de severidade alta —
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

`cp .env.example .env` e preencha o que for usar. O arquivo já vem com os valores de
desenvolvimento; só as chaves de serviço externo ficam em branco. Para referência:

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

### 3. Instalar e migrar

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan test          # 179 testes devem passar
php artisan serve
```

### 4. Popular o catálogo

```bash
php artisan db:seed          # 16 receitas fixas, sem chamar a OpenAI
```

O `BebidaSeeder` é idempotente e não gasta cota de API. Para trabalhar com os dados reais, traga um
dump do Railway:

```bash
PGPASSWORD='<senha do Railway>' pg_dump -h <host>.proxy.rlwy.net -p <porta> \
  -U postgres -d railway --no-owner --no-acl \
| PGPASSWORD=drinkerito psql -h 127.0.0.1 -p 5434 -U drinkerito -d drinkerito
```

**Nunca aponte o `.env` local direto para o Railway.** Os testes usam `RefreshDatabase`, que roda
`migrate:fresh` — apontado para a produção, ele derruba o catálogo inteiro.

---

## O que já foi feito

Do mais antigo para o mais novo:

| Commit | O quê |
|---|---|
| `95237af` | `CLAUDE.md` com as orientações do projeto |
| `ca3a0f1` | Remove a integração com a TheCocktailDB (estava quebrada e fora de uso) |
| `1680726` | Preserva o tipo da bebida na aprovação; fecha XSS no Meu Bar e força bruta no login |
| `7d0707c` | Funde ingredientes duplicados; FKs, índice e unique em `bebida_ingrediente` |
| `76eacb0` | Suíte de testes contra Postgres |
| `39c1a9a` | Recuperação de senha por código no e-mail |
| `19f36c4` | Documenta o backlog e o setup em máquina nova |
| `f4c367c` | **SEC-05** — `.env.example` com as chaves do projeto |
| `7d6fd68` | **SEC-03** — painel de moderação protegido por middleware |
| `3c58bfb` | **BUG-05/06/07** — relações dos models e favoritar id inexistente |
| `a36cab6` | **QA-02** enums `TipoBebida` e `StatusCadastro`; **QA-05** busca sem o mapa manual de acentos |
| `bcb5a50` | Corrige `MAIL_ENCRYPTION`, chave morta no Laravel 12, no `.env.example` |
| `05b909a` | Detalha o **SEC-04** com as duas armadilhas de configuração |
| `b9bef3f` | **QA-04** — erro do chatbot no canal da aplicação |
| `121c0a8` | **FEAT-02** — Meu Bar persistente em `usuario_ingrediente` |
| `b14b644` | **QA-07** — título, meta description e Open Graph por página |
| `aecbbb7` | **FEAT-06** — página e índice por ingrediente |
| `e01e09a` | **FEAT-03** — facetas de verdade na busca |
| `aacfa6a` | **FEAT-14** — ingredientes da receita viram links |
| `3d16810` | **FEAT-05** — editar nome e avatar do próprio perfil |
| `36c8910` | **FEAT-04** — aviso por e-mail ao aprovar ou rejeitar |
| `cd19f0c` | **FEAT-07** — chatbot com memória da conversa |
| `54eea3d` | **PERF-02** cache da home; parte do **PERF-01**, paginação do painel de moderação |
| `f7600c7` | **QA-03** — JavaScript fora das views, em `public/js/` |
| `18ec805` | **PERF-05** — imagens no tamanho em que aparecem |
| `a13dcbc` | **PERF-03** — sorteio da bebida aleatória |
| `590e604` | **DB-04** — rollback nas migrations e seeder do catálogo |
| (este) | **PERF-01** — perfil deixa de trazer o preparo inteiro |

Dois defeitos apareceram no caminho e foram junto: o regex de "não alcoólica" na busca não tinha o
modificador `/u` e só funcionava porque o `limpaString()` tirava o acento antes; e o badge de
rejeitada no perfil usava `bi-times`, que é classe do Font Awesome e não do Bootstrap Icons.

---

## Ordem sugerida

1. **SEC-04** — só painel do Railway, nenhuma linha de código, e destrava a recuperação de senha em produção. **Pendente.**
2. **FEAT-08 (coleções de drinks)** — a maior das que sobraram por retorno: lista nomeada e pública é conteúdo indexável gerado pelo usuário.
4. O resto, conforme o tempo.

Escreva o teste antes da correção. `php artisan test --filter=<Nome>` roda em menos de um segundo.

Agora que `bebida.id_tipo` e `cadastro_bebida.id_status` são enums (`App\Enums\TipoBebida` e
`App\Enums\StatusCadastro`), **cuidado ao ler essas colunas via Eloquent numa view**: a comparação
`$bebida->id_status == 0` não casa mais. Os `DB::select` crus continuam devolvendo inteiro.

---

## Segurança (1)

### SEC-04 · Configuração de produção no Railway · médio · **PENDENTE**

Nenhuma linha de código: é tudo em **Variables** do serviço no Railway. O build não roda
`config:cache`, então as variáveis passam a valer no deploy seguinte, sem passo extra.

```dotenv
# 1. Cookie de sessão — o site é HTTPS e o cookie sai sem a flag Secure.
SESSION_SECURE_COOKIE=true

# 2. Remetente — hoje QUEBRADO em produção.
MAIL_FROM_ADDRESS=nao-responda@seudominio.com
MAIL_FROM_NAME=Drinkerito

# 3. SMTP de verdade (se ainda estiver em 'log', o e-mail não sai do container).
MAIL_MAILER=smtp
MAIL_HOST=<host do provedor>
MAIL_PORT=587
MAIL_USERNAME=<usuário>
MAIL_PASSWORD=<senha ou app password>
MAIL_SCHEME=smtp

# 4. Conferir que estão assim.
APP_DEBUG=false
APP_ENV=production
# MAIL_FROM_NAME vale "${APP_NAME}", então APP_NAME é o nome que aparece
# como remetente. No .env local ele está como UpServer, sobra de outro
# projeto, e o e-mail sai assinado "UpServer".
APP_NAME=Drinkerito
```

Duas armadilhas que custam tempo se não estiverem escritas:

- **A variável vazia não cai no valor padrão.** `MAIL_FROM_ADDRESS` existe no painel mas está em
  branco, e `env()` devolve string vazia, não `null` — o default `hello@example.com` do
  `config/mail.php` nunca entra. O e-mail sai sem cabeçalho `From` e a recuperação de senha estoura
  500 com `An email must have a "From" header`. Apagar a variável não resolve: precisa de endereço
  real.
- **`MAIL_ENCRYPTION` não existe mais no Laravel 12.** O `config/mail.php` lê `MAIL_SCHEME`. Na porta
  587 o STARTTLS é automático com `MAIL_SCHEME=smtp`; se o provedor exigir 465, use `MAIL_PORT=465` e
  `MAIL_SCHEME=smtps`.

**Como confirmar que funcionou:** peça a recuperação de senha em produção com um e-mail cadastrado e
veja se o código de 6 dígitos chega. É o único caminho que exercita remetente, SMTP e template juntos.

---

## Qualidade (1)

### QA-06 · Migrar o front para o Vite · médio

O JavaScript saiu das views e virou arquivo estático em `public/js/`, com versionamento por
`filemtime` (diretiva `@js`). O que **não** foi feito é o bundling: sem Vite não há minificação, não
há imports entre módulos, e as bibliotecas seguem vindo de CDN.

Três bloqueios concretos para quem for encarar, todos verificados:

- **`public/build` não existe** — o projeto nunca foi buildado. `@vite` no layout lança
  `ViteManifestNotFoundException` já no primeiro request, local e em produção.
- **O `railway.toml` não define comando de build**, só liga o nixpacks. Se o `npm run build` não
  rodar no deploy, o site vai ao ar quebrado. Confirme o que o nixpacks faz antes de tocar no layout.
- **`resources/css/app.css` importa Tailwind**, com sintaxe v3 e v4 misturadas (`@import 'tailwindcss'`
  junto de `@tailwind base`). Carregar esse CSS traz o preflight, que reseta estilo de elemento sobre
  um site inteiro em Bootstrap mais `public/css/custom.css` — regressão visual em todas as telas.

O `package.json` também pede limpeza: o bloco `dependencies` lista dezenas de pacotes transitivos
(`ansi-styles`, `color-name`, `yallist`), sinal de um `npm install` feito sobre a saída de outro
comando.

O **FEAT-12 (PWA)** depende deste item, não da extração que já foi feita.

---

## Performance (2)

Nenhum dói com o catálogo atual (50 bebidas). São problemas que aparecem com crescimento.

| Item | Onde | O quê |
|---|---|---|
| PERF-06 · baixo | `CadastroBebidaController::avisarAutor` | O aviso de moderação sai no mesmo request, depois do commit. Com `QUEUE_CONNECTION=sync` enfileirar não mudaria nada hoje; no dia em que a fila for de verdade, `ShouldQueue` na Notification tira o SMTP do caminho do admin |
| PERF-04 · baixo | `GerarBebidasAI.php:145-188` | Geração de imagem em série: cada drink espera o DALL·E e o upload. Vire Job na fila — o `composer dev` já sobe um `queue:listen` |

---

## Funcionalidades (7)

Ordenadas por retorno sobre esforço.

### FEAT-13 · Meu Bar para quem não está logado · impacto médio, esforço médio

As quatro rotas de `/meu-bar` exigem `auth`, então a tela nunca foi acessível deslogado — o
enunciado antigo do FEAT-02, que falava em "migrar a sessão no login", partia de um visitante que
não existe. Abrir a tela é uma funcionalidade à parte, de conversão: a pessoa monta o bar, vê o que
dá para preparar e só então cria conta.

**Fazer:** tirar o `auth` das rotas do Meu Bar, manter `localStorage` como armazenamento do
visitante e, no login e no cadastro (inclusive pelo Google), mesclar o que ele montou com o que já
houver em `usuario_ingrediente`. O ponto delicado é a mesclagem: união, e não substituição, senão
quem já tinha um bar montado o perde ao entrar de um aparelho novo.

### FEAT-15 · Trocar o e-mail, e a conta do Google por trás dele · impacto baixo, esforço médio

O FEAT-05 deixou o e-mail de fora de propósito, e o motivo não é preguiça: `GoogleController`
identifica a conta pelo e-mail (`firstOrCreate(['email' => $googleUser->getEmail()])`,
`GoogleController.php:26`). Quem entrou pelo Google e trocasse o e-mail no perfil passaria a criar
uma **segunda conta** no próximo login, deixando favoritos, receitas e avaliações na primeira.

**Fazer, nesta ordem:** uma coluna `google_id` em `users`, gravada no primeiro login pelo Google;
`GoogleController` passa a casar por ela, caindo para o e-mail só quando estiver vazia (as contas que
já existem); e só então a troca de e-mail no perfil, com unique, validação e a senha atual como
confirmação.

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
