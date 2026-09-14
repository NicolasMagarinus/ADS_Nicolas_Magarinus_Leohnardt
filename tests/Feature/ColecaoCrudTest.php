<?php

namespace Tests\Feature;

use App\Models\Colecao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coleção pública é superfície indexável criada por usuário, e superfície
 * indexável sem teto é convite a script — daí o limite por conta.
 *
 * O unique (id_usuario, nm_colecao) é quem garante de verdade que ninguém tem
 * duas listas com o mesmo nome; a regra de validação existe para a pessoa ler
 * uma mensagem em vez de um erro de banco.
 */
class ColecaoCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_colecao(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->post(route('colecao.store'), [
                'nm_colecao' => 'Drinks de verão',
                'ds_colecao' => 'Para os dias quentes.',
                'id_publica' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('colecao', [
            'id_usuario' => $usuario->id,
            'nm_colecao' => 'Drinks de verão',
            'id_publica' => true,
        ]);
    }

    public function test_nome_repetido_do_mesmo_usuario_e_recusado(): void
    {
        $usuario = User::factory()->create();
        Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs($usuario)
            ->post(route('colecao.store'), ['nm_colecao' => 'Drinks de verão'])
            ->assertSessionHasErrors('nm_colecao');

        $this->assertDatabaseCount('colecao', 1);
    }

    public function test_duas_pessoas_podem_ter_o_mesmo_nome(): void
    {
        Colecao::create(['id_usuario' => User::factory()->create()->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs(User::factory()->create())
            ->post(route('colecao.store'), ['nm_colecao' => 'Drinks de verão'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('colecao', 2);
    }

    public function test_recusa_a_colecao_acima_do_limite(): void
    {
        $usuario = User::factory()->create();

        for ($i = 0; $i < 50; $i++) {
            Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Lista '.$i]);
        }

        $this->actingAs($usuario)
            ->post(route('colecao.store'), ['nm_colecao' => 'A quinquagésima primeira'])
            ->assertSessionHasErrors('nm_colecao');

        $this->assertDatabaseCount('colecao', 50);
    }

    public function test_renomear_muda_a_url_canonica(): void
    {
        $usuario = User::factory()->create();
        $colecao = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs($usuario)
            ->put(route('colecao.update', $colecao->cd_colecao), [
                'nm_colecao' => 'Drinks de inverno',
                'id_publica' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(
            $colecao->cd_colecao.'-drinks-de-inverno',
            $colecao->fresh()->parametroUrl()
        );
    }

    public function test_nao_mexe_na_colecao_de_outra_pessoa(): void
    {
        $colecao = Colecao::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_colecao' => 'Drinks de verão',
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('colecao.update', $colecao->cd_colecao), ['nm_colecao' => 'Sequestrada'])
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->delete(route('colecao.destroy', $colecao->cd_colecao))
            ->assertNotFound();

        $this->assertDatabaseHas('colecao', ['nm_colecao' => 'Drinks de verão']);
    }

    public function test_apaga_a_propria_colecao(): void
    {
        $usuario = User::factory()->create();
        $colecao = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs($usuario)
            ->delete(route('colecao.destroy', $colecao->cd_colecao))
            ->assertRedirect();

        $this->assertDatabaseCount('colecao', 0);
    }

    public function test_visitante_nao_cria(): void
    {
        $this->post(route('colecao.store'), ['nm_colecao' => 'Drinks de verão'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('colecao', 0);
    }
}
