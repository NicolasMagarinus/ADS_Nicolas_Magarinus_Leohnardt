<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Avaliacao;
use App\Models\Bebida;
use App\Models\BebidaIngrediente;
use App\Models\Ingrediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O sorteio montava a consulta completa — join de avaliações, GROUP BY e o
 * json_agg dos ingredientes — para o catálogo inteiro, ordenava tudo por
 * RANDOM() e jogava fora todas as linhas menos uma.
 */
class RandomDrinkTest extends TestCase
{
    use RefreshDatabase;

    private function bebida(string $nome): Bebida
    {
        $bebida = Bebida::create([
            'nm_bebida' => $nome,
            'ds_preparo' => 'Macere a hortelã. Complete com rum.',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Teste.',
        ]);

        BebidaIngrediente::create([
            'cd_bebida' => $bebida->cd_bebida,
            'cd_ingrediente' => Ingrediente::create(['nm_ingrediente' => 'Ingrediente '.$nome])->cd_ingrediente,
            'ds_medida' => '50 ml',
        ]);

        return $bebida;
    }

    public function test_sorteio_devolve_bebida_montada(): void
    {
        $bebida = $this->bebida('Mojito');
        Avaliacao::create([
            'cd_bebida' => $bebida->cd_bebida,
            'id_usuario' => User::factory()->create()->id,
            'id_nota' => 4,
            'ds_avaliacao' => 'Boa.',
        ]);

        $sorteada = Bebida::getBebida();

        $this->assertSame('Mojito', $sorteada->nm_bebida);
        $this->assertSame('Ingrediente Mojito', $sorteada->ingredientes[0]['nm_ingrediente']);
        $this->assertSame('4.0', (string) $sorteada->nota);
        $this->assertSame(1, (int) $sorteada->qt_avaliacao);
        // O preparo continua quebrado em passos pela própria getBebida().
        $this->assertCount(2, $sorteada->preparo);
    }

    public function test_catalogo_vazio_devolve_null(): void
    {
        $this->assertNull(Bebida::getBebida());
    }

    public function test_rota_aleatoria_responde_404_com_catalogo_vazio(): void
    {
        $this->get(route('random'))->assertNotFound();
    }

    /**
     * Sem isto, "aleatório" poderia virar "sempre o primeiro" e ninguém
     * perceberia até alguém reclamar de ver o mesmo drink todo dia.
     */
    public function test_sorteios_repetidos_variam(): void
    {
        foreach (['Mojito', 'Caipirinha', 'Negroni', 'Daiquiri', 'Margarita'] as $nome) {
            $this->bebida($nome);
        }

        $sorteadas = [];
        for ($i = 0; $i < 30; $i++) {
            $sorteadas[] = Bebida::getBebida()->nm_bebida;
        }

        $this->assertGreaterThan(1, count(array_unique($sorteadas)),
            'trinta sorteios devolveram sempre a mesma bebida');
    }

    /**
     * O ponto do item: a consulta pesada nunca pode rodar sem filtro. É esta
     * asserção que impede alguém devolver o ORDER BY RANDOM() para o lugar
     * antigo.
     */
    public function test_consulta_pesada_nunca_roda_sem_filtro(): void
    {
        foreach (['Mojito', 'Caipirinha', 'Negroni'] as $nome) {
            $this->bebida($nome);
        }

        $pesadasSemFiltro = 0;
        DB::listen(function ($consulta) use (&$pesadasSemFiltro) {
            $sql = $consulta->sql;

            if (str_contains($sql, 'json_agg') && ! str_contains($sql, 'WHERE b.cd_bebida = ?')) {
                $pesadasSemFiltro++;
            }
        });

        Bebida::getBebida();

        $this->assertSame(0, $pesadasSemFiltro,
            'a consulta com json_agg rodou sobre o catálogo inteiro');
    }
}
