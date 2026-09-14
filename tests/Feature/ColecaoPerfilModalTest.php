<?php

namespace Tests\Feature;

use App\Models\Colecao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o retorno de erro dos modais de coleção no perfil: old('colecao_form'),
 * os blocos $sendoEditada/$criandoColecao e o script que reabre o modal certo
 * depois do redirect. Foi a correção que motivou um commit inteiro
 * ("Fix round 1: faixa de id em update/destroy, retorno de erro nos modais e
 * edição de coleção") e tinha ficado sem teste — sem ele, um erro de
 * validação (nome duplicado, por exemplo) mandava a pessoa de volta ao
 * perfil sem sinal nenhum de qual formulário falhou.
 */
class ColecaoPerfilModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_erro_ao_criar_colecao_reabre_o_modal_de_criacao_com_a_mensagem(): void
    {
        $usuario = User::factory()->create();
        Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);

        $this->actingAs($usuario)
            ->post(route('colecao.store'), [
                'colecao_form' => 'novo',
                'nm_colecao' => 'Drinks de verão',
            ])
            ->assertSessionHasErrors('nm_colecao');

        // A mesma sessão carrega os erros e o old() para a próxima
        // requisição, que é exatamente o que a tela do perfil lê.
        $this->get(route('perfil.index'))
            ->assertOk()
            ->assertSee('Você já tem uma coleção com esse nome.')
            // O script inline só decide abrir #novaColecaoModal porque
            // old('colecao_form') veio 'novo' — é essa variável que prova
            // que o modal certo (o de criação, não o de editar uma das
            // coleções existentes) é quem reabre.
            ->assertSee('var alvo = "novo";', false);
    }

    public function test_erro_ao_editar_colecao_reabre_o_modal_daquela_colecao_com_a_mensagem(): void
    {
        $usuario = User::factory()->create();
        $alvo = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de inverno']);
        $outra = Colecao::create(['id_usuario' => $usuario->id, 'nm_colecao' => 'Drinks de verão']);

        // Renomear $alvo para o nome que $outra já tem.
        $this->actingAs($usuario)
            ->put(route('colecao.update', $alvo->cd_colecao), [
                'colecao_form' => 'editar-'.$alvo->cd_colecao,
                'nm_colecao' => 'Drinks de verão',
            ])
            ->assertSessionHasErrors('nm_colecao');

        $resposta = $this->get(route('perfil.index'))
            ->assertOk()
            ->assertSee('Você já tem uma coleção com esse nome.')
            // Prova que é o modal de $alvo que reabre, não o de $outra nem
            // o de criação.
            ->assertSee('var alvo = "editar-'.$alvo->cd_colecao.'";', false);

        // O nome ainda não persistiu: a validação recusou antes do update().
        $this->assertSame('Drinks de inverno', $alvo->fresh()->nm_colecao);

        // $sendoEditada compara old('colecao_form') com 'editar-'.$colecao->
        // cd_colecao coleção por coleção: só o campo de $alvo pode ganhar
        // is-invalid. Se a comparação fosse só "há erro? há old?" sem olhar
        // o id, o modal de $outra ficaria marcado também.
        $html = $resposta->getContent();

        preg_match('/<input[^>]*id="nm_colecao_'.$alvo->cd_colecao.'"[^>]*>/', $html, $campoAlvo);
        preg_match('/<input[^>]*id="nm_colecao_'.$outra->cd_colecao.'"[^>]*>/', $html, $campoOutra);

        $this->assertNotEmpty($campoAlvo, 'campo de nome da coleção editada não apareceu na resposta');
        $this->assertStringContainsString('is-invalid', $campoAlvo[0]);

        $this->assertNotEmpty($campoOutra, 'campo de nome da outra coleção não apareceu na resposta');
        $this->assertStringNotContainsString('is-invalid', $campoOutra[0]);
    }
}
