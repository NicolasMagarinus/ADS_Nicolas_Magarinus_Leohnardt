# QA-06 — Migração do front para o Vite

**Data:** 16/set/2026 · **Status:** desenho aprovado, implementação não iniciada

Fecha o QA-06 do `docs/proximos-passos.md`, cujo enunciado listava três bloqueios
verificados mas não decidia nada. Este documento decide, e serve de base para o plano de
implementação.

---

## Por que

O JavaScript já saiu das views e virou arquivo estático em `public/js/`, versionado por
`filemtime` através da diretiva `@js`. O que **não** foi feito é o bundling. Sem ele:

- nada é minificado — `custom.css` tem 941 linhas e os quatro arquivos de `public/js/`
  somam 751, todos servidos como foram escritos;
- não há `import` entre módulos, então o reaproveitamento depende de `window.Drinkerito`;
- Bootstrap, Bootstrap Icons, Font Awesome e SweetAlert2 vêm de **três CDNs de terceiros**,
  no caminho crítico de renderização de toda página;
- o `?v=filemtime` é uma prótese para a falta de build: resolve o cache velho, mas o
  navegador ainda revalida o arquivo a cada deploy em vez de tratá-lo como imutável.

O FEAT-12 (PWA) depende deste item.

---

## Escopo

**Entra:** o pipeline de assets. Vite passa a bundlar e minificar o que já existe —
`custom.css`, os quatro arquivos de `public/js/`, e as quatro bibliotecas que hoje vêm de
CDN, agora vindas do npm.

**Não entra, e é deliberado:**

| Fora | Por quê |
|---|---|
| Tailwind | O site é Bootstrap. O preflight do Tailwind resetaria estilo de elemento sobre 941 linhas de CSS escritas à mão — regressão visual em todas as telas, sem teste que pegue. |
| As 574 linhas de JS inline em 14 views | Extrair é outro trabalho, com risco próprio. Elas continuam funcionando; ver "Os três globais". |
| As 492 linhas de `<style>` inline em 4 views | Três telas de auth e o 404. Ficam fora do bundle, sem minificação. |
| Consolidar as duas bibliotecas de ícones | 83 usos de Font Awesome e 73 de Bootstrap Icons, em 17 views. As duas entram no bundle. |

**Nenhuma tela muda de aparência.** Este é o critério que separa sucesso de fracasso: se
alguma mudou, a migração errou.

---

## Estado de partida (medido em 16/set/2026, HEAD `eded2d0`)

| | Hoje |
|---|---|
| Vite | Configurado em `vite.config.js`, **nunca buildado**. `public/build` não existe. |
| Layout | Sem `@vite`. Bootstrap 5.3.0, Bootstrap Icons 1.10.0, Font Awesome 5.0.7 e SweetAlert2 11 por CDN. |
| CSS próprio | `public/css/custom.css`, 941 linhas, variáveis no topo. |
| JS próprio | `public/js/{drinkerito,chatbot,meubar,colecao}.js` — 60 + 290 + 292 + 109 = 751 linhas. |
| `resources/css/app.css` | Mistura sintaxe Tailwind v3 e v4 (`@import 'tailwindcss'` junto de `@tailwind base`). |
| `resources/js/` | `app.js` importa `bootstrap.js`, que só configura `axios` — **`axios` não é usado em lugar nenhum do projeto.** |
| `package.json` | ~60 pacotes transitivos listados em `dependencies`; Tailwind 3 em `dependencies` e `@tailwindcss/vite` 4 em `devDependencies`. |
| Deploy | `railway.toml` só liga o nixpacks; nenhum comando de build declarado. |
| Testes | 28 arquivos de Feature renderizam view. `AssetsJsTest` tem 9 testes, 6 casando com `js/x.js?v=N`. |

---

## Decisões

### 1. `custom.css` e os quatro JS **mudam de lugar**, não de conteúdo

`public/css/custom.css` → `resources/css/custom.css`, e os quatro `public/js/*.js` →
`resources/js/`. Com `git mv`, para o histórico dessas 1.692 linhas sobreviver.

O conteúdo não é reescrito nesta migração. Só `drinkerito.js` ganha uma linha (ver decisão
3). Misturar "mover para o pipeline" com "reescrever" tornaria impossível saber, diante de
uma regressão, qual das duas causou.

### 2. Entries separados por página, não um bundle único

```
resources/js/app.js        → toda página (Bootstrap, SweetAlert2, drinkerito.js)
resources/js/chatbot.js    → onde o parcial do chatbot entra
resources/js/meubar.js     → meubar/index.blade.php
resources/js/colecao.js    → bebida/show.blade.php
```

Espelha o que a diretiva `@js` já fazia. Um bundle único mandaria as 691 linhas de
chatbot+meubar+colecao para todas as telas, inclusive as que não usam nada disso. O Vite
extrai sozinho o chunk comum entre entries, então não há duplicação de biblioteca.

### 3. Os três globais que o JS inline ainda espera

Sob Vite tudo vira módulo ESM, e escopo de módulo não é global. As 574 linhas de JS inline
que ficam nas views não são módulos e continuam lendo do `window`. Três nomes precisam ser
reexpostos **à mão** em `resources/js/app.js`:

| Global | Quem depende | Onde |
|---|---|---|
| `window.Drinkerito` | busca ao vivo do cabeçalho | `partials/header.blade.php:131` |
| `window.Swal` | 9 chamadas | `favoritos/index`, `cadastro_bebida/create`, `cadastro_bebida/index`, `bebida/show`, `meubar.js` |
| `window.bootstrap` | `new bootstrap.Modal(modalEl).show()` | `perfil/index.blade.php:290` |

`drinkerito.js` já faz `window.Drinkerito = (function () {...})()`; basta importá-lo. Os
outros dois exigem atribuição explícita.

**Esquecer qualquer um dos três é um erro de JS silencioso, numa tela só, que nenhum teste
da suíte pega.** Os três ganham teste (ver "Testes").

Os objetos de configuração que o servidor injeta — `window.DrinkeritoChatbot`,
`window.DrinkeritoMeuBar`, `window.DrinkeritoColecao` — continuam funcionando sem mudança:
são `<script>` inline sem `defer`, e `@vite` emite `type="module"`, que é deferido por
definição. O inline sempre roda antes.

### 4. Font Awesome fica fixado em 5.15.4

O CDN de hoje serve 5.0.7. O `@fortawesome/fontawesome-free` do npm está em 6.x, que
renomeou ícones (`fa-times` → `fa-xmark`) mantendo aliases para a maioria, mas não para
todos. São 83 usos em 17 views, e não há teste visual que pegue um ícone que sumiu.

Fixar em `5.15.4` congela numa versão de 2021. É o custo aceito: subir para o 6 é um
trabalho de conferir 83 ícones, e não é este trabalho.

### 5. Tailwind sai inteiro

`tailwindcss`, `@tailwindcss/vite`, `@tailwindcss/cli`, `autoprefixer` e `postcss` saem do
`package.json`. O plugin sai do `vite.config.js`. `resources/css/app.css` é apagado e
reescrito do zero — o arquivo atual não é ponto de partida para nada.

`axios` também sai: não é referenciado em nenhum lugar do projeto, que usa `fetch` com
`X-CSRF-TOKEN`. Com ele sai `resources/js/bootstrap.js`, cuja única função era configurá-lo.

### 6. A diretiva `@js` é removida

O `?v=filemtime` existia porque não havia build — está escrito assim no comentário do
`AppServiceProvider`. O nome com hash que o Vite gera faz melhor o mesmo trabalho: permite
cache imutável em vez de revalidação. A diretiva e seu comentário saem.

`@imagem` é a única outra diretiva do projeto e não é afetada.

### 7. Dois deploys, e o primeiro não muda o site

Com `@vite` no layout e sem `public/build`, **toda** rota responde
`ViteManifestNotFoundException`. Não é degradação, é o site fora do ar.

O `railway.toml` não declara comando de build. Se o nixpacks já roda `npm run build` por
autodetecção, ótimo; se não, descobrir isso junto com a troca do layout significa descobrir
com o site caído. Então o build é declarado explicitamente e validado num deploy próprio,
com o layout ainda nas CDNs.

---

## Plano de execução

### Fase 0 — Limpar o terreno

Nada vai ao ar. Nenhuma tela muda.

1. `package.json`: esvaziar `dependencies` dos ~60 transitivos. Ficam em
   `dependencies` as quatro bibliotecas de runtime — `bootstrap`, `bootstrap-icons`,
   `sweetalert2`, `@fortawesome/fontawesome-free` — e em `devDependencies` apenas `vite`,
   `laravel-vite-plugin` e `concurrently`. Saem Tailwind (`tailwindcss`,
   `@tailwindcss/vite`, `@tailwindcss/cli`), `autoprefixer`, `postcss` e `axios`.
2. Apagar `node_modules` e `package-lock.json`; `npm install` limpo.
3. Apagar `resources/js/bootstrap.js`. Reescrever `resources/css/app.css` e
   `resources/js/app.js` como entries mínimos e válidos — o conteúdo real chega na fase 2,
   mas eles precisam existir e buildar já aqui, senão as fases 0 e 1 rodam sobre um projeto
   que não compila e a fase 1 não consegue provar nada.
4. `vite.config.js`: remover o plugin do Tailwind.

**Verificação:** `npm run build` passa e gera `public/build/manifest.json`. `php artisan
test` continua verde (nada que a suíte toque mudou). O site local continua idêntico — o
layout ainda não conhece o `@vite`.

### Fase 1 — Provar o build no Railway (deploy 1)

O layout **não muda**. O site continua nas CDNs e continua idêntico.

1. Declarar `npm ci && npm run build` no `railway.toml` (ou em um `nixpacks.toml`, se o
   provider PHP não aceitar a declaração no `railway.toml` — resolver aqui é o ponto da fase).
2. Deploy.

**Verificação:** no log do Railway, `vite build` executou e `public/build/manifest.json` foi
gerado. Se não foi, o site continua no ar e a fase repete com outra configuração.

### Fase 2 — Montar os entries (local)

```bash
npm i bootstrap@5.3 bootstrap-icons sweetalert2 @fortawesome/fontawesome-free@5.15.4
git mv public/css/custom.css resources/css/custom.css
git mv public/js/drinkerito.js public/js/chatbot.js public/js/meubar.js public/js/colecao.js resources/js/
```

`resources/css/app.css` — a ordem é o que preserva as sobrescritas; `custom.css` por último:

```css
@import 'bootstrap/dist/css/bootstrap.min.css';
@import 'bootstrap-icons/font/bootstrap-icons.css';
@import '@fortawesome/fontawesome-free/css/all.min.css';
@import './custom.css';
```

`resources/js/app.js`:

```js
import * as bootstrap from 'bootstrap';
import Swal from 'sweetalert2';
import './drinkerito.js';

window.bootstrap = bootstrap;
window.Swal = Swal;
```

`vite.config.js` recebe os quatro entries.

**Verificação:** `npm run build` gera `public/build/manifest.json` com as quatro entradas, e
as fontes dos dois pacotes de ícones aparecem em `public/build/assets/`.

### Fase 3 — Blindar a suíte

**Antes da fase 4, e num commit próprio.** `tests/TestCase.php` ganha `withoutVite()` no
`setUp`. Sem isso, os 28 arquivos de Feature que renderizam view quebram de uma vez assim
que o layout mudar, e o erro real fica enterrado sob 200 falhas idênticas.

**Verificação:** `php artisan test` verde, com o layout ainda nas CDNs.

### Fase 4 — A troca (deploy 2)

1. `layouts/app.blade.php`: remover as 4 tags de CDN, o `asset('css/custom.css')` e o
   `@js('drinkerito.js')`; pôr `@vite(['resources/css/app.css', 'resources/js/app.js'])`.
2. `partials/chatbot.blade.php`, `meubar/index.blade.php`, `bebida/show.blade.php`:
   `@js('x.js')` → `@vite('resources/js/x.js')`.
3. `AppServiceProvider`: remover `registrarDiretivaJs()`, sua chamada e o comentário.
4. `AssetsJsTest`: reescrever os 5 testes que casam com `js/x.js?v=N` e trocar
   `public_path` por `resource_path` no sexto. Ver a seção "Testes".
5. Atualizar `CLAUDE.md` (seção "Frontend assets", inteira) e fechar o QA-06 em
   `docs/proximos-passos.md`.

### Fase 5 — Verificar de verdade

A suíte verde não é evidência suficiente neste projeto. Antes de chamar de pronto:

- Renderizar **as 31 telas** e comparar com o antes. Especialmente as 4 com `<style>`
  inline, que agora convivem com um CSS bundlado.
- Exercitar os três globais na mão: buscar no cabeçalho (`window.Drinkerito`), favoritar
  para ver o SweetAlert (`Swal`), abrir o modal do perfil (`bootstrap`).
- Conferir no navegador que as requisições caíram de 7 para 2 e que os assets vêm com hash.
- Exercitar o rollback: `git revert` do commit da fase 4 devolve o site às CDNs sem passo
  manual nenhum.

---

## Testes

`AssetsJsTest` hoje tem 9 testes. Três sobrevivem quase intactos:

| Teste | Destino |
|---|---|
| `test_configuracao_do_chatbot_chega_ao_navegador` | intacto |
| `test_configuracao_do_meu_bar_leva_os_ingredientes_salvos` | intacto |
| `test_logica_nao_volta_para_dentro_das_views` | intacto |
| `test_escape_cobre_a_aspa_simples` | `public_path` → `resource_path` |
| os outros 5 | reescritos para o manifesto do Vite |

Os 5 reescritos deixam de procurar `js/x.js?v=N` e passam a afirmar que a view referencia a
entry certa do manifesto. `test_utilitario_carrega_antes_dos_scripts_de_pagina` perde o
sentido que tinha — a ordem passa a ser garantida pelo `import`, não pela posição no
documento — e é substituído por um teste de que `app.js` importa `drinkerito.js`.

**Três testes novos**, um por global da decisão 3. São o que impede o erro silencioso:
cada um afirma que o entry expõe o nome no `window`, lendo o fonte de `resources/js/app.js`.
Não é um teste bonito, mas é o único que roda sem navegador e pega a regressão que importa.

---

## Riscos

| Risco | Mitigação |
|---|---|
| `npm run build` não roda no deploy → site fora do ar | Fase 1 prova isso num deploy que não muda o site. |
| Um dos três globais esquecido → JS quebrado numa tela | Três testes novos + verificação manual na fase 5. |
| npm indisponível derruba o deploy inteiro | Aceito. Hoje um problema de front nunca impediu o backend de subir; depois, impede. |
| Ícone do Font Awesome sumido | Versão fixada em 5.15.4 (decisão 4). |
| Regressão visual por ordem de CSS | `custom.css` importado por último; verificação tela a tela na fase 5. |
| Esquecer `npm run build` ao desenvolver | `composer dev` já sobe o `npm run dev`. |

---

## Custo

- **Esforço:** ~18 arquivos, dos quais 2 são só `git mv`. 4 a 6 horas de trabalho focado,
  mais dois deploys e a verificação tela a tela.
- **Deploy:** +30–60s de `npm ci` e +5–15s de `vite build` em todo deploy.
- **Dia a dia:** um segundo processo a manter vivo, ou `npm run build` a cada alteração de
  CSS/JS. Esquecer significa editar e não ver efeito.
- **Ganho:** 7 requisições externas viram 2; nenhuma CDN de terceiro no caminho crítico;
  1.692 linhas de CSS+JS minificadas; cache imutável por hash; FEAT-12 destravado.
