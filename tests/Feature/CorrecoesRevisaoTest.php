<?php

namespace Tests\Feature;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\CadastroBebida;
use App\Models\Ingrediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Achados da revisão da branch: parâmetros em forma de array derrubando
 * páginas, o FAQ do chatbot desligado para sempre e a recuperação de corrida
 * do normalizar() inalcançável dentro de transação.
 */
class CorrecoesRevisaoTest extends TestCase
{
    use RefreshDatabase;

    /** Achado 7 — página pública, sem login, ao alcance de qualquer rastreador. */
    public function test_busca_com_q_em_array_nao_derruba_a_pagina(): void
    {
        $this->get('/search?q[]=abc')->assertOk();
    }

    /** Achado 6 — contradizia o comentário de "cai na fila em vez de dar 404". */
    public function test_painel_com_status_em_array_cai_na_fila(): void
    {
        CadastroBebida::create([
            'id_usuario' => User::factory()->create()->id,
            'nm_bebida' => 'Receita Pendente',
            'id_tipo' => TipoBebida::Alcoolica,
            'ds_bebida' => 'x',
            'ds_preparo' => 'y',
            'id_status' => StatusCadastro::Pendente,
        ]);

        $this->actingAs(User::factory()->create(['id_admin' => true]))
            ->get('/admin/bebidas?status[]=aprovadas')
            ->assertOk()
            ->assertSee('Receita Pendente');
    }

    public function test_demais_filtros_em_array_nao_derrubam_a_busca(): void
    {
        $this->get('/search?tipo[]=1&nota[]=4&max_ingredientes[]=3&ingrediente[]=9')->assertOk();
    }

    /**
     * Achado 5 — no Postgres a violação de unique aborta a transação inteira,
     * então o porNome() do catch estourava 25P02 e o comentário "corrida com
     * outra escrita" descrevia um caminho que não funcionava.
     *
     * A corrida é simulada de propósito: um ouvinte insere a linha canônica
     * logo depois da busca que não a encontrou, que é exatamente a janela em
     * que o outro escritor entraria. Sem savepoint em volta do create(), a
     * transação aborta e a chamada estoura.
     */
    public function test_normalizar_recupera_de_corrida_dentro_de_transacao(): void
    {
        $concorrenteJaEntrou = false;

        DB::listen(function ($consulta) use (&$concorrenteJaEntrou) {
            if ($concorrenteJaEntrou || ! str_contains($consulta->sql, 'f_unaccent')) {
                return;
            }

            $concorrenteJaEntrou = true;

            // O outro escritor, entre a busca e a inserção.
            DB::table('ingrediente')->insert([
                'nm_ingrediente' => 'Água com gás',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $resolvido = DB::transaction(function () {
            return Ingrediente::normalizar('Agua com gas');
        });

        $this->assertTrue($concorrenteJaEntrou, 'a corrida não chegou a acontecer');
        $this->assertSame('Água com gás', $resolvido->nm_ingrediente);
        $this->assertSame(1, Ingrediente::count());

        // E a transação continuou utilizável depois da recuperação.
        $this->assertSame(0, Bebida::count());
    }

    /**
     * Achado 4 — chatbot_historico nunca era limpo, então o atalho de FAQ
     * ficava desligado para o resto da sessão e toda pergunta passava a
     * gastar a cota de 5 por dia.
     */
    public function test_faq_volta_a_responder_depois_que_a_conversa_esfria(): void
    {
        OpenAI::fake([CreateResponse::fake([
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => 'Experimente um Mojito.', 'tool_calls' => []],
                'finish_reason' => 'stop',
            ]],
        ])]);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('chatbot.message'), [
            'message' => 'Sugere um coquetel refrescante?',
        ])->assertOk()->assertJson(['source' => 'openai']);

        // Conversa parada: a próxima pergunta volta a ser pergunta solta.
        $this->travel(31)->minutes();

        $this->actingAs($user)->postJson(route('chatbot.message'), [
            'message' => 'Como faço para favoritar?',
        ])->assertOk()->assertJson(['source' => 'faq']);

        // E não consumiu uma segunda chamada à IA.
        $this->assertDatabaseHas('chatbot_usage', ['user_id' => $user->id, 'ai_calls_count' => 1]);
    }

    public function test_conversa_recente_continua_pulando_o_faq(): void
    {
        OpenAI::fake([
            CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'A.', 'tool_calls' => []], 'finish_reason' => 'stop']]]),
            CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'B.', 'tool_calls' => []], 'finish_reason' => 'stop']]]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('chatbot.message'), [
            'message' => 'Sugere um coquetel refrescante?',
        ])->assertOk();

        $this->travel(2)->minutes();

        $this->actingAs($user)->postJson(route('chatbot.message'), [
            'message' => 'E uma versão sem álcool disso?',
        ])->assertOk()->assertJson(['source' => 'openai']);
    }
}
