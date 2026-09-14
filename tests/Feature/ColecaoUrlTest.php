<?php

namespace Tests\Feature;

use App\Models\Colecao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A URL é híbrida: /colecao/12-drinks-de-verao. O id é quem resolve, o slug é
 * derivado do nome. Qualquer outra forma redireciona 301 para a canônica, e é
 * isso que faz renomear a coleção não quebrar link já publicado.
 *
 * Quebra em silêncio: com o 301 ausente, as quatro formas respondem 200 e o
 * buscador indexa quatro URLs com o mesmo conteúdo.
 */
class ColecaoUrlTest extends TestCase
{
    use RefreshDatabase;

    private function colecaoPublica(string $nome = 'Drinks de verão'): Colecao
    {
        return Colecao::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_colecao' => $nome,
            'id_publica' => true,
        ]);
    }

    public function test_a_forma_canonica_responde_200(): void
    {
        $colecao = $this->colecaoPublica();

        $this->get('/colecao/'.$colecao->cd_colecao.'-drinks-de-verao')
            ->assertOk()
            ->assertSee('Drinks de verão');
    }

    public function test_so_o_id_redireciona_para_a_canonica(): void
    {
        $colecao = $this->colecaoPublica();

        $this->get('/colecao/'.$colecao->cd_colecao)
            ->assertStatus(301)
            ->assertRedirect($colecao->url());
    }

    public function test_slug_antigo_redireciona_para_a_canonica(): void
    {
        $colecao = $this->colecaoPublica();
        $colecao->update(['nm_colecao' => 'Drinks de inverno']);

        $this->get('/colecao/'.$colecao->cd_colecao.'-drinks-de-verao')
            ->assertStatus(301)
            ->assertRedirect(route('colecao.show', $colecao->cd_colecao.'-drinks-de-inverno'));
    }

    public function test_lixo_no_lugar_do_slug_redireciona_para_a_canonica(): void
    {
        $colecao = $this->colecaoPublica();

        $this->get('/colecao/'.$colecao->cd_colecao.'-qualquer-coisa')
            ->assertStatus(301)
            ->assertRedirect($colecao->url());
    }

    public function test_id_inexistente_da_404(): void
    {
        $this->get('/colecao/999999-o-que-for')->assertNotFound();
    }

    public function test_a_canonica_aponta_para_ela_mesma(): void
    {
        $colecao = $this->colecaoPublica();

        $this->get($colecao->url())
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$colecao->url().'">', false);
    }
}
