<?php

namespace App\Http\Controllers;

use App\Models\Avaliacao;
use App\Models\Bebida;
use Illuminate\Http\Request;

class BebidaController extends Controller
{

    public function show($cd_bebida)
    {
        $bebida = Bebida::getBebida($cd_bebida);

        return view('bebida.show')
            ->with('bebida', $bebida);
    }

    public function search(Request $request)
    {
        $nome = $request->nome;

        if (!$nome) {
            return response()->json([]);
        }

        $bebidas = Bebida::select('cd_bebida', 'nm_bebida', 'ds_imagem')
            ->where('nm_bebida', 'ILIKE', "%{$nome}%")
            ->orderBy('nm_bebida')
            ->limit(10)
            ->get();

        return response()->json($bebidas);
    }
}
