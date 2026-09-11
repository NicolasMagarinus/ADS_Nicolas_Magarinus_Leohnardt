<?php

namespace Tests\Feature;

use App\Models\Ingrediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O Meu Bar é a funcionalidade mais original do site e era a única que não
 * guardava nada: os ingredientes viviam na sessão e no localStorage, então
 * trocar de aparelho ou deixar a sessão expirar apagava o bar inteiro.
 * Agora a fonte da verdade é usuario_ingrediente.
 */
class MeuBarPersistenciaTest extends TestCase
{
    use RefreshDatabase;

    private function ingrediente(string $nome): Ingrediente
    {
        return Ingrediente::create(['nm_ingrediente' => $nome]);
    }

    private function salvar(User $usuario, array $ids)
    {
        return $this->actingAs($usuario)
            ->postJson(route('meubar.salvar'), ['ingredientes' => $ids]);
    }

    public function test_bar_sobrevive_a_uma_sessao_nova(): void
    {
        $usuario = User::factory()->create();
        // Nomes sem acento de propósito: a view embute a lista com @json, que
        // escapa não-ASCII (Cachaça vira Cacha\u00e7a), e aí o assertSee
        // passaria ou falharia pelo motivo errado.
        $rum = $this->ingrediente('Rum');
        $gin = $this->ingrediente('Gin');

        $this->salvar($usuario, [$rum->cd_ingrediente, $gin->cd_ingrediente])
            ->assertOk();

        // Sessão nova: é exatamente o que acontece ao trocar de aparelho.
        $this->flushSession();

        $this->actingAs($usuario)->get(route('meubar.index'))
            ->assertOk()
            ->assertSee('Rum')
            ->assertSee('Gin')
            ->assertViewHas('ingredientesSalvos', function ($salvos) {
                // Ordem de adição, que é como as chips aparecem na tela.
                return $salvos->pluck('nm_ingrediente')->all() === ['Rum', 'Gin'];
            });
    }

    public function test_gravar_substitui_o_conjunto(): void
    {
        $usuario = User::factory()->create();
        $cachaca = $this->ingrediente('Cachaça');
        $limao = $this->ingrediente('Limão');
        $hortela = $this->ingrediente('Hortelã');

        $this->salvar($usuario, [$cachaca->cd_ingrediente, $limao->cd_ingrediente]);
        $this->salvar($usuario, [$limao->cd_ingrediente, $hortela->cd_ingrediente])->assertOk();

        $this->assertDatabaseCount('usuario_ingrediente', 2);
        $this->assertDatabaseHas('usuario_ingrediente', [
            'id_usuario' => $usuario->id,
            'cd_ingrediente' => $hortela->cd_ingrediente,
        ]);
        $this->assertDatabaseMissing('usuario_ingrediente', [
            'id_usuario' => $usuario->id,
            'cd_ingrediente' => $cachaca->cd_ingrediente,
        ]);
    }

    public function test_lista_vazia_limpa_o_bar(): void
    {
        $usuario = User::factory()->create();
        $this->salvar($usuario, [$this->ingrediente('Cachaça')->cd_ingrediente]);

        $this->salvar($usuario, [])->assertOk();

        $this->assertDatabaseCount('usuario_ingrediente', 0);
    }

    public function test_um_usuario_nao_ve_o_bar_do_outro(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();

        $this->salvar($ana, [$this->ingrediente('Vodka')->cd_ingrediente]);
        $this->salvar($bruno, [$this->ingrediente('Rum')->cd_ingrediente]);

        $this->actingAs($bruno)->get(route('meubar.index'))
            ->assertOk()
            ->assertSee('Rum')
            ->assertDontSee('Vodka');

        $this->assertDatabaseCount('usuario_ingrediente', 2);
    }

    public function test_ingrediente_repetido_nao_duplica(): void
    {
        $usuario = User::factory()->create();
        $cachaca = $this->ingrediente('Cachaça');

        $this->salvar($usuario, [$cachaca->cd_ingrediente, $cachaca->cd_ingrediente])
            ->assertOk();

        $this->assertDatabaseCount('usuario_ingrediente', 1);
    }

    public function test_ingrediente_inexistente_e_recusado(): void
    {
        $usuario = User::factory()->create();

        $this->salvar($usuario, [999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ingredientes.0');

        $this->assertDatabaseCount('usuario_ingrediente', 0);
    }

    public function test_rota_exige_login(): void
    {
        $this->postJson(route('meubar.salvar'), ['ingredientes' => []])
            ->assertUnauthorized();
    }
}
