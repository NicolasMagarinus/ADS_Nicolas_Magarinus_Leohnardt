<?php

namespace Tests\Feature;

use App\Models\Bebida;
use App\Models\BebidaIngrediente;
use App\Models\Ingrediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O casamento do Meu Bar é a consulta mais específica do projeto: usa
 * FILTER (WHERE ...) e = ANY(?) do PostgreSQL para contar, numa passada,
 * quantos ingredientes de cada receita o usuário tem em casa.
 */
class MeuBarTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Ingrediente> */
    private array $ingredientes = [];

    private function ingrediente(string $nome): Ingrediente
    {
        return $this->ingredientes[$nome] ??= Ingrediente::create(['nm_ingrediente' => $nome]);
    }

    /** @param array<int, string> $ingredientes */
    private function bebida(string $nome, array $ingredientes, int $tipo = 1): Bebida
    {
        $bebida = Bebida::create([
            'nm_bebida' => $nome,
            'ds_preparo' => 'Misture tudo e sirva.',
            'id_tipo' => $tipo,
            'ds_bebida' => 'Teste',
        ]);

        foreach ($ingredientes as $nomeIngrediente) {
            BebidaIngrediente::create([
                'cd_bebida' => $bebida->cd_bebida,
                'cd_ingrediente' => $this->ingrediente($nomeIngrediente)->cd_ingrediente,
                'ds_medida' => '50 ml',
            ]);
        }

        return $bebida;
    }

    /** @param array<int, string> $despensa */
    private function consultar(array $despensa)
    {
        $ids = array_map(fn (string $nome) => $this->ingrediente($nome)->cd_ingrediente, $despensa);

        return $this->actingAs(User::factory()->create())
            ->postJson(route('meubar.drinks'), ['ingredientes' => $ids]);
    }

    public function test_separa_o_que_da_para_fazer_do_que_ainda_falta(): void
    {
        $this->bebida('Caipirinha', ['Cachaça', 'Limão', 'Açúcar']);
        $this->bebida('Mojito', ['Rum branco', 'Limão', 'Hortelã', 'Água com gás']);
        $this->bebida('Negroni', ['Gin', 'Campari', 'Vermute tinto']);

        $resposta = $this->consultar(['Cachaça', 'Limão', 'Açúcar', 'Rum branco', 'Hortelã']);

        $resposta->assertOk();

        $prontos = array_column($resposta->json('prontos'), 'nm_bebida');
        $quaseLa = array_column($resposta->json('quase_la'), 'nm_bebida');

        $this->assertSame(['Caipirinha'], $prontos);
        $this->assertSame(['Mojito'], $quaseLa, 'falta 1 ingrediente, entra em "quase lá"');
        $this->assertNotContains('Negroni', array_merge($prontos, $quaseLa), 'sem nenhum ingrediente em comum');
    }

    public function test_diz_quais_ingredientes_faltam(): void
    {
        $this->bebida('Mojito', ['Rum branco', 'Limão', 'Hortelã', 'Água com gás']);

        $resposta = $this->consultar(['Rum branco', 'Limão']);

        $faltando = $resposta->json('quase_la.0.ingredientes_faltando');

        sort($faltando);
        $this->assertSame(['Hortelã', 'Água com gás'], $faltando);
    }

    public function test_ignora_receitas_faltando_mais_de_dois_ingredientes(): void
    {
        $this->bebida('Drink Complexo', ['Gin', 'Campari', 'Vermute tinto', 'Laranja', 'Gelo']);

        $resposta = $this->consultar(['Gin', 'Campari']);

        $resposta->assertOk()
            ->assertJsonCount(0, 'prontos')
            ->assertJsonCount(0, 'quase_la');
    }

    public function test_ordena_por_nota_quando_falta_o_mesmo_tanto(): void
    {
        $boa = $this->bebida('Bem Avaliada', ['Cachaça', 'Limão']);
        $ruim = $this->bebida('Mal Avaliada', ['Cachaça', 'Limão']);

        foreach ([[$boa, 5], [$ruim, 2]] as [$bebida, $nota]) {
            \App\Models\Avaliacao::create([
                'cd_bebida' => $bebida->cd_bebida,
                'id_usuario' => User::factory()->create()->id,
                'id_nota' => $nota,
                'ds_avaliacao' => 'teste',
                'dt_avaliacao' => now(),
            ]);
        }

        $resposta = $this->consultar(['Cachaça', 'Limão']);

        $this->assertSame(
            ['Bem Avaliada', 'Mal Avaliada'],
            array_column($resposta->json('prontos'), 'nm_bebida')
        );
    }

    public function test_exige_autenticacao(): void
    {
        $this->postJson(route('meubar.drinks'), ['ingredientes' => [1]])->assertStatus(401);
    }
}
