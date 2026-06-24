<?php

namespace App\Services;

use App\Models\Bebida;
use App\Models\CadastroBebida;
use App\Models\ChatbotUsage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ChatbotService
{
    private const AI_DAILY_LIMIT = 5;

    public function __construct(
        private readonly OpenAIRecipeService $openAI
    ) {
    }

    public function process(string $message): array
    {
        if ($faqReply = $this->faq($message)) {
            return [
                'reply' => $faqReply,
                'source' => 'faq',
            ];
        }

        $usage = $this->dailyUsage();

        if ($usage->ai_calls_count >= self::AI_DAILY_LIMIT) {
            return [
                'reply' => '⚠️ Você atingiu o limite diário de perguntas à IA. Volte amanhã! 🍹',
                'source' => 'limit',
                'limit_reached' => true,
                'remaining' => 0,
            ];
        }

        try {
            $result = $this->openAI->ask($message);

            $usage->increment('ai_calls_count');
            $remaining = self::AI_DAILY_LIMIT - $usage->fresh()->ai_calls_count;

            $response = [
                'reply' => nl2br(e($result['reply'])),
                'source' => 'openai',
                'remaining' => $remaining,
            ];

            if (!empty($result['drink']) && is_array($result['drink'])) {
                $drinkName = $result['drink']['nome'] ?? null;

                if ($drinkName) {
                    $status = $this->drinkStatus($drinkName);

                    $response['drink_suggestion'] = $result['drink'];
                    $response['drink_exists'] = $status !== null;
                    $response['drink_exists_reason'] = $status;
                }
            }

            return $response;
        } catch (\Throwable $e) {
            report($e);

            return [
                'reply' => '😔 Ops! Ocorreu um erro ao consultar a IA. Tente novamente em instantes.',
                'source' => 'error',
            ];
        }
    }

    public function drinkStatus(string $name): ?string
    {
        if ($this->drinkExists($name)) {
            return 'catalog';
        }

        if ($this->drinkPending($name)) {
            return 'pending';
        }

        return null;
    }

    public function dailyUsage(): ChatbotUsage
    {
        return ChatbotUsage::firstOrCreate(
            [
                'user_id' => Auth::id(),
                'usage_date' => Carbon::today()->toDateString(),
            ],
            [
                'ai_calls_count' => 0,
            ]
        );
    }

    private function drinkExists(string $name): bool
    {
        return Bebida::whereRaw('LOWER(nm_bebida) = LOWER(?)', [$name])->exists();
    }

    private function drinkPending(string $name): bool
    {
        return CadastroBebida::where('id_usuario', Auth::id())
            ->whereRaw('LOWER(nm_bebida) = LOWER(?)', [$name])
            ->where('id_status', '!=', 2)
            ->exists();
    }

    private function faq(string $text): ?string
    {
        $text = $this->normalize($text);

        $rules = [
            [
                'patterns' => ['/\b(oi|ola|hello|bom dia|boa tarde|boa noite|hey)\b/'],
                'reply' => 'Olá! 😄 Estou aqui para te ajudar a encontrar o drink perfeito. O que você está procurando?',
            ],
            [
                'patterns' => ['/\b(obrigad|valeu|thanks|grat)\w*/'],
                'reply' => 'Por nada! 🍸 Qualquer dúvida é só chamar. Saúde!',
            ],
            [
                'patterns' => ['/\b(aleator|sortear|sortei|surpresa|random)\w*/'],
                'reply' => '🎲 Clique em <a href="/random" class="chatbot-link">Sortear Bebida</a> para descobrir um drink surpresa!',
            ],
            [
                'patterns' => [
                    '/\b(sem alcool|nao alcoolico|nao alcooli|virgin)\w*/',
                ],
                'reply' => '🥤 Temos várias opções sem álcool! Use o filtro de <a href="/search" class="chatbot-link">busca</a> e filtre por tipo "Não alcoólico".',
            ],
            [
                'patterns' => ['/\bfavorit\w*/'],
                'reply' => '❤️ Para favoritar, abra a página da bebida e clique no botão <strong>Favoritar</strong>. Você precisa estar logado!',
            ],
            [
                'patterns' => ['/\b(ingrediente|buscar|pesquis|filtrar)\w*/'],
                'reply' => '🧪 Você pode buscar drinks por ingrediente! Acesse <a href="/search" class="chatbot-link">Explorar Bebidas</a> e use a barra de pesquisa.',
            ],
            [
                'patterns' => [
                    '/\b(meu bar|meubar|tenho em casa|ingredientes que tenho|o que posso fazer|posso preparar)\w*/',
                ],
                'reply' => '🍶 Com o <a href="/meu-bar" class="chatbot-link">Meu Bar</a> você adiciona os ingredientes que tem em casa e descobre quais drinks pode preparar agora — ou quais estão quase prontos!',
            ],
            [
                'patterns' => [
                    '/\b(cadastr|adicionar|criar|submeter|enviar)\w*.*(drink|bebida|receita)/',
                    '/\b(drink|bebida|receita)\w*.*(cadastr|adicionar|criar)/',
                ],
                'reply' => '📝 Para cadastrar uma bebida, acesse <a href="/bebida/cadastrar" class="chatbot-link">Cadastrar Bebida</a> no menu superior!',
            ],
            [
                'patterns' => ['/\b(perfil|conta|minha conta|meus dados)\w*/'],
                'reply' => '👤 Você pode acessar seu perfil clicando no seu nome no menu superior, depois em <a href="/profile" class="chatbot-link">Meu Perfil</a>.',
            ],
            [
                'patterns' => [
                    '/\b(indic|recomend|sugir)\w*.*(drink|bebida)/',
                    '/\b(drink|bebida)\w*.*(indic|recomend|sugir)/',
                    '/\b(qual|que)\w*.*(drink|bebida)\w*(bom|legal|gostoso|boa)/',
                ],
                'reply' => '🍹 Que tal explorar nosso catálogo? Acesse <a href="/search" class="chatbot-link">Explorar</a> ou peça um <a href="/random" class="chatbot-link">drink aleatório</a>. Se quiser uma recomendação personalizada, me diga seus ingredientes preferidos!',
            ],
        ];

        foreach ($rules as $rule) {
            foreach ($rule['patterns'] as $pattern) {
                if (preg_match($pattern, $text)) {
                    return $rule['reply'];
                }
            }
        }

        return null;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);

        return strtr($text, [
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ç' => 'c',
            'ñ' => 'n',
        ]);
    }
}