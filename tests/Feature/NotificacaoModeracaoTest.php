<?php

namespace Tests\Feature;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use App\Models\CadastroBebida;
use App\Models\CadastroBebidaIngrediente;
use App\Models\User;
use App\Notifications\BebidaModerada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * O usuário enviava uma receita e nunca mais era avisado: precisava lembrar
 * de voltar ao perfil por conta própria. O motivo da rejeição já era gravado
 * e exibido lá — só faltava ele descobrir que existia.
 */
class NotificacaoModeracaoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['id_admin' => true]);
    }

    private function cadastroDe(User $autor): CadastroBebida
    {
        $cadastro = CadastroBebida::create([
            'id_usuario' => $autor->id,
            'nm_bebida' => 'Limonada Suíça',
            'id_tipo' => TipoBebida::NaoAlcoolica,
            'ds_bebida' => 'Refrescante.',
            'ds_preparo' => 'Bata tudo no liquidificador e coe.',
            'id_status' => StatusCadastro::Pendente,
        ]);

        CadastroBebidaIngrediente::create([
            'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
            'nm_ingrediente' => 'Limão',
            'ds_medida' => '2 unidades',
        ]);

        return $cadastro;
    }

    public function test_aprovar_avisa_o_autor(): void
    {
        Notification::fake();

        $autor = User::factory()->create();
        $cadastro = $this->cadastroDe($autor);

        $this->actingAs($this->admin())
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));

        Notification::assertSentTo($autor, BebidaModerada::class,
            fn (BebidaModerada $n) => $n->cadastro->id_status === StatusCadastro::Aprovada);
    }

    public function test_rejeitar_avisa_o_autor_com_o_motivo(): void
    {
        Notification::fake();

        $autor = User::factory()->create();
        $cadastro = $this->cadastroDe($autor);

        $this->actingAs($this->admin())->post(
            route('admin.bebidas.reject', $cadastro->cd_bebida_cadastro),
            ['motivo_rejeicao' => 'O modo de preparo está incompleto.']
        );

        Notification::assertSentTo($autor, BebidaModerada::class, function (BebidaModerada $n) {
            return $n->cadastro->id_status === StatusCadastro::Rejeitada
                && $n->cadastro->ds_motivo_rejeicao === 'O modo de preparo está incompleto.';
        });
    }

    public function test_o_admin_nao_recebe_aviso(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $cadastro = $this->cadastroDe(User::factory()->create());

        $this->actingAs($admin)->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));

        Notification::assertNotSentTo($admin, BebidaModerada::class);
    }

    /**
     * avisarAutor() lê $cadastro->usuario sem conferir se veio algo, e isso só
     * é seguro por causa do esquema: id_usuario é NOT NULL e a FK apaga em
     * cascata. Se alguém afrouxar qualquer um dos dois, este teste cai antes
     * de a moderação começar a estourar em produção.
     */
    public function test_apagar_o_usuario_leva_os_cadastros_dele_junto(): void
    {
        $autor = User::factory()->create();
        $cadastro = $this->cadastroDe($autor);

        $autor->delete();

        $this->assertDatabaseMissing('cadastro_bebida', [
            'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
        ]);
    }

    /**
     * A bebida já entrou no catálogo quando o e-mail sai. Um erro de SMTP não
     * pode desfazer isso nem devolver 500 ao admin, que tentaria aprovar de
     * novo algo que já está aprovado.
     */
    public function test_falha_no_envio_nao_desfaz_a_aprovacao(): void
    {
        $autor = User::factory()->create();
        $cadastro = $this->cadastroDe($autor);

        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP fora do ar'));

        $this->actingAs($this->admin())
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro))
            ->assertRedirect(route('admin.bebidas.index'));

        $this->assertDatabaseHas('bebida', ['nm_bebida' => 'Limonada Suíça']);
        $this->assertDatabaseHas('cadastro_bebida', [
            'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
            'id_status' => StatusCadastro::Aprovada->value,
        ]);
    }

    public function test_falha_no_envio_nao_desfaz_a_rejeicao(): void
    {
        $autor = User::factory()->create();
        $cadastro = $this->cadastroDe($autor);

        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP fora do ar'));

        $this->actingAs($this->admin())->post(
            route('admin.bebidas.reject', $cadastro->cd_bebida_cadastro),
            ['motivo_rejeicao' => 'Receita duplicada.']
        )->assertRedirect(route('admin.bebidas.index'));

        $this->assertDatabaseHas('cadastro_bebida', [
            'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
            'id_status' => StatusCadastro::Rejeitada->value,
        ]);
    }

    /**
     * Notification::fake() intercepta antes de montar a mensagem, então nunca
     * pega template quebrado nem variável faltando — a mesma armadilha que o
     * RecuperacaoSenhaTest já documenta para Mail::fake(). Aqui a notificação
     * é renderizada de verdade.
     */
    public function test_email_de_aprovacao_e_montado_de_verdade(): void
    {
        $autor = User::factory()->create();
        $cadastro = $this->cadastroDe($autor);
        $cadastro->update(['id_status' => StatusCadastro::Aprovada]);

        $corpo = (new BebidaModerada($cadastro, 7))->toMail($autor)->render();

        $this->assertStringContainsString('Limonada Suíça', $corpo);
        $this->assertStringContainsString(route('bebida.show', 7), $corpo);
    }

    public function test_email_de_rejeicao_mostra_o_motivo(): void
    {
        $autor = User::factory()->create();
        $cadastro = $this->cadastroDe($autor);
        $cadastro->update([
            'id_status' => StatusCadastro::Rejeitada,
            'ds_motivo_rejeicao' => 'O modo de preparo está incompleto.',
        ]);

        $corpo = (new BebidaModerada($cadastro))->toMail($autor)->render();

        $this->assertStringContainsString('Limonada Suíça', $corpo);
        $this->assertStringContainsString('O modo de preparo está incompleto.', $corpo);
        $this->assertStringContainsString(route('perfil.index'), $corpo);
    }
}
