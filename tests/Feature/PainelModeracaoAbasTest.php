<?php

namespace Tests\Feature;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use App\Models\CadastroBebida;
use App\Models\CadastroBebidaIngrediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O painel listava só os pendentes. Depois de aprovar não havia como rever a
 * decisão nem perceber um engano — a receita simplesmente sumia da tela.
 */
class PainelModeracaoAbasTest extends TestCase
{
    use RefreshDatabase;

    private function cadastro(string $nome, StatusCadastro $status, ?string $motivo = null): CadastroBebida
    {
        $cadastro = CadastroBebida::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_bebida' => $nome,
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Teste.',
            'ds_preparo' => 'Misture tudo e sirva.',
            'id_status' => $status,
            'ds_motivo_rejeicao' => $motivo,
        ]);

        CadastroBebidaIngrediente::create([
            'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
            'nm_ingrediente' => 'Cachaça',
            'ds_medida' => '50 ml',
        ]);

        return $cadastro;
    }

    private function catalogo(): void
    {
        $this->cadastro('Receita Pendente', StatusCadastro::Pendente);
        $this->cadastro('Receita Aprovada', StatusCadastro::Aprovada);
        $this->cadastro('Receita Rejeitada', StatusCadastro::Rejeitada, 'Modo de preparo incompleto.');
    }

    private function admin(): User
    {
        return User::factory()->create(['id_admin' => true]);
    }

    private function painel(?string $status = null)
    {
        $url = $status === null
            ? route('admin.bebidas.index')
            : route('admin.bebidas.index', ['status' => $status]);

        return $this->actingAs($this->admin())->get($url);
    }

    public function test_aba_padrao_e_a_fila_de_pendentes(): void
    {
        $this->catalogo();

        $this->painel()
            ->assertOk()
            ->assertSee('Receita Pendente')
            ->assertDontSee('Receita Aprovada')
            ->assertDontSee('Receita Rejeitada');
    }

    public function test_aba_de_aprovadas_mostra_so_aprovadas(): void
    {
        $this->catalogo();

        $this->painel('aprovadas')
            ->assertOk()
            ->assertSee('Receita Aprovada')
            ->assertDontSee('Receita Pendente')
            ->assertDontSee('Receita Rejeitada');
    }

    public function test_aba_de_rejeitadas_mostra_o_motivo(): void
    {
        $this->catalogo();

        $this->painel('rejeitadas')
            ->assertOk()
            ->assertSee('Receita Rejeitada')
            ->assertSee('Modo de preparo incompleto.')
            ->assertDontSee('Receita Pendente');
    }

    /**
     * É URL que a pessoa edita à mão; cair na fila é melhor que dar 404.
     */
    public function test_status_invalido_cai_na_fila_de_pendentes(): void
    {
        $this->catalogo();

        $this->painel('qualquer-coisa')
            ->assertOk()
            ->assertSee('Receita Pendente')
            ->assertDontSee('Receita Aprovada');
    }

    /**
     * Oferecer "aprovar" para o que já foi aprovado convida ao engano.
     */
    public function test_acoes_aparecem_so_na_fila_de_pendentes(): void
    {
        $this->catalogo();

        $this->painel()->assertOk()->assertSee('Aprovar');
        $this->painel('aprovadas')->assertOk()->assertDontSee('Aprovar');
        $this->painel('rejeitadas')->assertOk()->assertDontSee('Aprovar');
    }

    /**
     * As datas são recuadas pelo query builder, e não por $cadastro->update():
     * created_at e updated_at não estão no $fillable do model, então um update
     * de model as descarta em silêncio e as duas linhas ficam com a mesma
     * data — o teste passaria pela ordem de inserção, sem provar nada.
     */
    private function recuar(CadastroBebida $cadastro, string $coluna, int $dias): void
    {
        CadastroBebida::where('cd_bebida_cadastro', $cadastro->cd_bebida_cadastro)
            ->update([$coluna => now()->subDays($dias)]);
    }

    public function test_fila_de_pendentes_comeca_pela_mais_antiga(): void
    {
        // Inserida depois e recuada, para a ordem de inserção não explicar o
        // resultado.
        $this->cadastro('Chegou por último', StatusCadastro::Pendente);
        $this->recuar($this->cadastro('Espera há mais tempo', StatusCadastro::Pendente), 'created_at', 3);

        $html = $this->painel()->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Chegou por último'),
            strpos($html, 'Espera há mais tempo'),
            'a fila não está começando pela receita que espera há mais tempo'
        );
    }

    public function test_historico_comeca_pela_decisao_mais_recente(): void
    {
        $this->cadastro('Decidida agora', StatusCadastro::Aprovada);
        $this->recuar($this->cadastro('Decidida há dias', StatusCadastro::Aprovada), 'updated_at', 3);

        $html = $this->painel('aprovadas')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Decidida há dias'),
            strpos($html, 'Decidida agora'),
            'o histórico não está começando pela decisão mais recente'
        );
    }

    public function test_abas_mostram_quantos_ha_em_cada_estado(): void
    {
        $this->catalogo();
        $this->cadastro('Outra Pendente', StatusCadastro::Pendente);

        $html = $this->painel()->assertOk()->getContent();

        foreach (['Pendentes', 'Aprovadas', 'Rejeitadas'] as $aba) {
            $this->assertStringContainsString($aba, $html);
        }
    }

    public function test_paginacao_preserva_a_aba(): void
    {
        foreach (range(1, 12) as $i) {
            $this->cadastro(sprintf('Aprovada %02d', $i), StatusCadastro::Aprovada);
        }

        $this->painel('aprovadas')
            ->assertOk()
            ->assertSee('status=aprovadas', false);
    }
}
