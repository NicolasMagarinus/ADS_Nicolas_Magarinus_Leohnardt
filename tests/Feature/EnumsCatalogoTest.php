<?php

namespace Tests\Feature;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\CadastroBebida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * id_tipo 1/2 e id_status 0/1/2 estavam escritos crus em controller, view e
 * SQL. Foi assim que a aprovação passou a gravar toda bebida como alcoólica:
 * 'id_tipo' => 1 não parece errado quando se lê a linha isolada.
 */
class EnumsCatalogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_tipo_conhece_o_proprio_rotulo(): void
    {
        $this->assertSame('Alcoólica', TipoBebida::Alcoolica->label());
        $this->assertSame('Não alcoólica', TipoBebida::NaoAlcoolica->label());
    }

    public function test_numeros_do_banco_continuam_os_mesmos(): void
    {
        $this->assertSame(1, TipoBebida::Alcoolica->value);
        $this->assertSame(2, TipoBebida::NaoAlcoolica->value);

        $this->assertSame(0, StatusCadastro::Pendente->value);
        $this->assertSame(1, StatusCadastro::Aprovada->value);
        $this->assertSame(2, StatusCadastro::Rejeitada->value);
    }

    public function test_status_conhece_o_proprio_rotulo(): void
    {
        $this->assertSame('Pendente', StatusCadastro::Pendente->label());
        $this->assertSame('Aprovada', StatusCadastro::Aprovada->label());
        $this->assertSame('Rejeitada', StatusCadastro::Rejeitada->label());
    }

    public function test_bebida_lida_do_banco_devolve_o_enum(): void
    {
        $bebida = Bebida::create([
            'nm_bebida' => 'Limonada',
            'ds_preparo' => 'Misture e sirva gelado.',
            'id_tipo' => TipoBebida::NaoAlcoolica,
            'ds_bebida' => 'Simples.',
        ]);

        $this->assertSame(TipoBebida::NaoAlcoolica, $bebida->fresh()->id_tipo);
        $this->assertDatabaseHas('bebida', [
            'cd_bebida' => $bebida->cd_bebida,
            'id_tipo' => 2,
        ]);
    }

    public function test_cadastro_lido_do_banco_devolve_os_enums(): void
    {
        $cadastro = CadastroBebida::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_bebida' => 'Caipirinha',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_preparo' => 'Macere e complete com cachaça.',
            'id_status' => StatusCadastro::Pendente,
        ])->fresh();

        $this->assertSame(TipoBebida::Alcoolica, $cadastro->id_tipo);
        $this->assertSame(StatusCadastro::Pendente, $cadastro->id_status);
    }

    public function test_perfil_mostra_o_badge_de_cada_status(): void
    {
        $usuario = User::factory()->create();

        foreach (StatusCadastro::cases() as $status) {
            CadastroBebida::create([
                'id_usuario' => $usuario->id,
                'nm_bebida' => 'Drink '.$status->name,
                'id_tipo' => TipoBebida::Alcoolica,
                'ds_preparo' => 'Misture tudo.',
                'id_status' => $status,
                'ds_motivo_rejeicao' => $status === StatusCadastro::Rejeitada ? 'Receita incompleta.' : null,
            ]);
        }

        $resposta = $this->actingAs($usuario)->get(route('perfil.index'))->assertOk();

        foreach (StatusCadastro::cases() as $status) {
            $resposta->assertSee($status->label());
        }

        $resposta->assertSee('Receita incompleta.');
    }
}
