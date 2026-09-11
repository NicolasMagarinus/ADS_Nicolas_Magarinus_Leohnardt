<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\Bebida;
use App\Models\BebidaIngrediente;
use App\Models\Ingrediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As chaves não seguem a convenção do Laravel, então toda relação precisa
 * declarar a coluna à mão. Uma relação com a coluna errada só aparece quando
 * alguém a usa pela primeira vez — e as telas hoje usam SQL cru, o que
 * esconde o defeito.
 */
class RelacoesModelosTest extends TestCase
{
    use RefreshDatabase;

    private function bebida(): Bebida
    {
        return Bebida::create([
            'nm_bebida' => 'Caipirinha',
            'ds_preparo' => 'Macere o limão com açúcar, complete com cachaça e gelo.',
            'id_tipo' => 1,
            'ds_bebida' => 'Clássico brasileiro.',
        ]);
    }

    public function test_avaliacao_encontra_o_autor(): void
    {
        $autor = User::factory()->create(['name' => 'Joana']);

        $avaliacao = Avaliacao::create([
            'cd_bebida' => $this->bebida()->cd_bebida,
            'id_usuario' => $autor->id,
            'id_nota' => 5,
            'ds_avaliacao' => 'Perfeita.',
        ]);

        $this->assertSame('Joana', $avaliacao->user->name);
    }

    public function test_vinculo_encontra_bebida_e_ingrediente(): void
    {
        $bebida = $this->bebida();
        $ingrediente = Ingrediente::create(['nm_ingrediente' => 'Cachaça']);

        $vinculo = BebidaIngrediente::create([
            'cd_bebida' => $bebida->cd_bebida,
            'cd_ingrediente' => $ingrediente->cd_ingrediente,
            'ds_medida' => '50 ml',
        ]);

        $this->assertSame('Caipirinha', $vinculo->bebida->nm_bebida);
        $this->assertSame('Cachaça', $vinculo->ingrediente->nm_ingrediente);
    }
}
