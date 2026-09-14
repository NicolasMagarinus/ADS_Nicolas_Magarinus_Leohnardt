<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\Colecao;
use App\Models\ColecaoBebida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O modal é chamado por fetch e espera JSON. Um id que não existe não pode
 * virar violação de chave estrangeira — é o mesmo defeito que o FavoritoTest
 * já cobre para a estrela: o front recebe página de erro onde espera JSON e
 * trava sem dizer nada.
 */
class ColecaoBebidaTest extends TestCase
{
    use RefreshDatabase;

    private function bebida(string $nome = 'Mojito'): Bebida
    {
        return Bebida::create([
            'nm_bebida' => $nome,
            'ds_preparo' => 'Macere e complete com rum.',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Cubano.',
        ]);
    }

    public function test_alterna_a_bebida_nos_dois_sentidos(): void
    {
        $usuario = User::factory()->create();
        $colecao = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);
        $bebida = $this->bebida();

        $this->actingAs($usuario)
            ->postJson(route('colecao.bebida.alternar', [$colecao->cd_colecao, $bebida->cd_bebida]))
            ->assertOk()
            ->assertJson(['contem' => true]);

        $this->assertDatabaseCount('colecao_bebida', 1);

        $this->actingAs($usuario)
            ->postJson(route('colecao.bebida.alternar', [$colecao->cd_colecao, $bebida->cd_bebida]))
            ->assertOk()
            ->assertJson(['contem' => false]);

        $this->assertDatabaseCount('colecao_bebida', 0);
    }

    public function test_bebida_inexistente_devolve_404_em_json(): void
    {
        $usuario = User::factory()->create();
        $colecao = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs($usuario)
            ->postJson(route('colecao.bebida.alternar', [$colecao->cd_colecao, 999999]))
            ->assertNotFound()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('colecao_bebida', 0);
    }

    public function test_nao_escreve_na_colecao_de_outra_pessoa(): void
    {
        $colecao = Colecao::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_colecao' => 'Drinks de verão',
        ]);
        $bebida = $this->bebida();

        $this->actingAs(User::factory()->create())
            ->postJson(route('colecao.bebida.alternar', [$colecao->cd_colecao, $bebida->cd_bebida]))
            ->assertNotFound();

        $this->assertDatabaseCount('colecao_bebida', 0);
    }

    public function test_lista_as_colecoes_marcando_quais_contem_a_bebida(): void
    {
        $usuario = User::factory()->create();
        $bebida = $this->bebida();
        $com = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Com o drink']);
        $sem = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Sem o drink']);
        ColecaoBebida::create(['cd_colecao' => $com->cd_colecao, 'cd_bebida' => $bebida->cd_bebida]);

        $this->actingAs($usuario)
            ->getJson(route('colecao.para-bebida', $bebida->cd_bebida))
            ->assertOk()
            ->assertJsonCount(2, 'colecoes')
            ->assertJsonFragment(['cd_colecao' => $com->cd_colecao, 'nm_colecao' => 'Com o drink', 'contem' => true])
            ->assertJsonFragment(['cd_colecao' => $sem->cd_colecao, 'nm_colecao' => 'Sem o drink', 'contem' => false]);
    }

    public function test_visitante_nao_alterna(): void
    {
        $colecao = Colecao::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_colecao' => 'Drinks de verão',
        ]);

        $this->postJson(route('colecao.bebida.alternar', [$colecao->cd_colecao, $this->bebida()->cd_bebida]))
            ->assertUnauthorized();
    }

    /**
     * Mesmo defeito de faixa que ColecaoCrudTest já cobre para update/destroy
     * (cd_colecao e cd_bebida são INTEGER no Postgres, máx. 2147483647, mas
     * as rotas só exigem "[0-9]+"): paraBebida() e alternarBebida() recebem
     * os dois ids pela URL, então precisam da mesma checagem manual de faixa
     * que minhaColecao() já faz — em vez de tipar os parâmetros como `int`,
     * o que faria o PHP lançar TypeError (500) antes do método rodar num id
     * de vinte dígitos, que nem cabe no int64 do PHP.
     */
    public function test_id_de_colecao_acima_da_faixa_do_integer_da_404_no_alternar(): void
    {
        $usuario = User::factory()->create();
        $bebida = $this->bebida();

        $this->actingAs($usuario)
            ->postJson('/colecao/9999999999/bebida/'.$bebida->cd_bebida.'/alternar')
            ->assertNotFound();

        $this->assertDatabaseCount('colecao_bebida', 0);
    }

    public function test_id_de_colecao_maior_que_o_int64_do_php_da_404_no_alternar(): void
    {
        $usuario = User::factory()->create();
        $bebida = $this->bebida();

        $this->actingAs($usuario)
            ->postJson('/colecao/99999999999999999999/bebida/'.$bebida->cd_bebida.'/alternar')
            ->assertNotFound();

        $this->assertDatabaseCount('colecao_bebida', 0);
    }

    public function test_id_de_bebida_acima_da_faixa_do_integer_da_404_no_alternar(): void
    {
        $usuario = User::factory()->create();
        $colecao = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs($usuario)
            ->postJson('/colecao/'.$colecao->cd_colecao.'/bebida/9999999999/alternar')
            ->assertNotFound();

        $this->assertDatabaseCount('colecao_bebida', 0);
    }

    public function test_id_de_bebida_maior_que_o_int64_do_php_da_404_no_alternar(): void
    {
        $usuario = User::factory()->create();
        $colecao = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs($usuario)
            ->postJson('/colecao/'.$colecao->cd_colecao.'/bebida/99999999999999999999/alternar')
            ->assertNotFound();

        $this->assertDatabaseCount('colecao_bebida', 0);
    }

    public function test_id_de_bebida_acima_da_faixa_do_integer_da_404_no_para_bebida(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->getJson('/colecoes/para-bebida/9999999999')
            ->assertNotFound();
    }

    public function test_id_de_bebida_maior_que_o_int64_do_php_da_404_no_para_bebida(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->getJson('/colecoes/para-bebida/99999999999999999999')
            ->assertNotFound();
    }

    /**
     * Toda outra visita a bebida.show na suíte é anônima, então o bloco de
     * autenticação que inclui o modal, o script de config e a diretiva que
     * carrega colecao.js nunca era renderizado por nenhum teste — um erro
     * de compilação Blade ali só apareceria para quem estivesse logado de
     * verdade.
     */
    public function test_bebida_autenticado_recebe_modal_e_config_da_colecao(): void
    {
        $usuario = User::factory()->create();
        $bebida = $this->bebida();

        $this->actingAs($usuario)
            ->get(route('bebida.show', $bebida->cd_bebida))
            ->assertOk()
            ->assertSee('id="colecaoModal"', false)
            ->assertSee('DrinkeritoColecao', false);
    }

    public function test_visitante_nao_recebe_modal_nem_config_da_colecao(): void
    {
        $bebida = $this->bebida();

        $this->get(route('bebida.show', $bebida->cd_bebida))
            ->assertOk()
            ->assertDontSee('id="colecaoModal"', false)
            ->assertDontSee('DrinkeritoColecao', false);
    }
}
