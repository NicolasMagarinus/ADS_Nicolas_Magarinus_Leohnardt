<?php

namespace Tests\Feature;

use App\Models\Bebida;
use App\Models\BebidaIngrediente;
use App\Models\Ingrediente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A busca precisa ignorar acento nos dois sentidos: quem digita "caipirinha"
 * acha "Caipirinha", e quem digita "açaí" acha o mesmo drink que quem digita
 * "acai". Quem resolve isso é o unaccent() do Postgres, dentro do SQL.
 */
class BuscaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $caipirinha = Bebida::create([
            'nm_bebida' => 'Caipirinha de Açaí',
            'ds_preparo' => 'Macere o limão com açúcar e complete com cachaça.',
            'id_tipo' => 1,
            'ds_bebida' => 'Clássico brasileiro.',
        ]);

        $limonada = Bebida::create([
            'nm_bebida' => 'Limonada Suíça',
            'ds_preparo' => 'Bata tudo no liquidificador e coe.',
            'id_tipo' => 2,
            'ds_bebida' => 'Refrescante.',
        ]);

        $hortela = Ingrediente::create(['nm_ingrediente' => 'Hortelã']);
        BebidaIngrediente::create([
            'cd_bebida' => $limonada->cd_bebida,
            'cd_ingrediente' => $hortela->cd_ingrediente,
            'ds_medida' => 'a gosto',
        ]);
    }

    public function test_termo_sem_acento_acha_nome_com_acento(): void
    {
        $this->get(route('search', ['q' => 'acai']))
            ->assertOk()
            ->assertSee('Caipirinha de Açaí')
            ->assertDontSee('Limonada Suíça');
    }

    public function test_termo_com_acento_acha_o_mesmo_drink(): void
    {
        $this->get(route('search', ['q' => 'Açaí']))
            ->assertOk()
            ->assertSee('Caipirinha de Açaí');
    }

    public function test_busca_por_ingrediente_ignora_acento(): void
    {
        $this->get(route('search', ['q' => 'hortela']))
            ->assertOk()
            ->assertSee('Limonada Suíça')
            ->assertDontSee('Caipirinha de Açaí');
    }

    public function test_nao_alcoolica_filtra_por_tipo(): void
    {
        $this->get(route('search', ['q' => 'não alcoólica']))
            ->assertOk()
            ->assertSee('Limonada Suíça')
            ->assertDontSee('Caipirinha de Açaí');
    }

    public function test_alcoolica_filtra_por_tipo(): void
    {
        $this->get(route('search', ['q' => 'alcoolica']))
            ->assertOk()
            ->assertSee('Caipirinha de Açaí')
            ->assertDontSee('Limonada Suíça');
    }

    public function test_busca_vazia_lista_tudo(): void
    {
        $this->get(route('search'))
            ->assertOk()
            ->assertSee('Caipirinha de Açaí')
            ->assertSee('Limonada Suíça');
    }
}
