<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * O limite diário é o que impede a conta da OpenAI de ser drenada por um
 * único usuário, então ele precisa valer mesmo com o contador vindo do banco.
 */
class ChatbotLimiteDiarioTest extends TestCase
{
    use RefreshDatabase;

    private const LIMITE = 5;

    protected function respostaFalsa(string $texto = 'Experimente um Mojito bem gelado.'): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $texto, 'tool_calls' => []],
                'finish_reason' => 'stop',
            ]],
        ]);
    }

    private function perguntar(User $user)
    {
        // Texto escolhido para não casar com nenhuma resposta pronta de FAQ,
        // garantindo que a pergunta chegue ao caminho da IA.
        return $this->actingAs($user)->postJson(route('chatbot.message'), [
            'message' => 'Como preparar um coquetel tropical?',
        ]);
    }

    public function test_bloqueia_a_pergunta_seguinte_ao_limite_diario(): void
    {
        OpenAI::fake(array_fill(0, self::LIMITE + 1, $this->respostaFalsa()));

        $user = User::factory()->create();

        for ($i = 1; $i <= self::LIMITE; $i++) {
            $resposta = $this->perguntar($user);

            $resposta->assertOk()->assertJson(['source' => 'openai']);
            $this->assertSame(self::LIMITE - $i, $resposta->json('remaining'), "pergunta {$i}");
        }

        $bloqueada = $this->perguntar($user);

        $bloqueada->assertStatus(429)->assertJson([
            'source' => 'limit',
            'limit_reached' => true,
            'remaining' => 0,
        ]);

        $this->assertDatabaseHas('chatbot_usage', [
            'user_id' => $user->id,
            'ai_calls_count' => self::LIMITE,
        ]);
    }

    public function test_resposta_pronta_nao_consome_cota(): void
    {
        OpenAI::fake([$this->respostaFalsa()]);

        $user = User::factory()->create();

        $resposta = $this->actingAs($user)->postJson(route('chatbot.message'), ['message' => 'Oi']);

        $resposta->assertOk()->assertJson(['source' => 'faq']);
        $this->assertDatabaseMissing('chatbot_usage', ['user_id' => $user->id]);
    }

    public function test_o_limite_e_por_usuario(): void
    {
        OpenAI::fake(array_fill(0, self::LIMITE + 2, $this->respostaFalsa()));

        $esgotado = User::factory()->create();
        $outro = User::factory()->create();

        for ($i = 1; $i <= self::LIMITE; $i++) {
            $this->perguntar($esgotado)->assertOk();
        }

        $this->perguntar($esgotado)->assertStatus(429);
        $this->perguntar($outro)->assertOk();
    }

    public function test_visitante_nao_autenticado_nao_fala_com_a_ia(): void
    {
        $this->postJson(route('chatbot.message'), ['message' => 'Como preparar um coquetel tropical?'])
            ->assertStatus(401);
    }
}
