<?php

namespace Tests\Feature;

use App\Models\Ingrediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O JavaScript morava dentro das views: 281 linhas no parcial do chatbot, que
 * o layout inclui em toda página, e 285 no Meu Bar. Sem cache, sem
 * versionamento e sem reaproveitar nada — por isso o escapeHtml existia em
 * duas cópias, e foi a falta dele no Meu Bar que abriu o XSS.
 *
 * Depois do QA-06 os arquivos são entries do Vite. A suíte roda com
 * withoutVite() (ver Tests\TestCase), porque public/build é gitignored e não
 * existe em clone novo; então o que dá para afirmar sobre as tags de asset não
 * é o HTML renderizado, e sim o contrato do lado dos fontes.
 */
class AssetsJsTest extends TestCase
{
    use RefreshDatabase;

    private function fonteDaView(string $view): string
    {
        return file_get_contents(resource_path('views/'.$view));
    }

    public function test_layout_carrega_os_entries_do_projeto(): void
    {
        $layout = $this->fonteDaView('layouts/app.blade.php');

        $this->assertStringContainsString('resources/css/app.css', $layout);
        $this->assertStringContainsString('resources/js/app.js', $layout);
    }

    /**
     * Quatro CDNs de terceiro saíram do caminho crítico de renderização. Se
     * alguma voltar, volta junto o motivo de elas terem saído.
     */
    public function test_layout_nao_carrega_mais_nada_de_cdn(): void
    {
        $layout = $this->fonteDaView('layouts/app.blade.php');

        $this->assertStringNotContainsString('cdn.jsdelivr.net', $layout);
        $this->assertStringNotContainsString('use.fontawesome.com', $layout);
    }

    public function test_pagina_carrega_o_script_do_chatbot(): void
    {
        $this->assertStringContainsString(
            "@vite('resources/js/chatbot.js')",
            $this->fonteDaView('partials/chatbot.blade.php')
        );
    }

    public function test_meu_bar_carrega_o_proprio_script(): void
    {
        $this->assertStringContainsString(
            "@vite('resources/js/meubar.js')",
            $this->fonteDaView('meubar/index.blade.php')
        );
    }

    /**
     * Declarar o @vite na view e esquecer o entry no vite.config.js passa em
     * todo teste que renderiza — e só quebra em produção, onde o manifesto
     * existe de verdade e não tem a chave pedida.
     */
    public function test_todo_entry_pedido_por_uma_view_esta_declarado_no_vite(): void
    {
        $config = file_get_contents(base_path('vite.config.js'));

        $pedidos = [];
        foreach (glob(resource_path('views').'/{,*/,*/*/}*.blade.php', GLOB_BRACE) as $view) {
            preg_match_all("/resources\/(?:js|css)\/[\w.-]+/", file_get_contents($view), $m);
            $pedidos = array_merge($pedidos, $m[0]);
        }

        $pedidos = array_unique($pedidos);
        $this->assertNotEmpty($pedidos, 'nenhuma view pede entry — o teste parou de testar algo');

        foreach ($pedidos as $entry) {
            $this->assertStringContainsString($entry, $config, "a view pede $entry, que o vite.config.js não declara");
            $this->assertFileExists(base_path($entry));
        }
    }

    /**
     * As 574 linhas de JavaScript que ainda moram inline dentro das views não
     * são módulos: leem estes três nomes do escopo global. Tirar qualquer um
     * deles do entry quebra uma tela só, sem erro em lugar nenhum — a busca do
     * cabeçalho, os alertas de favoritar, o modal do perfil.
     */
    public function test_entry_expoe_os_globais_que_o_js_inline_das_views_le(): void
    {
        // Sem os comentários: o docblock do app.js nomeia os três globais para
        // explicar por que existem, e casar com ele faria o teste passar mesmo
        // com o código removido.
        $app = preg_replace(['#/\*.*?\*/#s', '#//.*#'], '', file_get_contents(resource_path('js/app.js')));

        $this->assertMatchesRegularExpression('/window\.bootstrap\s*=/', $app);
        $this->assertMatchesRegularExpression('/window\.Swal\s*=/', $app);
        $this->assertStringContainsString("import './drinkerito.js'", $app);
    }

    /**
     * chatbot.js e meubar.js leem window.Drinkerito.escapeHtml no corpo do
     * módulo, não dentro de um handler. Sob a diretiva @js isso dependia da
     * ordem das tags; agora depende do grafo de módulos, que é o que o import
     * declara.
     */
    public function test_scripts_de_pagina_importam_o_utilitario_de_que_dependem(): void
    {
        foreach (['chatbot.js', 'meubar.js', 'colecao.js'] as $arquivo) {
            $this->assertStringContainsString(
                "import './drinkerito.js'",
                file_get_contents(resource_path('js/'.$arquivo)),
                "$arquivo lê window.Drinkerito sem declarar que depende dele"
            );
        }
    }

    public function test_configuracao_do_chatbot_chega_ao_navegador(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(route('chatbot.message'), $html);
        $this->assertStringContainsString(route('chatbot.salvar-bebida'), $html);
        $this->assertStringContainsString('csrfToken', $html);
    }

    public function test_configuracao_do_meu_bar_leva_os_ingredientes_salvos(): void
    {
        $usuario = User::factory()->create();
        $rum = Ingrediente::create(['nm_ingrediente' => 'Rum']);

        $this->actingAs($usuario)->postJson(route('meubar.salvar'), [
            'ingredientes' => [$rum->cd_ingrediente],
        ])->assertOk();

        $html = $this->actingAs($usuario)->get(route('meubar.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('meubar.salvar'), $html);
        $this->assertStringContainsString('Rum', $html);
    }

    /**
     * O que impede alguém reintroduzir a lógica por cópia: se estas funções
     * voltarem para dentro do HTML, o teste cai.
     */
    public function test_logica_nao_volta_para_dentro_das_views(): void
    {
        $usuario = User::factory()->create();

        $home = $this->actingAs($usuario)->get(route('home'))->assertOk();
        $home->assertDontSee('function escapeHtml', false);

        $meubar = $this->actingAs($usuario)->get(route('meubar.index'))->assertOk();
        $meubar->assertDontSee('function escapeHtml', false);
        $meubar->assertDontSee('function createDrinkCard', false);
    }

    public function test_escape_cobre_a_aspa_simples(): void
    {
        $js = file_get_contents(resource_path('js/drinkerito.js'));

        // As duas cópias antigas esqueciam a aspa simples.
        $this->assertStringContainsString('&#39;', $js);
    }
}
