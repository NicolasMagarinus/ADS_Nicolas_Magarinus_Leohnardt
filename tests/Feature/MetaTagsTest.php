<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Todas as páginas tinham o mesmo <title>, e nenhuma tinha Open Graph: o
 * botão de compartilhar existia, mas o link colado no WhatsApp não mostrava
 * nem o nome do drink.
 *
 * Nome e descrição de bebida vêm de submissão de usuário, então o teste das
 * aspas é o que importa de verdade aqui: @yield devolve conteúdo cru, e um
 * nome com aspas fecharia o atributo content="..." e deixaria injetar HTML
 * dentro do <head>.
 */
class MetaTagsTest extends TestCase
{
    use RefreshDatabase;

    private function bebida(array $atributos = []): Bebida
    {
        return Bebida::create(array_merge([
            'nm_bebida' => 'Caipirinha',
            'ds_preparo' => 'Macere o limão com açúcar e complete com cachaça.',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'O drink brasileiro mais conhecido do mundo.',
            'ds_imagem' => 'https://res.cloudinary.com/test/image/upload/caipirinha.jpg',
        ], $atributos));
    }

    public function test_home_usa_o_titulo_padrao(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>Drinkerito — sua rede social de receitas de bebidas</title>', false);
    }

    public function test_pagina_de_bebida_usa_o_nome_no_titulo(): void
    {
        $bebida = $this->bebida();

        $this->get(route('bebida.show', $bebida->cd_bebida))
            ->assertOk()
            ->assertSee('<title>Caipirinha — Drinkerito</title>', false);
    }

    public function test_pagina_de_bebida_publica_open_graph(): void
    {
        $bebida = $this->bebida();

        $resposta = $this->get(route('bebida.show', $bebida->cd_bebida))->assertOk();

        $resposta->assertSee('<meta property="og:title" content="Caipirinha — Drinkerito">', false);
        $resposta->assertSee('<meta property="og:type" content="article">', false);
        $resposta->assertSee('<meta property="og:image" content="https://res.cloudinary.com/test/image/upload/caipirinha.jpg">', false);
        $resposta->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_descricao_da_bebida_sai_do_ds_bebida(): void
    {
        $bebida = $this->bebida();

        $this->get(route('bebida.show', $bebida->cd_bebida))
            ->assertOk()
            ->assertSee('<meta name="description" content="O drink brasileiro mais conhecido do mundo.">', false);
    }

    public function test_bebida_sem_imagem_nao_emite_og_image(): void
    {
        $bebida = $this->bebida(['ds_imagem' => null]);

        $this->get(route('bebida.show', $bebida->cd_bebida))
            ->assertOk()
            ->assertDontSee('property="og:image"', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false);
    }

    public function test_aspas_no_nome_do_drink_nao_quebram_o_atributo(): void
    {
        $bebida = $this->bebida([
            'nm_bebida' => 'Gin & Tonic "Especial"',
            'ds_bebida' => 'Leva <script>alert(1)</script> de limão siciliano.',
        ]);

        $resposta = $this->get(route('bebida.show', $bebida->cd_bebida))->assertOk();

        // O atributo continua fechando onde deve: as aspas viraram entidade.
        $resposta->assertSee('content="Gin &amp; Tonic &quot;Especial&quot; — Drinkerito"', false);
        $resposta->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_busca_reflete_o_termo(): void
    {
        $this->get(route('search', ['q' => 'gin']))
            ->assertOk()
            ->assertSee('<title>Busca por “gin” — Drinkerito</title>', false);
    }

    public function test_busca_sem_termo_tem_titulo_generico(): void
    {
        $this->get(route('search'))
            ->assertOk()
            ->assertSee('<title>Busca — Drinkerito</title>', false);
    }

    public function test_termo_de_busca_com_aspas_sai_escapado(): void
    {
        $resposta = $this->get(route('search', ['q' => 'gin "seco" & tonica']))->assertOk();

        $resposta->assertSee('<title>Busca por “gin &quot;seco&quot; &amp; tonica” — Drinkerito</title>', false);
        $resposta->assertDontSee('content="Busca por “gin "seco"', false);
    }

    public function test_pagina_aleatoria_usa_o_drink_sorteado(): void
    {
        $this->bebida(['nm_bebida' => 'Mojito', 'ds_bebida' => 'Refrescante e cubano.']);

        $this->get(route('random'))
            ->assertOk()
            ->assertSee('<title>Mojito — Drinkerito</title>', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('property="og:image"', false);
    }

    public function test_pagina_de_erro_tem_titulo_proprio(): void
    {
        $this->get('/bebida/999999')
            ->assertNotFound()
            ->assertSee('<title>Página não encontrada — Drinkerito</title>', false)
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_tela_atras_de_login_nao_e_indexavel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('perfil.index'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_pagina_publica_e_indexavel(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('name="robots"', false);
    }

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
}
