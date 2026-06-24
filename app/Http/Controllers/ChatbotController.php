<?php

namespace App\Http\Controllers;

use App\Models\CadastroBebida;
use App\Models\CadastroBebidaIngrediente;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatbotController extends Controller
{
    public function __construct(private readonly ChatbotService $chatbot) {
        
    }

    public function mensagem(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $resposta = $this->chatbot->process(trim($request->message));

        return response()->json($resposta, $this->statusCodeDaResposta($resposta));
    }

    public function salvarBebida(Request $request): JsonResponse
    {
        $request->validate([
            'nome' => 'required|string|max:255',
            'modo_preparo' => 'required|string',
            'ingredientes' => 'required|array|min:1',
            'ingredientes.*.nm_ingrediente' => 'required|string|max:255',
            'ingredientes.*.ds_medida' => 'nullable|string|max:255',
        ]);

        $statusExistente = $this->chatbot->drinkStatus($request->nome);

        if ($statusExistente !== null) {
            return $this->respostaBebidaJaExiste($statusExistente);
        }

        $this->criarCadastroBebida($request);

        return response()->json([
            'success' => true,
            'message' => '🍹 Bebida enviada para aprovação! Acompanhe no seu perfil.',
        ]);
    }

    private function statusCodeDaResposta(array $resposta): int
    {
        $atingiuLimite = ($resposta['source'] ?? null) === 'limit';

        return $atingiuLimite ? 429 : 200;
    }

    private function respostaBebidaJaExiste(string $status): JsonResponse
    {
        $mensagem = $status === 'catalog'
            ? 'Esta bebida já existe no catálogo do Drinkerito.'
            : 'Você já enviou esta bebida para aprovação.';

        return response()->json([
            'success' => false,
            'message' => $mensagem,
            'reason' => $status,
        ], 409);
    }

    private function criarCadastroBebida(Request $request): void
    {
        DB::transaction(function () use ($request) {
            $cadastro = CadastroBebida::create([
                'id_usuario' => Auth::id(),
                'nm_bebida' => $request->nome,
                'ds_preparo' => $request->modo_preparo,
                'ds_imagem' => null,
                'id_status' => 0,
            ]);

            foreach ($request->ingredientes as $ingrediente) {
                CadastroBebidaIngrediente::create([
                    'cd_bebida_cadastro' => $cadastro->cd_bebida_cadastro,
                    'nm_ingrediente' => $ingrediente['nm_ingrediente'],
                    'ds_medida' => $ingrediente['ds_medida'] ?? 'a gosto',
                ]);
            }
        });
    }
}