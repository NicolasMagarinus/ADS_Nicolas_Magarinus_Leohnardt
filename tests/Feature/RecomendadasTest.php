<?php

namespace Tests\Feature;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\BebidaIngrediente;
use App\Models\Favorito;
use App\Models\Ingrediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A tela de recomendadas é a consulta mais pesada do projeto e não tinha teste
 * nenhum: ela tira os cinco ingredientes mais frequentes nos favoritos da
 * pessoa e busca drinks parecidos, tudo em SQL cru com `= ANY(?)` e `<> ALL(?)`
 * sobre literais de array do Postgres montados à mão.
 *
 * É SQL que quebra calado. Se o `<> ALL(?)` parar de funcionar, a tela passa a
 * recomendar justamente o que a pessoa já favoritou — e continua respondendo
 * 200, com cards na tela, sem erro em log nenhum. Foi o que motivou este
 * arquivo: uma passada de formatação tocou nessa string e a suíte não tinha
 * como dizer se algo mudou.
 */
class RecomendadasTest extends TestCase
{
    use RefreshDatabase;

    private function ingrediente(string $nome): Ingrediente
    {
        return Ingrediente::create(['nm_ingrediente' => $nome]);
    }

    /** @param  Ingrediente[]  $ingredientes */
    private function bebida(string $nome, array $ingredientes): Bebida
    {
        $bebida = Bebida::create([
            'nm_bebida' => $nome,
            'ds_preparo' => 'Misture tudo e sirva.',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Teste.',
        ]);

        foreach ($ingredientes as $ingrediente) {
            BebidaIngrediente::create([
                'cd_bebida' => $bebida->cd_bebida,
                'cd_ingrediente' => $ingrediente->cd_ingrediente,
                'ds_medida' => '50 ml',
            ]);
        }

        return $bebida;
    }

    /**
     * Cenário compartilhado: a pessoa favoritou o Mojito (rum, hortelã, limão).
     *
     * O Daiquiri divide dois desses ingredientes, a Caipirinha divide um, e a
     * Gin Tônica não divide nenhum. O Mojito em si não pode voltar como
     * recomendação — já está nos favoritos.
     */
    private function cenario(User $usuario): array
    {
        $rum = $this->ingrediente('Rum');
        $hortela = $this->ingrediente('Hortelã');
        $limao = $this->ingrediente('Limão');
        $acucar = $this->ingrediente('Açúcar');
        $gin = $this->ingrediente('Gin');

        $mojito = $this->bebida('Mojito', [$rum, $hortela, $limao]);
        $daiquiri = $this->bebida('Daiquiri', [$rum, $limao, $acucar]);
        $caipirinha = $this->bebida('Caipirinha', [$limao, $acucar]);
        $ginTonica = $this->bebida('Gin Tônica', [$gin]);

        Favorito::create(['id_usuario' => $usuario->id, 'cd_bebida' => $mojito->cd_bebida]);

        return compact('mojito', 'daiquiri', 'caipirinha', 'ginTonica');
    }

    public function test_exige_login(): void
    {
        $this->get(route('recomendadas.index'))->assertRedirect(route('login'));
    }

    public function test_sem_favoritos_a_tela_avisa_em_vez_de_recomendar(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('recomendadas.index'))
            ->assertOk()
            ->assertViewHas('hasFavorites', false)
            ->assertViewHas('recomendadas', fn ($r) => $r->isEmpty());
    }

    /**
     * O `<> ALL(?)` da consulta. Sem ele a tela recomenda o que a pessoa já
     * favoritou, respondendo 200 e com cards na tela — quebra invisível.
     */
    public function test_nao_recomenda_bebida_que_ja_esta_nos_favoritos(): void
    {
        $usuario = User::factory()->create();
        $cenario = $this->cenario($usuario);

        $this->actingAs($usuario)
            ->get(route('recomendadas.index'))
            ->assertOk()
            ->assertViewHas('recomendadas', function ($recomendadas) use ($cenario) {
                return ! $recomendadas->contains('cd_bebida', $cenario['mojito']->cd_bebida);
            });
    }

    /**
     * O `HAVING ... > 0`: drink que não divide ingrediente nenhum com os
     * favoritos não é recomendação, é ruído.
     */
    public function test_nao_recomenda_bebida_sem_ingrediente_em_comum(): void
    {
        $usuario = User::factory()->create();
        $cenario = $this->cenario($usuario);

        $this->actingAs($usuario)
            ->get(route('recomendadas.index'))
            ->assertOk()
            ->assertViewHas('recomendadas', function ($recomendadas) use ($cenario) {
                return ! $recomendadas->contains('cd_bebida', $cenario['ginTonica']->cd_bebida);
            });
    }

    /**
     * O `ORDER BY match_count DESC`: quem divide mais ingredientes vem antes.
     */
    public function test_ordena_por_quantidade_de_ingredientes_em_comum(): void
    {
        $usuario = User::factory()->create();
        $cenario = $this->cenario($usuario);

        $this->actingAs($usuario)
            ->get(route('recomendadas.index'))
            ->assertOk()
            ->assertViewHas('recomendadas', function ($recomendadas) use ($cenario) {
                $ids = $recomendadas->pluck('cd_bebida')->all();

                return $ids === [
                    $cenario['daiquiri']->cd_bebida,   // divide rum e limão
                    $cenario['caipirinha']->cd_bebida, // divide só limão
                ];
            });
    }

    /**
     * Os cinco ingredientes mais frequentes nos favoritos alimentam a consulta
     * e também aparecem na tela, então precisam sair contados e ordenados.
     */
    public function test_traz_os_ingredientes_mais_frequentes_dos_favoritos(): void
    {
        $usuario = User::factory()->create();

        $rum = $this->ingrediente('Rum');
        $limao = $this->ingrediente('Limão');
        $hortela = $this->ingrediente('Hortelã');

        // Rum nas duas favoritas, limão nas duas, hortelã só numa.
        foreach ([
            $this->bebida('Mojito', [$rum, $limao, $hortela]),
            $this->bebida('Daiquiri', [$rum, $limao]),
        ] as $favorita) {
            Favorito::create(['id_usuario' => $usuario->id, 'cd_bebida' => $favorita->cd_bebida]);
        }

        $this->actingAs($usuario)
            ->get(route('recomendadas.index'))
            ->assertOk()
            ->assertViewHas('topIngredientes', function ($top) use ($hortela) {
                $frequencias = $top->pluck('frequencia', 'nm_ingrediente')->all();

                return $frequencias['Rum'] === 2
                    && $frequencias['Limão'] === 2
                    && $frequencias['Hortelã'] === 1
                    // hortelã é o menos frequente, então vem por último
                    && $top->last()->cd_ingrediente === $hortela->cd_ingrediente;
            });
    }

    /**
     * Favoritou algo que não tem ingrediente cadastrado: não há de onde tirar
     * recomendação, mas a tela precisa distinguir isso de "não tem favorito".
     */
    public function test_favorito_sem_ingrediente_nao_quebra_a_tela(): void
    {
        $usuario = User::factory()->create();
        $semIngrediente = $this->bebida('Água com Gás', []);
        Favorito::create(['id_usuario' => $usuario->id, 'cd_bebida' => $semIngrediente->cd_bebida]);

        $this->actingAs($usuario)
            ->get(route('recomendadas.index'))
            ->assertOk()
            ->assertViewHas('hasFavorites', true)
            ->assertViewHas('recomendadas', fn ($r) => $r->isEmpty());
    }

    /**
     * Os favoritos de outra pessoa não podem influenciar a recomendação: a
     * consulta filtra por id_usuario numa etapa e carrega o resultado para a
     * seguinte por literal de array, que é onde um filtro esquecido passaria.
     */
    public function test_nao_mistura_favoritos_de_outra_pessoa(): void
    {
        $usuario = User::factory()->create();
        $outra = User::factory()->create();

        $gin = $this->ingrediente('Gin');
        $rum = $this->ingrediente('Rum');

        $ginTonica = $this->bebida('Gin Tônica', [$gin]);
        $mojito = $this->bebida('Mojito', [$rum]);
        $daiquiri = $this->bebida('Daiquiri', [$rum]);

        Favorito::create(['id_usuario' => $usuario->id, 'cd_bebida' => $mojito->cd_bebida]);
        Favorito::create(['id_usuario' => $outra->id, 'cd_bebida' => $ginTonica->cd_bebida]);

        $this->actingAs($usuario)
            ->get(route('recomendadas.index'))
            ->assertOk()
            ->assertViewHas('recomendadas', function ($recomendadas) use ($daiquiri) {
                // Só o Daiquiri divide ingrediente com o favorito DESTE usuário.
                return $recomendadas->pluck('cd_bebida')->all() === [$daiquiri->cd_bebida];
            })
            ->assertViewHas('topIngredientes', fn ($top) => $top->pluck('nm_ingrediente')->all() === ['Rum']);
    }
}
