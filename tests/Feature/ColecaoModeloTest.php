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
 * O slug da URL é derivado do nome, não guardado em coluna: se ele virasse
 * coluna, todo rename teria de ressincronizá-lo, e slug velho no banco é
 * justamente o defeito que a URL híbrida existe para não ter.
 *
 * As duas cascatas também estão aqui porque falham caladas: apagar a conta
 * deixando coleção órfã só aparece quando alguém abre o link meses depois.
 */
class ColecaoModeloTest extends TestCase
{
    use RefreshDatabase;

    private function bebida(string $nome = 'Mojito'): Bebida
    {
        return Bebida::create([
            'nm_bebida' => $nome,
            'ds_preparo' => 'Macere hortelã e limão, complete com rum.',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Cubano.',
        ]);
    }

    private function colecao(User $dono, string $nome = 'Drinks de verão'): Colecao
    {
        return Colecao::create([
            'id_usuario' => $dono->id,
            'nm_colecao' => $nome,
            'id_publica' => true,
        ]);
    }

    public function test_o_parametro_da_url_junta_id_e_slug(): void
    {
        $colecao = $this->colecao(User::factory()->create());

        $this->assertSame('drinks-de-verao', $colecao->slug);
        $this->assertSame($colecao->cd_colecao.'-drinks-de-verao', $colecao->parametroUrl());
    }

    public function test_nome_sem_letra_nenhuma_cai_para_o_id_puro(): void
    {
        // Str::slug('???') devolve '', e "12-" é URL feia com hífen solto.
        $colecao = $this->colecao(User::factory()->create(), '???');

        $this->assertSame('', $colecao->slug);
        $this->assertSame((string) $colecao->cd_colecao, $colecao->parametroUrl());
    }

    public function test_apagar_o_usuario_leva_as_colecoes_junto(): void
    {
        $dono = User::factory()->create();
        $colecao = $this->colecao($dono);
        ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $this->bebida()->cd_bebida]);

        $dono->delete();

        $this->assertDatabaseCount('colecao', 0);
        $this->assertDatabaseCount('colecao_bebida', 0);
    }

    public function test_apagar_a_bebida_a_tira_das_colecoes(): void
    {
        $colecao = $this->colecao(User::factory()->create());
        $bebida = $this->bebida();
        ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $bebida->cd_bebida]);

        $bebida->delete();

        $this->assertDatabaseCount('colecao_bebida', 0);
        $this->assertDatabaseCount('colecao', 1);
    }

    public function test_a_mesma_bebida_nao_entra_duas_vezes_na_colecao(): void
    {
        $colecao = $this->colecao(User::factory()->create());
        $bebida = $this->bebida();
        ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $bebida->cd_bebida]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ColecaoBebida::create(['cd_colecao' => $colecao->cd_colecao, 'cd_bebida' => $bebida->cd_bebida]);
    }
}
