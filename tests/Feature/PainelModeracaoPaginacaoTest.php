<?php

namespace Tests\Feature;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use App\Models\CadastroBebida;
use App\Models\CadastroBebidaIngrediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O painel trazia todos os cadastros pendentes numa página só, com os
 * ingredientes de cada um. É a tela que mais cresce sem limite: nada é
 * removido da fila até um admin decidir.
 */
class PainelModeracaoPaginacaoTest extends TestCase
{
    use RefreshDatabase;

    private function pendente(string $nome): CadastroBebida
    {
        $cadastro = CadastroBebida::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_bebida' => $nome,
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'Teste.',
            'ds_preparo' => 'Misture tudo e sirva.',
            'id_status' => StatusCadastro::Pendente,
        ]);

        CadastroBebidaIngrediente::create([
            'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
            'nm_ingrediente' => 'Cachaça',
            'ds_medida' => '50 ml',
        ]);

        return $cadastro;
    }

    private function admin(): User
    {
        return User::factory()->create(['id_admin' => true]);
    }

    public function test_painel_mostra_dez_por_pagina(): void
    {
        foreach (range(1, 12) as $i) {
            $this->pendente(sprintf('Receita %02d', $i));
        }

        $resposta = $this->actingAs($this->admin())->get(route('admin.bebidas.index'))->assertOk();

        // Ordem é created_at asc: a fila começa pela mais antiga.
        $resposta->assertSee('Receita 01')
            ->assertSee('Receita 10')
            ->assertDontSee('Receita 11');
    }

    public function test_segunda_pagina_traz_o_resto(): void
    {
        foreach (range(1, 12) as $i) {
            $this->pendente(sprintf('Receita %02d', $i));
        }

        $this->actingAs($this->admin())
            ->get(route('admin.bebidas.index').'?page=2')
            ->assertOk()
            ->assertSee('Receita 11')
            ->assertSee('Receita 12')
            ->assertDontSee('Receita 01');
    }

    public function test_fila_curta_nao_mostra_paginacao(): void
    {
        $this->pendente('Receita única');

        $this->actingAs($this->admin())->get(route('admin.bebidas.index'))
            ->assertOk()
            ->assertSee('Receita única')
            ->assertDontSee('page=2', false);
    }

    /**
     * Ingredientes e autor vêm num lote só.
     *
     * Contar consultas contra um número fixo não provaria nada: há um piso de
     * sessão, autenticação e a contagem da paginação. O que prova é a
     * diferença — carregando em lote, 10 linhas custam o mesmo que 1; sem o
     * with(), custam nove consultas a mais.
     *
     * Foi assim que apareceu o N+1 do autor: os ingredientes já estavam no
     * with(), mas a view também mostra quem enviou cada receita.
     */
    public function test_relacoes_nao_viram_consulta_por_linha(): void
    {
        $admin = $this->admin();

        $this->pendente('Receita 01');
        $comUma = $this->contarConsultas(fn () => $this->actingAs($admin)
            ->get(route('admin.bebidas.index'))->assertOk());

        foreach (range(2, 10) as $i) {
            $this->pendente(sprintf('Receita %02d', $i));
        }
        $comDez = $this->contarConsultas(fn () => $this->actingAs($admin)
            ->get(route('admin.bebidas.index'))->assertOk());

        $this->assertSame($comUma, $comDez,
            "10 linhas custaram {$comDez} consultas contra {$comUma} de uma linha: alguma relação está vindo linha a linha");
    }

    private int $consultas = 0;

    private bool $ouvindo = false;

    private function contarConsultas(callable $acao): int
    {
        // O ouvinte é registrado uma vez só: registrar de novo a cada medição
        // faria a segunda contar cada consulta duas vezes.
        if (! $this->ouvindo) {
            DB::listen(fn () => $this->consultas++);
            $this->ouvindo = true;
        }

        $this->consultas = 0;
        $acao();

        return $this->consultas;
    }
}
