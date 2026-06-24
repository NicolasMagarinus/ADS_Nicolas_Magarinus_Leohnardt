<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class OpenAIRecipeService
{
    public function ask(string $message): array
    {
        $response = OpenAI::chat()->create([
            'model' => config('openai.model', 'gpt-4o-mini'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $message,
                ],
            ],
            'max_tokens' => 600,
            'temperature' => 0.7,
        ]);

        $rawReply = $response->choices[0]->message->content ?? '';

        return $this->parseResponse($rawReply);
    }

    private function parseResponse(string $rawReply): array
    {
        $drinkSuggestion = null;
        $cleanReply = trim($rawReply);

        if (preg_match('/\[\[DRINK_JSON\]\](.*?)\[\[\/DRINK_JSON\]\]/s', $rawReply, $matches)) {
            $jsonStr = trim($matches[1]);
            $decoded = json_decode($jsonStr, true);

            if (
                json_last_error() === JSON_ERROR_NONE
                && is_array($decoded)
                && isset($decoded['nome'], $decoded['ingredientes'], $decoded['modo_preparo'])
                && is_array($decoded['ingredientes'])
            ) {
                $drinkSuggestion = $decoded;
                $cleanReply = trim(str_replace($matches[0], '', $rawReply));
            }
        }

        if ($cleanReply === '') {
            $cleanReply = 'Desculpe, não consegui montar a resposta corretamente.';
        }

        return [
            'reply' => $cleanReply,
            'drink' => $drinkSuggestion,
            'is_recipe' => $drinkSuggestion !== null,
        ];
    }

    private function systemPrompt(): string
    {
        return implode(PHP_EOL, [
            'Você é o Drinky, assistente oficial do Drinkerito.',
            '',
            'Regras:',
            '- Responda sempre em português do Brasil.',
            '- Seja amigável e objetivo.',
            '- Máximo de 3 parágrafos.',
            '- Fale apenas sobre drinks, bebidas e coquetéis.',
            '- Se a pergunta não for sobre bebidas, redirecione de forma gentil para o tema.',
            '',
            'Quando o usuário pedir uma receita, responda com texto normal e, ao final, inclua exatamente um bloco entre os marcadores abaixo:',
            '',
            '[[DRINK_JSON]]',
            '{',
            '  "nome": "Nome do Drink",',
            '  "ingredientes": [',
            '    {',
            '      "nm_ingrediente": "Ingrediente",',
            '      "ds_medida": "Quantidade"',
            '    }',
            '  ],',
            '  "modo_preparo": "Modo de preparo"',
            '}',
            '[[/DRINK_JSON]]',
            '',
            'Regras do bloco JSON:',
            '- O conteúdo entre os marcadores deve ser JSON válido.',
            '- Não use markdown dentro do JSON.',
            '- Não inclua explicações dentro dos marcadores.',
            '- Não inclua o bloco JSON em respostas que não sejam receitas.',
        ]);
    }
}