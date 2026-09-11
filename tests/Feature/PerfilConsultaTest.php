<?php

namespace Tests\Feature;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use App\Models\CadastroBebida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O perfil trazia o ds_preparo inteiro de cada receita enviada só para a view
 * cortar em 120 caracteres. Modo de preparo é texto livre e pode ser longo;
 * quem enviou muitas receitas pagava por todas elas a cada visita.
 */
class PerfilConsultaTest extends TestCase
{
    use RefreshDatabase;

    private function cadastro(User $autor, string $preparo): CadastroBebida
    {
        return CadastroBebida::create([
            'id_usuario' => $autor->id,
            'nm_bebida' => 'Limonada Suíça',
            'id_tipo' => TipoBebida::NaoAlcoolica,
            'ds_bebida' => 'Refrescante.',
            'ds_preparo' => $preparo,
            'id_status' => StatusCadastro::Pendente,
        ]);
    }

    public function test_preparo_longo_nao_vem_inteiro_do_banco(): void
    {
        $usuario = User::factory()->create();
        $this->cadastro($usuario, str_repeat('Misture bem devagar. ', 200));

        $resposta = $this->actingAs($usuario)->get(route('perfil.index'))->assertOk();

        $enviadas = $resposta->viewData('arrBebida');

        $this->assertLessThan(
            1000,
            strlen($enviadas->first()->ds_preparo),
            'o preparo inteiro está vindo do banco só para ser cortado na view'
        );
    }

    public function test_tela_continua_mostrando_o_preparo_cortado(): void
    {
        $usuario = User::factory()->create();
        $this->cadastro($usuario, 'Bata tudo no liquidificador e coe. '.str_repeat('Sirva gelado. ', 30));

        $this->actingAs($usuario)->get(route('perfil.index'))
            ->assertOk()
            ->assertSee('Bata tudo no liquidificador e coe.')
            // O Str::limit da view continua acrescentando as reticências.
            ->assertSee('...');
    }

    public function test_preparo_curto_aparece_inteiro_e_sem_reticencias(): void
    {
        $usuario = User::factory()->create();
        $this->cadastro($usuario, 'Bata tudo e sirva.');

        $resposta = $this->actingAs($usuario)->get(route('perfil.index'))->assertOk();

        $this->assertSame('Bata tudo e sirva.', $resposta->viewData('arrBebida')->first()->ds_preparo);
    }

    public function test_as_colunas_que_a_tela_usa_continuam_chegando(): void
    {
        $usuario = User::factory()->create();
        $cadastro = $this->cadastro($usuario, 'Bata tudo e sirva.');
        $cadastro->update([
            'id_status' => StatusCadastro::Rejeitada,
            'ds_motivo_rejeicao' => 'Receita incompleta.',
        ]);

        $this->actingAs($usuario)->get(route('perfil.index'))
            ->assertOk()
            ->assertSee('Limonada Suíça')
            ->assertSee('Rejeitada')
            ->assertSee('Receita incompleta.');
    }
}
