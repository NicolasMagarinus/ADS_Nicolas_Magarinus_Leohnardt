<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Avaliacao;
use App\Models\Bebida;
use App\Models\BebidaIngrediente;
use App\Models\Ingrediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filtrar por "não alcoólica" dependia de um regex adivinhando a intenção no
 * texto digitado — o chatbot chegava a instruir a frase exata, e qualquer
 * variação caía na busca textual sem filtrar nada. Agora são parâmetros.
 */
class BuscaFiltrosTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Ingrediente> */
    private array $ingredientes = [];

    private function ingrediente(string $nome): Ingrediente
    {
        return $this->ingredientes[$nome] ??= Ingrediente::create(['nm_ingrediente' => $nome]);
    }

    private function bebida(string $nome, TipoBebida $tipo, array $ingredientes = [], array $notas = []): Bebida
    {
        $bebida = Bebida::create([
            'nm_bebida' => $nome,
            'ds_preparo' => 'Misture tudo e sirva.',
            'id_tipo' => $tipo,
            'ds_bebida' => 'Teste.',
        ]);

        foreach ($ingredientes as $nomeIngrediente) {
            BebidaIngrediente::create([
                'cd_bebida' => $bebida->cd_bebida,
                'cd_ingrediente' => $this->ingrediente($nomeIngrediente)->cd_ingrediente,
                'ds_medida' => '50 ml',
            ]);
        }

        foreach ($notas as $nota) {
            Avaliacao::create([
                'cd_bebida' => $bebida->cd_bebida,
                'id_usuario' => User::factory()->create()->id,
                'id_nota' => $nota,
                'ds_avaliacao' => 'Teste.',
            ]);
        }

        return $bebida;
    }

    private function catalogo(): void
    {
        $this->bebida('Caipirinha', TipoBebida::Alcoolica, ['Cachaca', 'Limao', 'Acucar'], [5, 5]);
        $this->bebida('Mojito', TipoBebida::Alcoolica, ['Rum', 'Hortela', 'Limao', 'Acucar', 'Soda'], [3, 3]);
        $this->bebida('Limonada', TipoBebida::NaoAlcoolica, ['Limao', 'Acucar'], [5, 5]);
        $this->bebida('Suco de Uva', TipoBebida::NaoAlcoolica, ['Uva'], []);
    }

    public function test_filtra_por_tipo(): void
    {
        $this->catalogo();

        $this->get(route('search', ['tipo' => TipoBebida::NaoAlcoolica->value]))
            ->assertOk()
            ->assertSee('Limonada')
            ->assertSee('Suco de Uva')
            ->assertDontSee('Caipirinha')
            ->assertDontSee('Mojito');
    }

    public function test_filtra_por_nota_minima(): void
    {
        $this->catalogo();

        $this->get(route('search', ['nota' => 4]))
            ->assertOk()
            ->assertSee('Caipirinha')
            ->assertSee('Limonada')
            ->assertDontSee('Mojito')
            // Sem avaliação nenhuma não passa num filtro de nota mínima.
            ->assertDontSee('Suco de Uva');
    }

    public function test_filtra_por_numero_maximo_de_ingredientes(): void
    {
        $this->catalogo();

        $this->get(route('search', ['max_ingredientes' => 3]))
            ->assertOk()
            ->assertSee('Caipirinha')
            ->assertSee('Limonada')
            ->assertSee('Suco de Uva')
            ->assertDontSee('Mojito');
    }

    public function test_filtra_por_ingrediente(): void
    {
        $this->catalogo();

        $this->get(route('search', ['ingrediente' => $this->ingrediente('Hortela')->cd_ingrediente]))
            ->assertOk()
            ->assertSee('Mojito')
            ->assertDontSee('Caipirinha')
            ->assertDontSee('Limonada');
    }

    public function test_filtros_combinam_entre_si(): void
    {
        $this->catalogo();

        $this->get(route('search', [
            'tipo' => TipoBebida::NaoAlcoolica->value,
            'nota' => 4,
            'max_ingredientes' => 3,
            'ingrediente' => $this->ingrediente('Limao')->cd_ingrediente,
        ]))
            ->assertOk()
            ->assertSee('Limonada')
            ->assertDontSee('Caipirinha')
            ->assertDontSee('Suco de Uva')
            ->assertDontSee('Mojito');
    }

    public function test_filtro_combina_com_o_termo_digitado(): void
    {
        $this->catalogo();

        $this->get(route('search', ['q' => 'limao', 'tipo' => TipoBebida::NaoAlcoolica->value]))
            ->assertOk()
            ->assertSee('Limonada')
            ->assertDontSee('Caipirinha');
    }

    public function test_valor_invalido_e_ignorado_em_vez_de_quebrar(): void
    {
        $this->catalogo();

        $this->get(route('search', ['tipo' => 9, 'nota' => 'abc', 'max_ingredientes' => -1, 'ingrediente' => 'xyz']))
            ->assertOk()
            ->assertSee('Caipirinha')
            ->assertSee('Limonada');
    }

    public function test_contagem_de_avaliacoes_nao_infla_com_o_filtro_de_ingredientes(): void
    {
        // Caipirinha tem 3 ingredientes e 2 avaliações. Se a contagem de
        // ingredientes virasse mais um join, cada avaliação apareceria três
        // vezes e a tela mostraria 6 avaliações.
        $this->catalogo();

        $this->get(route('search', ['max_ingredientes' => 3, 'q' => 'Caipirinha']))
            ->assertOk()
            ->assertSee('Caipirinha')
            ->assertViewHas('bebidas', function ($bebidas) {
                $caipirinha = $bebidas->firstWhere('nm_bebida', 'Caipirinha');

                return (int) $caipirinha->qt_avaliacao === 2 && (float) $caipirinha->nota === 5.0;
            });
    }

    public function test_frase_antiga_redireciona_para_o_filtro(): void
    {
        $this->get(route('search', ['q' => 'não alcoólica']))
            ->assertRedirect(route('search', ['tipo' => TipoBebida::NaoAlcoolica->value]));

        $this->get(route('search', ['q' => 'alcoolica']))
            ->assertRedirect(route('search', ['tipo' => TipoBebida::Alcoolica->value]));
    }

    public function test_paginacao_preserva_os_filtros(): void
    {
        foreach (range(1, 13) as $i) {
            $this->bebida(sprintf('Refresco %02d', $i), TipoBebida::NaoAlcoolica, ['Agua']);
        }
        $this->bebida('Caipirinha', TipoBebida::Alcoolica, ['Cachaca']);

        $this->get(route('search', ['tipo' => TipoBebida::NaoAlcoolica->value, 'page' => 2]))
            ->assertOk()
            ->assertDontSee('Caipirinha')
            ->assertSee('tipo='.TipoBebida::NaoAlcoolica->value, false);
    }

    /**
     * O chatbot mandava "filtre por tipo Não alcoólico", um filtro que não
     * existia: a pessoa tinha de adivinhar que era para digitar a frase no
     * campo de texto. Agora o link já leva filtrado.
     */
    public function test_chatbot_linka_direto_para_a_busca_filtrada(): void
    {
        $resposta = $this->actingAs(User::factory()->create())
            ->postJson(route('chatbot.message'), ['message' => 'quero algo sem alcool'])
            ->assertOk()
            ->assertJson(['source' => 'faq']);

        $this->assertStringContainsString(
            '/search?tipo='.TipoBebida::NaoAlcoolica->value,
            $resposta->json('reply')
        );
    }

    /**
     * É o que o próprio formulário manda quando a pessoa clica em Filtrar sem
     * escolher nada: todos os parâmetros presentes e vazios.
     */
    public function test_parametros_vazios_nao_filtram_nada(): void
    {
        $this->catalogo();

        $this->get(route('search').'?q=&tipo=&nota=&max_ingredientes=&ingrediente=')
            ->assertOk()
            ->assertSee('Caipirinha')
            ->assertSee('Mojito')
            ->assertSee('Limonada')
            ->assertSee('Suco de Uva');
    }

    public function test_formulario_oferece_os_filtros(): void
    {
        $this->catalogo();

        $this->get(route('search'))
            ->assertOk()
            ->assertSee('name="tipo"', false)
            ->assertSee('name="nota"', false)
            ->assertSee('name="max_ingredientes"', false)
            ->assertSee('name="ingrediente"', false)
            // O ingrediente vem do catálogo, ordenado por uso.
            ->assertSee('Limao')
            // O aviso que mandava digitar a frase sai de cena.
            ->assertDontSee('não alcoólica</strong>', false);
    }
}
