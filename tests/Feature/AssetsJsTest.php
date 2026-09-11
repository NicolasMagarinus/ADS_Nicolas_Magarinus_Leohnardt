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
 */
class AssetsJsTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_carrega_o_utilitario_compartilhado(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('js/drinkerito.js', false);
    }

    public function test_arquivos_saem_versionados(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        // Sem ?v= o navegador serve o arquivo velho depois de cada deploy.
        $this->assertMatchesRegularExpression('#js/drinkerito\.js\?v=\d+#', $html);
    }

    public function test_pagina_carrega_o_script_do_chatbot(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('js/chatbot.js', false);
    }

    public function test_meu_bar_carrega_o_proprio_script(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('meubar.index'))
            ->assertOk()
            ->assertSee('js/meubar.js', false);
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

    /**
     * Scripts com defer executam na ordem do documento. O parcial do chatbot é
     * incluído antes do rodapé, então o utilitário compartilhado precisa vir
     * antes dele — senão window.Drinkerito é undefined quando o chatbot roda.
     */
    public function test_utilitario_carrega_antes_dos_scripts_de_pagina(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'js/chatbot.js'),
            strpos($html, 'js/drinkerito.js'),
            'o utilitário compartilhado está sendo carregado depois de quem depende dele'
        );
    }

    public function test_escape_cobre_a_aspa_simples(): void
    {
        $js = file_get_contents(public_path('js/drinkerito.js'));

        // As duas cópias antigas esqueciam a aspa simples.
        $this->assertStringContainsString('&#39;', $js);
    }
}
