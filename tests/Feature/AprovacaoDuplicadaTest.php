<?php

namespace Tests\Feature;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\CadastroBebida;
use App\Models\CadastroBebidaIngrediente;
use App\Models\User;
use App\Notifications\BebidaModerada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * aprovar() ia de findOrFail() direto para Bebida::create() sem conferir o
 * estado. Duplo clique no botão, ou voltar e reenviar o formulário, criava
 * uma segunda bebida no catálogo — e, depois que o aviso por e-mail e o
 * registro de moderação entraram, mandava dois e-mails e sobrescrevia quem
 * tinha decidido.
 */
class AprovacaoDuplicadaTest extends TestCase
{
    use RefreshDatabase;

    private function pendente(): CadastroBebida
    {
        $cadastro = CadastroBebida::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_bebida' => 'Mojito',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Cubano.',
            'ds_preparo' => 'Macere e complete com rum.',
            'id_status' => StatusCadastro::Pendente,
        ]);

        CadastroBebidaIngrediente::create([
            'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
            'nm_ingrediente' => 'Rum',
            'ds_medida' => '50 ml',
        ]);

        return $cadastro;
    }

    public function test_aprovar_duas_vezes_nao_duplica_no_catalogo(): void
    {
        $admin = User::factory()->create(['id_admin' => true]);
        $cadastro = $this->pendente();

        $this->actingAs($admin)->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));
        $this->actingAs($admin)->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro))
            ->assertRedirect(route('admin.bebidas.index'));

        $this->assertSame(1, Bebida::where('nm_bebida', 'Mojito')->count());
    }

    public function test_segunda_aprovacao_nao_avisa_o_autor_de_novo(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['id_admin' => true]);
        $cadastro = $this->pendente();

        $this->actingAs($admin)->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));
        $this->actingAs($admin)->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));

        Notification::assertSentTimes(BebidaModerada::class, 1);
    }

    public function test_segunda_aprovacao_preserva_quem_decidiu(): void
    {
        $primeiro = User::factory()->create(['id_admin' => true, 'name' => 'Quem decidiu']);
        $segundo = User::factory()->create(['id_admin' => true, 'name' => 'Quem clicou depois']);
        $cadastro = $this->pendente();

        $this->actingAs($primeiro)->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));
        $this->actingAs($segundo)->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));

        $this->assertSame($primeiro->id, $cadastro->fresh()->id_moderador);
    }

    public function test_rejeitar_o_que_ja_foi_aprovado_nao_tira_do_catalogo(): void
    {
        $admin = User::factory()->create(['id_admin' => true]);
        $cadastro = $this->pendente();

        $this->actingAs($admin)->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));
        $this->actingAs($admin)->post(
            route('admin.bebidas.reject', $cadastro->cd_bebida_cadastro),
            ['motivo_rejeicao' => 'Mudei de ideia.']
        )->assertRedirect(route('admin.bebidas.index'));

        $this->assertSame(1, Bebida::where('nm_bebida', 'Mojito')->count());
        $this->assertSame(StatusCadastro::Aprovada, $cadastro->fresh()->id_status);
    }

    public function test_o_admin_e_avisado_de_que_ja_estava_decidida(): void
    {
        $admin = User::factory()->create(['id_admin' => true]);
        $cadastro = $this->pendente();

        $this->actingAs($admin)->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));

        $this->actingAs($admin)
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro))
            ->assertSessionHas('warning');

        // E o aviso precisa chegar à tela: a view só renderizava 'success'.
        $this->actingAs($admin)->get(route('admin.bebidas.index'))
            ->assertOk()
            ->assertSee('já havia sido aprovada');
    }
}
