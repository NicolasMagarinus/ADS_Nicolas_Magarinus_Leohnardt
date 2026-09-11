<?php

namespace Tests\Feature;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\CadastroBebida;
use App\Models\CadastroBebidaIngrediente;
use App\Models\Favorito;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A home é a página mais acessada e roda três GROUP BY sobre o catálogo
 * inteiro a cada visita. O conteúdo delas muda pouco — só quando entra bebida
 * nova ou alguém avalia —, então vale guardar por alguns minutos.
 */
class HomeCacheTest extends TestCase
{
    use RefreshDatabase;

    private function bebida(string $nome): Bebida
    {
        return Bebida::create([
            'nm_bebida' => $nome,
            'ds_preparo' => 'Misture tudo e sirva.',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Teste.',
        ]);
    }

    public function test_home_mostra_as_bebidas_recentes(): void
    {
        $this->bebida('Caipirinha');

        $this->get(route('home'))->assertOk()->assertSee('Caipirinha');
    }

    /**
     * Prova que a segunda visita veio do cache: a bebida é inserida direto no
     * banco, sem passar pela aprovação, então nada invalida as chaves.
     */
    public function test_segunda_visita_nao_refaz_as_consultas(): void
    {
        $this->bebida('Caipirinha');
        $this->get(route('home'))->assertOk();

        $this->bebida('Mojito');

        $this->get(route('home'))->assertOk()->assertDontSee('Mojito');
    }

    /**
     * Só o TTL não bastaria: a bebida recém-aprovada não aparecer por dez
     * minutos é justo quando o moderador vai conferir se funcionou.
     */
    public function test_aprovar_uma_bebida_limpa_o_cache(): void
    {
        $this->bebida('Caipirinha');
        $this->get(route('home'))->assertOk()->assertDontSee('Limonada Suíça');

        $cadastro = CadastroBebida::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_bebida' => 'Limonada Suíça',
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

        $this->actingAs(User::factory()->create(['id_admin' => true]))
            ->post(route('admin.bebidas.approve', $cadastro->cd_bebida_cadastro));

        $this->get(route('home'))->assertOk()->assertSee('Limonada Suíça');
    }

    /**
     * O bloco de recomendadas depende dos favoritos de quem está vendo. Se
     * entrasse no cache junto, a home de uma pessoa mostraria o estado de
     * outra.
     */
    public function test_favoritos_do_usuario_ficam_fora_do_cache(): void
    {
        $bebida = $this->bebida('Caipirinha');

        $comFavorito = User::factory()->create();
        Favorito::create(['id_usuario' => $comFavorito->id, 'cd_bebida' => $bebida->cd_bebida]);
        $semFavorito = User::factory()->create();

        $this->actingAs($comFavorito)->get(route('home'))
            ->assertOk()
            ->assertViewHas('hasFavoritesForRecommend', true);

        $this->actingAs($semFavorito)->get(route('home'))
            ->assertOk()
            ->assertViewHas('hasFavoritesForRecommend', false);
    }
}
