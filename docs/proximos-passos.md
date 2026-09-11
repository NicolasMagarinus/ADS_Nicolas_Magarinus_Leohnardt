# Próximos passos

Pendências levantadas na varredura de 10/set/2026. Restam **20 itens**, nenhum de severidade alta —
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
php artisan migrate
php artisan test          # 58 testes devem passar
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
| (este) | **QA-04** — erro do chatbot no canal da aplicação |

Dois defeitos apareceram no caminho e foram junto: o regex de "não alcoólica" na busca não tinha o
modificador `/u` e só funcionava porque o `limpaString()` tirava o acento antes; e o badge de
rejeitada no perfil usava `bi-times`, que é classe do Font Awesome e não do Bootstrap Icons.

---

## Ordem sugerida

1. **SEC-04** — só painel do Railway, nenhuma linha de código, e destrava a recuperação de senha em produção. **Pendente.**
2. **FEAT-02 (Meu Bar persistente)** — a de maior valor por esforço entre as que sobraram.
3. **QA-07 e FEAT-06** — baratas e rendem página indexável.
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

## Qualidade (2)

### QA-03 · Todo o JavaScript mora dentro das views · médio

364 linhas em `partials/chatbot.blade.php`, 345 em `meubar/index.blade.php`. Vite e Tailwind estão
configurados e não são usados: o layout carrega Bootstrap, jQuery, Select2 e SweetAlert por CDN e
nunca chama `@vite`. Sem cache, sem versionamento e sem reaproveitar código entre telas — por isso o
`escapeHtml` existia só no chatbot, que foi a raiz do XSS no Meu Bar.

**Fazer:** não precisa migrar tudo de uma vez. Comece movendo chatbot e Meu Bar para
`resources/js/`, adicione `@vite` no layout e deixe os utilitários compartilhados num módulo só.

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
| PERF-02 · médio | `HomeController.php:13-45` | Três `GROUP BY` sobre o catálogo inteiro em toda visita à página mais acessada. `Cache::remember(..., 600, ...)` resolve. Atenção: no Laravel 12 a chave é `CACHE_STORE`, não `CACHE_DRIVER` |
| PERF-03 · baixo | `app/Models/Bebida.php:46` | `ORDER BY RANDOM()` ordena a tabela toda para devolver uma linha. Sorteie o `cd_bebida` primeiro, depois monte a query completa |
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
