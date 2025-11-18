<?php

namespace App\Http\Controllers;

use App\Models\Avaliacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AvaliacaoController extends Controller
{
    public function store(Request $request, $cd_bebida)
    {
        $request->validate([
            'id_nota'      => 'required|integer|between:1,5',
            'ds_avaliacao' => 'nullable|string|max:1000',
        ]);

        $avaliacao = Avaliacao::updateOrCreate(
            [
                'id_usuario' => Auth::id(),
                'cd_bebida'  => $cd_bebida,
            ],
            [
                'id_nota'      => $request->id_nota,
                'ds_avaliacao' => $request->ds_avaliacao,
                'dt_avaliacao' => now(),
            ]
        );

        return redirect()->back()->with('success', 'Avaliação salva com sucesso!');
    }
}
