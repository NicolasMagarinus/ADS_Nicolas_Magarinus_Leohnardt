<?php

namespace Tests\Feature;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use App\Models\CadastroBebida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O envio é a porta de entrada da área de staging. O tipo informado aqui é o
 * que a aprovação carrega para o catálogo, então um tipo inválido não pode
 * passar, e o padrão de status tem de ser pendente.
 */
class EnvioBebidaTest extends TestCase
{
    use RefreshDatabase;

    private function receita(array $atributos = []): array
    {
        return array_merge([
            'nm_bebida' => 'Limonada Suíça',
            'id_tipo' => TipoBebida::NaoAlcoolica->value,
            'ds_preparo' => 'Bata tudo no liquidificador e coe.',
            'ingredientes' => [
                ['nm_ingrediente' => 'Limão', 'ds_medida' => '2 unidades'],
            ],
        ], $atributos);
    }

    public function test_formulario_oferece_os_dois_tipos(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('bebida.create'))
            ->assertOk()
            ->assertSee('Alcoólica')
            ->assertSee('Não alcoólica')
            ->assertSee('value="1"', false)
            ->assertSee('value="2"', false);
    }

    public function test_envio_nasce_pendente_e_guarda_o_tipo(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('bebida.store'), $this->receita())
            ->assertRedirect(route('perfil.index'));

        $cadastro = CadastroBebida::firstOrFail();
        $this->assertSame(TipoBebida::NaoAlcoolica, $cadastro->id_tipo);
        $this->assertSame(StatusCadastro::Pendente, $cadastro->id_status);
    }

    public function test_tipo_fora_da_lista_e_recusado(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('bebida.store'), $this->receita(['id_tipo' => 7]))
            ->assertSessionHasErrors('id_tipo');

        $this->assertDatabaseCount('cadastro_bebida', 0);
    }

    public function test_tipo_ausente_e_recusado(): void
    {
        $dados = $this->receita();
        unset($dados['id_tipo']);

        $this->actingAs(User::factory()->create())
            ->post(route('bebida.store'), $dados)
            ->assertSessionHasErrors('id_tipo');
    }
}
