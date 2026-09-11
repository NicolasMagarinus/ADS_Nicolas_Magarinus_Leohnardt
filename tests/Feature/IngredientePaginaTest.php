<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\BebidaIngrediente;
use App\Models\Ingrediente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A home já mostrava os ingredientes mais usados, mas o card levava para a
 * busca por texto, que casa com LIKE: "Limão" trazia também "Limão siciliano"
 * e qualquer drink com "limão" no nome. A página por ingrediente casa por id.
 */
class IngredientePaginaTest extends TestCase
{
    use RefreshDatabase;

    private function ingrediente(string $nome, ?string $imagem = null): Ingrediente
    {
        return Ingrediente::create(['nm_ingrediente' => $nome, 'ds_imagem' => $imagem]);
    }

    private function bebidaCom(string $nome, array $ingredientes): Bebida
    {
        $bebida = Bebida::create([
            'nm_bebida' => $nome,
            'ds_preparo' => 'Misture tudo e sirva.',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Teste.',
        ]);

        foreach ($ingredientes as $ingrediente) {
            BebidaIngrediente::create([
                'cd_bebida' => $bebida->cd_bebida,
                'cd_ingrediente' => $ingrediente->cd_ingrediente,
                'ds_medida' => '50 ml',
            ]);
        }

        return $bebida;
    }

    public function test_pagina_lista_so_os_drinks_do_ingrediente(): void
    {
        $cachaca = $this->ingrediente('Cachaca');
        $rum = $this->ingrediente('Rum');

        $this->bebidaCom('Caipirinha', [$cachaca]);
        $this->bebidaCom('Mojito', [$rum]);

        $this->get(route('ingrediente.show', $cachaca->cd_ingrediente))
            ->assertOk()
            ->assertSee('Caipirinha')
            ->assertDontSee('Mojito');
    }

    public function test_pagina_usa_o_ingrediente_no_titulo_e_na_imagem(): void
    {
        $ingrediente = $this->ingrediente('Rum', 'https://res.cloudinary.com/test/rum.jpg');
        $this->bebidaCom('Mojito', [$ingrediente]);

        $this->get(route('ingrediente.show', $ingrediente->cd_ingrediente))
            ->assertOk()
            ->assertSee('<title>Rum — Drinkerito</title>', false)
            ->assertSee('<meta property="og:image" content="https://res.cloudinary.com/test/rum.jpg">', false);
    }

    public function test_ingrediente_inexistente_da_404(): void
    {
        $this->get(route('ingrediente.show', 999999))->assertNotFound();
    }

    public function test_id_nao_numerico_nao_chega_ao_controller(): void
    {
        $this->get('/ingrediente/abc')->assertNotFound();
    }

    public function test_ingrediente_sem_receitas_responde_vazio_e_nao_indexavel(): void
    {
        $orfao = $this->ingrediente('Polenta');

        $this->get(route('ingrediente.show', $orfao->cd_ingrediente))
            ->assertOk()
            ->assertSee('Polenta')
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_ingrediente_com_receitas_e_indexavel(): void
    {
        $rum = $this->ingrediente('Rum');
        $this->bebidaCom('Mojito', [$rum]);

        $this->get(route('ingrediente.show', $rum->cd_ingrediente))
            ->assertOk()
            ->assertDontSee('name="robots"', false);
    }

    public function test_indice_esconde_ingrediente_sem_receita(): void
    {
        $rum = $this->ingrediente('Rum');
        $this->ingrediente('Polenta');
        $this->bebidaCom('Mojito', [$rum]);

        $this->get(route('ingrediente.index'))
            ->assertOk()
            ->assertSee('Rum')
            ->assertDontSee('Polenta');
    }

    public function test_indice_mostra_em_quantas_receitas_o_ingrediente_aparece(): void
    {
        $rum = $this->ingrediente('Rum');
        $this->bebidaCom('Mojito', [$rum]);
        $this->bebidaCom('Cuba Libre', [$rum]);

        $this->get(route('ingrediente.index'))
            ->assertOk()
            ->assertSee('2 receitas');
    }

    public function test_menu_leva_ao_indice_de_ingredientes(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('ingrediente.index'), false);
    }

    public function test_lista_de_drinks_e_paginada(): void
    {
        $rum = $this->ingrediente('Rum');

        // 13 receitas para estourar a página de 12.
        foreach (range(1, 13) as $i) {
            $this->bebidaCom(sprintf('Drink %02d', $i), [$rum]);
        }

        $primeira = $this->get(route('ingrediente.show', $rum->cd_ingrediente))->assertOk();
        $primeira->assertSee('13 receitas');

        $segunda = $this->get(route('ingrediente.show', $rum->cd_ingrediente).'?page=2')->assertOk();

        // A ordenação é nota e depois nome; com todas sem nota, sobra o nome,
        // então o último alfabeticamente é o único da segunda página.
        $segunda->assertSee('Drink 13');
        $segunda->assertDontSee('Drink 01');
    }

    public function test_home_linka_para_a_pagina_do_ingrediente(): void
    {
        $rum = $this->ingrediente('Rum');
        $this->bebidaCom('Mojito', [$rum]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('ingrediente.show', $rum->cd_ingrediente), false);
    }
}
