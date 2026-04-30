<?php

namespace App\Http\Controllers;

use App\Models\ChatbotUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use OpenAI\Laravel\Facades\OpenAI;

class ChatbotController extends Controller
{
    private const AI_DAILY_LIMIT = 5;

    public function message(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $text = trim($request->input('message'));

        $faqReply = $this->matchFaq($text);
        if ($faqReply !== null) {
            return response()->json([
                'reply'  => $faqReply,
                'source' => 'faq',
            ]);
        }

        $userId = Auth::id();
        $today  = Carbon::today()->toDateString();

        $usage = ChatbotUsage::firstOrCreate(
            ['user_id' => $userId, 'usage_date' => $today],
            ['ai_calls_count' => 0]
        );

        if ($usage->ai_calls_count >= self::AI_DAILY_LIMIT) {
            return response()->json([
                'reply'         => '⚠️ Você atingiu o limite de ' . self::AI_DAILY_LIMIT . ' perguntas à IA por hoje. Volte amanhã! 🍹',
                'source'        => 'limit',
                'limit_reached' => true,
                'remaining'     => 0,
            ], 429);
        }

        try {
            $response = OpenAI::chat()->create([
                'model'    => config('openai.model', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'Você é o Drinky, um assistente especialista em drinks e coquetéis do aplicativo Drinkerito. '
                            . 'Responda sempre em português do Brasil, de forma amigável e concisa (máximo 3 parágrafos). '
                            . 'Foque exclusivamente em bebidas, receitas, ingredientes e dicas de drinks. '
                            . 'Se a pergunta não for sobre bebidas, gentilmente redirecione o usuário para o tema de drinks.',
                    ],
                    ['role' => 'user', 'content' => $text],
                ],
                'max_tokens'  => 400,
                'temperature' => 0.7,
            ]);

            $reply = $response->choices[0]->message->content ?? 'Desculpe, não consegui processar sua pergunta.';

            $usage->increment('ai_calls_count');
            $remaining = self::AI_DAILY_LIMIT - $usage->ai_calls_count;

            return response()->json([
                'reply'     => nl2br(e($reply)),
                'source'    => 'openai',
                'remaining' => $remaining,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'reply'  => '😔 Ops! Ocorreu um erro ao consultar a IA. Tente novamente em instantes.',
                'source' => 'error',
            ], 500);
        }
    }

    private function matchFaq(string $text): ?string
    {
        $lower = mb_strtolower($text);
        $lower = $this->removeAccents($lower);

        if (preg_match('/\b(oi|ola|hello|bom dia|boa tarde|boa noite|hey)\b/', $lower)) {
            return 'Olá! 😄 Estou aqui para te ajudar a encontrar o drink perfeito. O que você está procurando?';
        }

        if (preg_match('/\b(obrigad|valeu|thanks|grat)\w*/', $lower)) {
            return 'Por nada! 🍸 Qualquer dúvida é só chamar. Saúde!';
        }

        if (preg_match('/\b(aleator|sortear|sortei|surpresa|random)\w*/', $lower)) {
            return '🎲 Clique em <a href="/random" class="chatbot-link">Sortear Bebida</a> para descobrir um drink surpresa!';
        }

        if (preg_match('/\b(sem alcool|nao alcoolico|nao alcooli|virgin)\w*/', $lower)
            || str_contains($lower, 'sem alcool')
            || str_contains($lower, 'nao alcool')) {
            return '🥤 Temos várias opções sem álcool! Use o filtro de <a href="/search" class="chatbot-link">busca</a> e filtre por tipo "Não alcoólico".';
        }

        if (str_contains($lower, 'favorit')) {
            return '❤️ Para favoritar, abra a página da bebida e clique no botão <strong>Favoritar</strong>. Você precisa estar logado!';
        }

        if (preg_match('/\b(ingrediente|buscar|pesquis|filtrar)\w*/', $lower)) {
            return '🧪 Você pode buscar drinks por ingrediente! Acesse <a href="/search" class="chatbot-link">Explorar Bebidas</a> e use a barra de pesquisa.';
        }

        if (preg_match('/\b(cadastr|adicionar|criar|submeter|enviar)\w*.*(drink|bebida|receita)/', $lower)
            || preg_match('/\b(drink|bebida|receita)\w*.*(cadastr|adicionar|criar)/', $lower)) {
            return '📝 Para cadastrar uma bebida, acesse <a href="/bebida/cadastrar" class="chatbot-link">Cadastrar Bebida</a> no menu superior!';
        }

        if (preg_match('/\b(perfil|conta|minha conta|meus dados)\w*/', $lower)) {
            return '👤 Você pode acessar seu perfil clicando no seu nome no menu superior, depois em <a href="/profile" class="chatbot-link">Meu Perfil</a>.';
        }

        if (preg_match('/\b(indic|recomend|sugir)\w*.*(drink|bebida)/', $lower)
            || preg_match('/\b(drink|bebida)\w*.*(indic|recomend|sugir)/', $lower)
            || preg_match('/\b(qual|que)\w*.*(drink|bebida)\w*(bom|legal|gostoso|boa)/', $lower)) {
            return '🍹 Que tal explorar nosso catálogo? Acesse <a href="/search" class="chatbot-link">Explorar</a> ou peça um <a href="/random" class="chatbot-link">drink aleatório</a>. Se quiser uma recomendação personalizada, me diga seus ingredientes preferidos!';
        }

        return null;
    }

    private function removeAccents(string $str): string
    {
        $from = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','ñ'];
        $to   = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];
        return str_replace($from, $to, $str);
    }
}
