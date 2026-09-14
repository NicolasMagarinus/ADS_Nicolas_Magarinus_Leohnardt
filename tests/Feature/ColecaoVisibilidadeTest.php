<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\Colecao;
use App\Models\ColecaoBebida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Índice cheio de página magra é pior que índice menor: é a mesma regra que o
 * /ingredientes já aplica ao deixar ingrediente órfão de fora. A coleção
 * pública com menos de 3 bebidas continua acessível por link, mas sai com
 * noindex.
 *
 * A privada responde 404, e não 403, porque 403 confirmaria que ela existe.
 */
class ColecaoVisibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private function colecaoCom(int $qtBebidas, bool $publica = true, ?User $dono = null): Colecao
    {
        $colecao = Colecao::create([
            'id_usuario' => ($dono ?? User::factory()->create())->id,
            'nm_colecao' => 'Coleção '.uniqid(),
            'id_publica' => $publica,
        ]);

        for ($i = 0; $i < $qtBebidas; $i++) {
            $bebida = Bebida::create([
                'nm_bebida' => 'Drink '.uniqid(),
                'ds_preparo' => 'Misture e sirva.',
                'id_tipo' => TipoBebida::Alcoolica,
                'ds_bebida' => 'Teste.',
            ]);
            ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $bebida->cd_bebida]);
        }

        return $colecao;
    }

    public function test_privada_da_404_para_estranho(): void
    {
        $privada = $this->colecaoCom(3, publica: false);

        $this->actingAs(User::factory()->create())->get($privada->url())->assertNotFound();
        $this->get($privada->url())->assertNotFound();
    }

    public function test_privada_abre_para_o_dono(): void
    {
        $dono = User::factory()->create();
        $privada = $this->colecaoCom(3, publica: false, dono: $dono);

        $this->actingAs($dono)->get($privada->url())->assertOk();
    }

    public function test_indice_lista_so_publica_com_tres_ou_mais(): void
    {
        $cheia = $this->colecaoCom(3);
        $magra = $this->colecaoCom(2);
        $privada = $this->colecaoCom(5, publica: false);

        $resposta = $this->get(route('colecao.index'))->assertOk();

        $resposta->assertSee($cheia->nm_colecao);
        $resposta->assertDontSee($magra->nm_colecao);
        $resposta->assertDontSee($privada->nm_colecao);
    }

    public function test_publica_magra_responde_200_com_noindex(): void
    {
        $magra = $this->colecaoCom(2);

        $this->get($magra->url())
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_publica_cheia_nao_leva_noindex(): void
    {
        $cheia = $this->colecaoCom(3);

        $this->get($cheia->url())
            ->assertOk()
            ->assertDontSee('name="robots"', false);
    }

    public function test_privada_leva_noindex_para_o_dono(): void
    {
        $dono = User::factory()->create();
        $privada = $this->colecaoCom(5, publica: false, dono: $dono);

        $this->actingAs($dono)->get($privada->url())
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false);
    }
}
