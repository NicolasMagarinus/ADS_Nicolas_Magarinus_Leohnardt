<?php

namespace App\Http\Controllers;

use App\Models\Bebida;
use App\Models\Ingrediente;
use Illuminate\Support\Facades\DB;

class IngredienteController extends Controller
{
    /**
     * Índice dos ingredientes que aparecem em ao menos uma receita.
     *
     * Órfão fica de fora de propósito: o comando app:gerar-ingredientes-ai
     * cria ingredientes que podem nunca ter sido usados, e um índice cheio de
     * página vazia é pior que não ter índice.
     */
    public function index()
    {
        $ingredientes = Ingrediente::query()
            ->select('ingrediente.cd_ingrediente', 'ingrediente.nm_ingrediente', 'ingrediente.ds_imagem')
            ->selectRaw('COUNT(bi.cd_bebida) AS qt_receitas')
            ->join('bebida_ingrediente as bi', 'bi.cd_ingrediente', '=', 'ingrediente.cd_ingrediente')
            ->groupBy('ingrediente.cd_ingrediente', 'ingrediente.nm_ingrediente', 'ingrediente.ds_imagem')
            ->orderByDesc('qt_receitas')
            ->orderBy('ingrediente.nm_ingrediente')
            ->paginate(24);

        return view('ingrediente.index', compact('ingredientes'));
    }

    public function show($cd_ingrediente)
    {
        $ingrediente = Ingrediente::findOrFail($cd_ingrediente);

        $bebidas = Bebida::query()
            ->select('bebida.*',
                DB::raw('COALESCE(ROUND(AVG(avaliacao.id_nota), 1), 0) AS nota'),
                DB::raw('COUNT(avaliacao.id_nota) AS qt_avaliacao'))
            ->join('bebida_ingrediente as bi', 'bi.cd_bebida', '=', 'bebida.cd_bebida')
            ->leftJoin('avaliacao', 'bebida.cd_bebida', '=', 'avaliacao.cd_bebida')
            ->where('bi.cd_ingrediente', $ingrediente->cd_ingrediente)
            ->groupBy('bebida.cd_bebida', 'bebida.nm_bebida', 'bebida.ds_preparo', 'bebida.id_tipo', 'bebida.ds_bebida', 'bebida.ds_imagem', 'bebida.created_at', 'bebida.updated_at')
            ->orderByDesc('nota')
            ->orderBy('bebida.nm_bebida')
            ->paginate(12);

        return view('ingrediente.show', compact('ingrediente', 'bebidas'));
    }
}
