<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

/**
 * Quando a chamada à OpenAI falha, o usuário só vê "tente novamente". O que
 * sobrou para investigar é o log — e ele precisa estar no canal da
 * aplicação, com quem perguntou e o quê. No log do PHP fica fora de
 * storage/logs e some do `php artisan pail`.
 */
class ChatbotErroLogTest extends TestCase
{
    use RefreshDatabase;

    private function perguntar(User $user)
    {
        // Texto escolhido para não casar com nenhuma resposta pronta de FAQ.
        return $this->actingAs($user)->postJson(route('chatbot.message'), [
            'message' => 'Como preparar um coquetel tropical?',
        ]);
    }

    public function test_falha_da_ia_vai_para_o_log_da_aplicacao(): void
    {
        $usuario = User::factory()->create();

        OpenAI::fake();
        OpenAI::shouldReceive('chat')->andThrow(new \RuntimeException('conexão recusada'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $mensagem, array $contexto) use ($usuario) {
                return str_contains($mensagem, 'conexão recusada')
                    && $contexto['user_id'] === $usuario->id
                    && $contexto['mensagem'] === 'Como preparar um coquetel tropical?';
            });

        $this->perguntar($usuario)
            ->assertStatus(500)
            ->assertJson(['source' => 'error']);
    }
}
