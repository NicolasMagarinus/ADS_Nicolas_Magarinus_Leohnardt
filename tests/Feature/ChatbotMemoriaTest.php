<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Cada mensagem ia sozinha para a OpenAI, então "e uma versão sem álcool?"
 * logo depois de uma receita não fazia sentido para o modelo. Com o limite de
 * 5 perguntas por dia, cada pergunta desperdiçada por falta de contexto pesa.
 */
class ChatbotMemoriaTest extends TestCase
{
    use RefreshDatabase;

    private function respostaFalsa(string $texto): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $texto, 'tool_calls' => []],
                'finish_reason' => 'stop',
            ]],
        ]);
    }

    /** Texto escolhido para não casar com nenhuma resposta pronta de FAQ. */
    private function perguntar(User $user, string $texto)
    {
        return $this->actingAs($user)->postJson(route('chatbot.message'), ['message' => $texto]);
    }

    public function test_primeira_pergunta_vai_sem_historico(): void
    {
        OpenAI::fake([$this->respostaFalsa('Experimente um Mojito.')]);

        $this->perguntar(User::factory()->create(), 'Sugere um coquetel refrescante?')->assertOk();

        OpenAI::assertSent(\OpenAI\Resources\Chat::class, function (string $metodo, array $parametros) {
            $papeis = array_column($parametros['messages'], 'role');

            return $papeis === ['system', 'user'];
        });
    }

    public function test_segunda_pergunta_leva_a_troca_anterior(): void
    {
        OpenAI::fake([
            $this->respostaFalsa('Experimente um Mojito bem gelado.'),
            $this->respostaFalsa('Dá para trocar o rum por água com gás.'),
        ]);

        $user = User::factory()->create();
        $this->perguntar($user, 'Sugere um coquetel refrescante?')->assertOk();
        $this->perguntar($user, 'E daria para adaptar essa receita?')->assertOk();

        OpenAI::assertSent(\OpenAI\Resources\Chat::class, function (string $metodo, array $parametros) {
            $mensagens = $parametros['messages'];

            if (end($mensagens)['content'] !== 'E daria para adaptar essa receita?') {
                return false;
            }

            $conteudos = array_column($mensagens, 'content');

            return in_array('Sugere um coquetel refrescante?', $conteudos, true)
                && in_array('Experimente um Mojito bem gelado.', $conteudos, true);
        });
    }

    public function test_janela_para_em_seis_mensagens(): void
    {
        OpenAI::fake(array_map(fn ($i) => $this->respostaFalsa("Resposta {$i}."), range(1, 5)));

        $user = User::factory()->create();
        foreach (range(1, 5) as $i) {
            $this->perguntar($user, "Pergunta {$i} sobre coquetel?")->assertOk();
        }

        OpenAI::assertSent(\OpenAI\Resources\Chat::class, function (string $metodo, array $parametros) {
            $mensagens = $parametros['messages'];
            $conteudos = array_column($mensagens, 'content');

            // system + 6 de histórico + a pergunta nova.
            return count($mensagens) === 8
                && in_array('Pergunta 5 sobre coquetel?', $conteudos, true)
                && ! in_array('Pergunta 1 sobre coquetel?', $conteudos, true);
        });
    }

    public function test_resposta_pronta_de_faq_nao_entra_no_historico(): void
    {
        OpenAI::fake([$this->respostaFalsa('Experimente um Mojito.')]);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('chatbot.message'), ['message' => 'Oi'])
            ->assertOk()->assertJson(['source' => 'faq']);

        $this->perguntar($user, 'Sugere um coquetel refrescante?')->assertOk();

        OpenAI::assertSent(\OpenAI\Resources\Chat::class, function (string $metodo, array $parametros) {
            return array_column($parametros['messages'], 'role') === ['system', 'user'];
        });
    }

    /**
     * O exemplo que motiva a memória no backlog. "sem álcool" casa com o FAQ,
     * então sem esta regra a continuação recebia a resposta pronta de
     * navegação e o histórico não servia para nada.
     */
    public function test_continuacao_de_conversa_escapa_do_faq(): void
    {
        OpenAI::fake([
            $this->respostaFalsa('Experimente um Mojito bem gelado.'),
            $this->respostaFalsa('Troque o rum por água com gás e mantenha a hortelã.'),
        ]);

        $user = User::factory()->create();
        $this->perguntar($user, 'Sugere um coquetel refrescante?')->assertOk();

        $this->perguntar($user, 'E uma versão sem álcool disso?')
            ->assertOk()
            ->assertJson(['source' => 'openai']);

        OpenAI::assertSent(\OpenAI\Resources\Chat::class, function (string $metodo, array $parametros) {
            return end($parametros['messages'])['content'] === 'E uma versão sem álcool disso?';
        });
    }

    /**
     * Sem conversa aberta, o FAQ continua sendo o atalho barato: a mesma
     * pergunta não gasta cota nenhuma.
     */
    public function test_sem_conversa_aberta_o_faq_continua_respondendo(): void
    {
        OpenAI::fake([$this->respostaFalsa('Não deveria ser chamado.')]);

        $user = User::factory()->create();

        $this->perguntar($user, 'Tem algo sem álcool?')
            ->assertOk()
            ->assertJson(['source' => 'faq']);

        $this->assertDatabaseMissing('chatbot_usage', ['user_id' => $user->id]);
    }

    /**
     * Cota esgotada com conversa aberta: a pergunta pula o FAQ, bate no limite
     * e o usuário recebe o 429 — não uma resposta pronta que mascararia o
     * limite.
     */
    public function test_conversa_aberta_com_cota_esgotada_devolve_o_limite(): void
    {
        OpenAI::fake(array_map(fn ($i) => $this->respostaFalsa("Resposta {$i}."), range(1, 5)));

        $user = User::factory()->create();
        foreach (range(1, 5) as $i) {
            $this->perguntar($user, "Pergunta {$i} sobre coquetel?")->assertOk();
        }

        $this->perguntar($user, 'E uma versão sem álcool disso?')->assertStatus(429);
    }

    public function test_historico_nao_vaza_entre_usuarios(): void
    {
        OpenAI::fake([
            $this->respostaFalsa('Experimente um Mojito.'),
            $this->respostaFalsa('Experimente uma Caipirinha.'),
        ]);

        $ana = User::factory()->create();
        $bruno = User::factory()->create();

        $this->perguntar($ana, 'Sugere um coquetel refrescante?')->assertOk();
        $this->flushSession();
        $this->perguntar($bruno, 'Qual coquetel combina com festa?')->assertOk();

        OpenAI::assertSent(\OpenAI\Resources\Chat::class, function (string $metodo, array $parametros) {
            $conteudos = array_column($parametros['messages'], 'content');

            return in_array('Qual coquetel combina com festa?', $conteudos, true)
                && ! in_array('Sugere um coquetel refrescante?', $conteudos, true);
        });
    }

    public function test_pergunta_barrada_pelo_limite_nao_entra_no_historico(): void
    {
        OpenAI::fake(array_map(fn ($i) => $this->respostaFalsa("Resposta {$i}."), range(1, 5)));

        $user = User::factory()->create();
        foreach (range(1, 5) as $i) {
            $this->perguntar($user, "Pergunta {$i} sobre coquetel?")->assertOk();
        }

        $this->perguntar($user, 'Essa aqui foi barrada pelo limite?')->assertStatus(429);

        $this->assertNotContains(
            'Essa aqui foi barrada pelo limite?',
            array_column(session('chatbot_historico', []), 'content')
        );
    }
}
