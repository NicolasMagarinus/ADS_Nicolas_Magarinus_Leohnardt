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
 * As abas de histórico mostravam o updated_at do cadastro, que é só a última
 * escrita na linha, e não diziam quem decidiu. Sem isso não dá para cobrar
 * nem para desfazer uma decisão com conhecimento de causa.
 */
class AuditoriaModeracaoTest extends TestCase
{
    use RefreshDatabase;

    private function pendente(string $nome = 'Limonada Suíça'): CadastroBebida
    {
        $cadastro = CadastroBebida::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_bebida' => $nome,
            'id_tipo' => TipoBebida::NaoAlcoolica,
            'ds_bebida' => 'Refrescante.',
            'ds_preparo' => 'Bata tudo e coe.',
            'id_status' => StatusCadastro::Pendente,
        ]);

        CadastroBebidaIngrediente::create([
            'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
            'nm_ingrediente' => 'Limão',
            'ds_medida' => '2 unidades',
        ]);

        return $cadastro;
    }

    public function test_aprovar_registra_quem_decidiu_e_quando(): void
    {
        $moderador = User::factory()->create(['id_admin' => true, 'name' => 'Ana Moderadora']);
        $cadastro = $this->pendente();

        $this->actingAs($moderador)
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));

        $cadastro = $cadastro->fresh();
        $this->assertSame($moderador->id, $cadastro->id_moderador);
        $this->assertNotNull($cadastro->dt_moderacao);
        $this->assertSame('Ana Moderadora', $cadastro->moderador->name);
    }

    public function test_rejeitar_registra_quem_decidiu_e_quando(): void
    {
        $moderador = User::factory()->create(['id_admin' => true, 'name' => 'Bruno Moderador']);
        $cadastro = $this->pendente();

        $this->actingAs($moderador)->post(
            route('admin.bebidas.reject', $cadastro->cd_bebida_cadastro),
            ['motivo_rejeicao' => 'Preparo incompleto.']
        );

        $cadastro = $cadastro->fresh();
        $this->assertSame($moderador->id, $cadastro->id_moderador);
        $this->assertNotNull($cadastro->dt_moderacao);
    }

    public function test_historico_mostra_o_nome_de_quem_decidiu(): void
    {
        $moderador = User::factory()->create(['id_admin' => true, 'name' => 'Ana Moderadora']);
        $cadastro = $this->pendente();

        $this->actingAs($moderador)
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));

        // A frase inteira, não só o nome: quem está logado é a própria Ana, e
        // o nome dela já aparece no menu do topo — asserção solta passaria
        // mesmo sem nada ter sido registrado.
        $this->actingAs($moderador)
            ->get(route('admin.bebidas.index', ['status' => 'aprovadas']))
            ->assertOk()
            ->assertSee('Moderada por Ana Moderadora');
    }

    /**
     * As receitas decididas antes desta coluna existir ficam com o campo nulo.
     * A tela precisa dizer isso em vez de quebrar.
     */
    public function test_decisao_antiga_sem_moderador_nao_quebra_a_tela(): void
    {
        $cadastro = $this->pendente('Receita antiga');
        $cadastro->update(['id_status' => StatusCadastro::Aprovada]);

        $this->assertNull($cadastro->fresh()->id_moderador);

        $this->actingAs(User::factory()->create(['id_admin' => true]))
            ->get(route('admin.bebidas.index', ['status' => 'aprovadas']))
            ->assertOk()
            ->assertSee('Receita antiga')
            ->assertSee('não registrado');
    }

    /**
     * Apagar um admin não pode levar junto as receitas que ele moderou — e o
     * histórico tem de continuar abrindo.
     */
    public function test_apagar_o_moderador_preserva_o_cadastro(): void
    {
        $moderador = User::factory()->create(['id_admin' => true]);
        $cadastro = $this->pendente();

        $this->actingAs($moderador)
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));

        $moderador->delete();

        $this->assertDatabaseHas('cadastro_bebida', [
            'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
            'id_moderador' => null,
        ]);

        $this->actingAs(User::factory()->create(['id_admin' => true]))
            ->get(route('admin.bebidas.index', ['status' => 'aprovadas']))
            ->assertOk()
            ->assertSee('não registrado');
    }

    public function test_fila_de_pendentes_nao_tem_moderador(): void
    {
        $this->pendente();

        $this->actingAs(User::factory()->create(['id_admin' => true]))
            ->get(route('admin.bebidas.index'))
            ->assertOk()
            ->assertDontSee('Moderada por');
    }
}
