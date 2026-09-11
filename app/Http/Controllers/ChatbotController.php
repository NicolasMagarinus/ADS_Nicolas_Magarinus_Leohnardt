<?php

namespace App\Http\Controllers;

use App\Enums\StatusCadastro;
use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\CadastroBebida;
use App\Models\CadastroBebidaIngrediente;
use App\Models\ChatbotUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use OpenAI\Laravel\Facades\OpenAI;

class ChatbotController extends Controller
{
    private const AI_DAILY_LIMIT = 5;

    /**
     * Quantas mensagens de contexto seguem junto de cada pergunta: 3 turnos de
     * ida e volta. A janela desliza, cortando as mais antigas.
     *
     * O teto é pequeno de propósito. O histórico entra no prompt de toda
     * chamada, então mais contexto custa mais token por pergunta — a troca
     * vale porque o limite é de 5 perguntas por dia, e uma pergunta
     * desperdiçada por falta de contexto custa mais caro que o contexto.
     */
    private const HISTORICO_MAX = 6;

    private const SESSAO_HISTORICO = 'chatbot_historico';

    private const SESSAO_ULTIMA_TROCA = 'chatbot_ultima_troca';

    /**
     * Depois de quantos minutos de silêncio a conversa é considerada
     * encerrada.
     *
     * Existe porque "conversa aberta" precisa ter fim. Sem isso o histórico
     * ficava na sessão até ela expirar (SESSION_LIFETIME, 120 minutos,
     * renovado a cada request), e o atalho de FAQ ficava desligado o tempo
     * todo: quem perguntasse uma receita e depois "como favoritar?" gastava
     * uma das 5 chamadas diárias numa pergunta que era de graça.
     */
    private const MINUTOS_ATE_ESFRIAR = 30;

    public function mensagem(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $text = trim($request->input('message'));

        // O atalho de FAQ só vale para pergunta solta. Com conversa aberta ele
        // atrapalha: "e uma versão sem álcool disso?" casa com o padrão
        // 'sem alcool' e receberia a resposta pronta de navegação, que é
        // justamente a continuação que o histórico existe para atender. O
        // mesmo vale para "e com outro ingrediente?" e "tem algo parecido
        // para recomendar?".
        //
        // A troca é consciente: enquanto a conversa estiver aberta, perguntas
        // que o FAQ resolveria de graça passam a consumir a cota diária.
        if (! $this->conversaAberta()) {
            // Esfriou: o contexto velho atrapalharia mais do que ajudaria numa
            // pergunta nova.
            session()->forget([self::SESSAO_HISTORICO, self::SESSAO_ULTIMA_TROCA]);
        }

        if (! $this->conversaAberta() && ($faqReply = $this->verificarFaq($text)) !== null) {
            return response()->json([
                'reply' => $faqReply,
                'source' => 'faq',
            ]);
        }

        $userId = Auth::id();
        $today = Carbon::today()->toDateString();

        $usage = ChatbotUsage::firstOrCreate(
            ['user_id' => $userId, 'usage_date' => $today],
            ['ai_calls_count' => 0]
        );

        if ($usage->ai_calls_count >= self::AI_DAILY_LIMIT) {
            return response()->json([
                'reply' => '⚠️ Você atingiu o limite de ' . self::AI_DAILY_LIMIT . ' perguntas à IA por hoje. Volte amanhã! 🍹',
                'source' => 'limit',
                'limit_reached' => true,
                'remaining' => 0,
            ], 429);
        }

        try {
            $response = OpenAI::chat()->create([
                'model' => config('openai.model', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é o Drinky, um assistente especialista em drinks e coquetéis do aplicativo Drinkerito. '
                            . 'Responda sempre em português do Brasil, de forma amigável e concisa (máximo 3 parágrafos). '
                            . 'Foque exclusivamente em bebidas, receitas, ingredientes e dicas de drinks. '
                            . 'Se a pergunta não for sobre bebidas, gentilmente redirecione o usuário para o tema de drinks. '
                            . 'IMPORTANTE: Quando o usuário pedir uma receita específica de drink ou coquetel, você DEVE chamar a função '
                            . '"sugerir_receita" com os dados estruturados da receita (nome, ingredientes e modo de preparo). '
                            . 'Além da chamada de função, escreva também uma resposta curta e amigável em texto apresentando o drink, '
                            . 'SEM repetir a lista de ingredientes nem o modo de preparo no texto — esses dados já são enviados pela função. '
                            . 'Não chame a função para perguntas que não sejam pedidos de receita específica de um drink.',
                    ],
                    ...($this->conversaAberta() ? session(self::SESSAO_HISTORICO, []) : []),
                    ['role' => 'user', 'content' => $text],
                ],
                'tools' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'sugerir_receita',
                            'description' => 'Envia os dados estruturados de uma receita de drink/coquetel sugerida ao usuário.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'nome' => [
                                        'type' => 'string',
                                        'description' => 'Nome do drink',
                                    ],
                                    'ingredientes' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'nm_ingrediente' => ['type' => 'string'],
                                                'ds_medida' => ['type' => 'string'],
                                            ],
                                            'required' => ['nm_ingrediente'],
                                        ],
                                    ],
                                    'modo_preparo' => [
                                        'type' => 'string',
                                        'description' => 'Passo a passo do preparo',
                                    ],
                                    'tipo' => [
                                        'type' => 'integer',
                                        'enum' => [1, 2],
                                        'description' => 'Tipo da bebida: 1 para alcoólica, 2 para não alcoólica',
                                    ],
                                ],
                                'required' => ['nome', 'ingredientes', 'modo_preparo', 'tipo'],
                            ],
                        ],
                    ],
                ],
                'tool_choice' => 'auto',
                'max_tokens' => 700,
                'temperature' => 0.7,
            ]);

            $message = $response->choices[0]->message;
            $cleanReply = $message->content ?? '';
            $drinkSuggestion = null;

            if (!empty($message->toolCalls)) {
                foreach ($message->toolCalls as $toolCall) {
                    if ($toolCall->type === 'function' && $toolCall->function->name === 'sugerir_receita') {
                        $decoded = json_decode($toolCall->function->arguments, true);

                        if (
                            json_last_error() === JSON_ERROR_NONE
                            && isset($decoded['nome'], $decoded['ingredientes'], $decoded['modo_preparo'])
                            && is_array($decoded['ingredientes'])
                        ) {
                            $drinkSuggestion = $decoded;
                        }
                        break;
                    }
                }
            }

            $cleanReply = preg_replace('/```json.*?```/s', '', $cleanReply);
            $cleanReply = preg_replace('/^\s*\{.*\}\s*$/s', '', $cleanReply);
            $cleanReply = trim($cleanReply);

            if ($cleanReply === '') {
                $cleanReply = $drinkSuggestion !== null
                    ? 'Encontrei uma receita pra você! 🍹 Veja os detalhes abaixo.'
                    : 'Desculpe, não consegui processar sua pergunta.';
            }

            $usage->increment('ai_calls_count');
            $remaining = self::AI_DAILY_LIMIT - $usage->ai_calls_count;

            $this->lembrarDaTroca($text, $cleanReply);

            $responseData = [
                'reply' => nl2br(e($cleanReply)),
                'source' => 'openai',
                'remaining' => $remaining,
            ];

            if ($drinkSuggestion !== null) {
                $nomeDrink = $drinkSuggestion['nome'];

                $existeNoCatalogo = Bebida::whereRaw(
                    'LOWER(nm_bebida) = LOWER(?)',
                    [$nomeDrink]
                )->exists();

                $jaSubmetido = CadastroBebida::where('id_usuario', Auth::id())
                    ->whereRaw('LOWER(nm_bebida) = LOWER(?)', [$nomeDrink])
                    ->where('id_status', '!=', StatusCadastro::Rejeitada)
                    ->exists();

                $responseData['drink_suggestion'] = $drinkSuggestion;
                $responseData['drink_exists'] = $existeNoCatalogo || $jaSubmetido;
                $responseData['drink_exists_reason'] = $existeNoCatalogo
                    ? 'catalog'
                    : ($jaSubmetido ? 'pending' : null);
            }

            return response()->json($responseData);
        } catch (\Throwable $e) {
            // Vai para o canal da aplicação, não para o log do PHP: fora de
            // storage/logs isto some do `php artisan pail`.
            Log::error('Erro na chamada à IA do chatbot: ' . $e->getMessage(), [
                'user_id' => $userId,
                'mensagem' => $text,
                'exception' => $e,
            ]);
            return response()->json([
                'reply' => '😔 Ops! Ocorreu um erro ao consultar a IA. Tente novamente em instantes.',
                'source' => 'error',
            ], 500);
        }
    }

    /**
     * Guarda o par pergunta/resposta na sessão, para a próxima pergunta ter
     * de que falar quando alguém escrever "e uma versão sem álcool disso?".
     *
     * Só é chamado depois de uma resposta que existiu de verdade: pergunta
     * barrada pelo limite diário, resposta pronta de FAQ e chamada que
     * estourou não entram. Com 5 perguntas por dia, contexto sujo
     * desperdiçaria as poucas que sobram.
     *
     * A resposta guardada é o texto limpo, o mesmo que a pessoa leu — quando
     * houve sugestão de receita, o nome do drink está nele, que é justamente
     * o que dá sentido à pergunta seguinte.
     */
    /**
     * Há conversa em andamento, isto é, houve troca com a IA há pouco.
     */
    private function conversaAberta(): bool
    {
        if (session(self::SESSAO_HISTORICO, []) === []) {
            return false;
        }

        $ultima = session(self::SESSAO_ULTIMA_TROCA);

        return $ultima !== null
            && Carbon::parse($ultima)->diffInMinutes(now()) < self::MINUTOS_ATE_ESFRIAR;
    }

    private function lembrarDaTroca(string $pergunta, string $resposta): void
    {
        $historico = session(self::SESSAO_HISTORICO, []);

        $historico[] = ['role' => 'user', 'content' => $pergunta];
        $historico[] = ['role' => 'assistant', 'content' => $resposta];

        session([
            self::SESSAO_HISTORICO => array_slice($historico, -self::HISTORICO_MAX),
            self::SESSAO_ULTIMA_TROCA => now()->toIso8601String(),
        ]);
    }

    public function salvarBebida(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:255',
            'tipo' => ['nullable', Rule::enum(TipoBebida::class)],
            'modo_preparo' => 'required|string',
            'ingredientes' => 'required|array|min:1',
            'ingredientes.*.nm_ingrediente' => 'required|string|max:255',
            'ingredientes.*.ds_medida' => 'nullable|string|max:255',
        ]);

        $nome = $request->nome;

        if (Bebida::whereRaw('LOWER(nm_bebida) = LOWER(?)', [$nome])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta bebida já existe no catálogo do Drinkerito.',
                'reason' => 'catalog',
            ], 409);
        }

        if (
            CadastroBebida::where('id_usuario', Auth::id())
                ->whereRaw('LOWER(nm_bebida) = LOWER(?)', [$nome])
                ->where('id_status', '!=', StatusCadastro::Rejeitada)
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Você já enviou esta bebida para aprovação.',
                'reason' => 'pending',
            ], 409);
        }

        DB::transaction(function () use ($request) {
            $cadastro = CadastroBebida::create([
                'id_usuario' => Auth::id(),
                'nm_bebida' => $request->nome,
                'id_tipo' => TipoBebida::tryFrom((int) $request->input('tipo', 1)) ?? TipoBebida::Alcoolica,
                'ds_preparo' => $request->modo_preparo,
                'ds_imagem' => null,
                'id_status' => StatusCadastro::Pendente,
            ]);

            foreach ($request->ingredientes as $ingrediente) {
                CadastroBebidaIngrediente::create([
                    'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
                    'nm_ingrediente' => $ingrediente['nm_ingrediente'],
                    'ds_medida' => $ingrediente['ds_medida'] ?? 'a gosto',
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => '🍹 Bebida enviada para aprovação! Acompanhe no seu perfil.',
        ]);
    }

    private function verificarFaq(string $text): ?string
    {
        $lower = mb_strtolower($text);
        $lower = $this->removerAcentos($lower);

        if (preg_match('/\b(oi|ola|hello|bom dia|boa tarde|boa noite|hey)\b/', $lower)) {
            return 'Olá! 😄 Estou aqui para te ajudar a encontrar o drink perfeito. O que você está procurando?';
        }

        if (preg_match('/\b(obrigad|valeu|thanks|grat)\w*/', $lower)) {
            return 'Por nada! 🍸 Qualquer dúvida é só chamar. Saúde!';
        }

        if (preg_match('/\b(aleator|sortear|sortei|surpresa|random)\w*/', $lower)) {
            return '🎲 Clique em <a href="/random" class="chatbot-link">Sortear Bebida</a> para descobrir um drink surpresa!';
        }

        if (
            preg_match('/\b(sem alcool|nao alcoolico|nao alcooli|virgin)\w*/', $lower)
            || str_contains($lower, 'sem alcool')
            || str_contains($lower, 'nao alcool')
        ) {
            return '🥤 Temos várias opções sem álcool! Veja a <a href="/search?tipo='
                .TipoBebida::NaoAlcoolica->value.'" class="chatbot-link">lista completa já filtrada</a>.';
        }

        if (str_contains($lower, 'favorit')) {
            return '❤️ Para favoritar, abra a página da bebida e clique no botão <strong>Favoritar</strong>. Você precisa estar logado!';
        }

        if (preg_match('/\b(ingrediente|buscar|pesquis|filtrar)\w*/', $lower)) {
            return '🧪 Você pode buscar drinks por ingrediente! Acesse <a href="/search" class="chatbot-link">Explorar Bebidas</a> e use a barra de pesquisa.';
        }

        if (
            preg_match('/\b(meu bar|meubar|tenho em casa|ingredientes que tenho|o que posso fazer|posso preparar)\w*/', $lower)
            || str_contains($lower, 'meu bar')
            || str_contains($lower, 'ingredientes que tenho')
            || str_contains($lower, 'o que posso fazer')
        ) {
            return '🍶 Com o <a href="/meu-bar" class="chatbot-link">Meu Bar</a> você adiciona os ingredientes que tem em casa e descobre quais drinks pode preparar agora — ou quais estão quase prontos!';
        }

        if (
            preg_match('/\b(cadastr|adicionar|criar|submeter|enviar)\w*.*(drink|bebida|receita)/', $lower)
            || preg_match('/\b(drink|bebida|receita)\w*.*(cadastr|adicionar|criar)/', $lower)
        ) {
            return '📝 Para cadastrar uma bebida, acesse <a href="/bebida/cadastrar" class="chatbot-link">Cadastrar Bebida</a> no menu superior!';
        }

        if (preg_match('/\b(perfil|conta|minha conta|meus dados)\w*/', $lower)) {
            return '👤 Você pode acessar seu perfil clicando no seu nome no menu superior, depois em <a href="/profile" class="chatbot-link">Meu Perfil</a>.';
        }

        if (
            preg_match('/\b(indic|recomend|sugir)\w*.*(drink|bebida)/', $lower)
            || preg_match('/\b(drink|bebida)\w*.*(indic|recomend|sugir)/', $lower)
            || preg_match('/\b(qual|que)\w*.*(drink|bebida)\w*(bom|legal|gostoso|boa)/', $lower)
        ) {
            return '🍹 Que tal explorar nosso catálogo? Acesse <a href="/search" class="chatbot-link">Explorar</a> ou peça um <a href="/random" class="chatbot-link">drink aleatório</a>. Se quiser uma recomendação personalizada, me diga seus ingredientes preferidos!';
        }

        return null;
    }

    private function removerAcentos(string $str): string
    {
        $from = ['á', 'à', 'ã', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'õ', 'ô', 'ö', 'ú', 'ù', 'û', 'ü', 'ç', 'ñ'];
        $to = ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'n'];
        return str_replace($from, $to, $str);
    }
}