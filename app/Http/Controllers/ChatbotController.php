<?php

namespace App\Http\Controllers;

use App\Models\CadastroBebida;
use App\Models\CadastroBebidaIngrediente;
use App\Services\ChatbotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly ChatbotService $chatbot
    ) {
    }

    public function mensagem(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $response = $this->chatbot->process(trim($request->message));

        $statusCode = ($response['source'] ?? null) === 'limit' ? 429 : 200;

        return response()->json($response, $statusCode);
    }

    public function salvarBebida(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:255',
            'modo_preparo' => 'required|string',
            'ingredientes' => 'required|array|min:1',
            'ingredientes.*.nm_ingrediente' => 'required|string|max:255',
            'ingredientes.*.ds_medida' => 'nullable|string|max:255',
        ]);

        $status = $this->chatbot->drinkStatus($request->nome);

        if ($status !== null) {
            return response()->json([
                'success' => false,
                'message' => $status === 'catalog'
                    ? 'Esta bebida já existe no catálogo do Drinkerito.'
                    : 'Você já enviou esta bebida para aprovação.',
                'reason' => $status,
            ], 409);
        }

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

        return response()->json([
            'success' => true,
            'message' => '🍹 Bebida enviada para aprovação! Acompanhe no seu perfil.',
        ]);
    }
}