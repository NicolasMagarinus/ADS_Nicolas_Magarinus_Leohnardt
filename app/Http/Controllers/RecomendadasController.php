<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecomendadasController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $favoritas = DB::table('favorito')
            ->where('id_usuario', $userId)
            ->pluck('cd_bebida')
            ->toArray();

        if (empty($favoritas)) {
            return view('recomendadas.index', [
                'hasFavorites' => false,
                'recomendadas' => collect(),
                'topIngredientes' => collect(),
            ]);
        }

        $topIngredientes = DB::table('bebida_ingrediente as bi')
            ->join('ingrediente as i', 'bi.cd_ingrediente', '=', 'i.cd_ingrediente')
            ->whereIn('bi.cd_bebida', $favoritas)
            ->select('bi.cd_ingrediente', 'i.nm_ingrediente', DB::raw('COUNT(*) as frequencia'))
            ->groupBy('bi.cd_ingrediente', 'i.nm_ingrediente')
            ->orderByDesc('frequencia')
            ->take(5)
            ->get();

        $topIngredienteIds = $topIngredientes->pluck('cd_ingrediente')->toArray();

        if (empty($topIngredienteIds)) {
            return view('recomendadas.index', [
                'hasFavorites' => true,
                'recomendadas' => collect(),
                'topIngredientes' => $topIngredientes,
            ]);
        }

        $topIdsLiteral = '{' . implode(',', $topIngredienteIds) . '}';
        $favoritasLiteral = '{' . implode(',', $favoritas) . '}';

        $recomendadas = DB::select("
            SELECT b.cd_bebida,
                   b.nm_bebida,
                   b.ds_imagem,
                   b.id_tipo,
                   COALESCE(ROUND(AVG(a.id_nota), 1), 0) AS nota,
                   COUNT(DISTINCT a.cd_avaliacao)          AS qt_avaliacao,
                   COUNT(DISTINCT CASE
                       WHEN bi.cd_ingrediente = ANY(?)
                       THEN bi.cd_ingrediente
                   END)                                    AS match_count
              FROM bebida b
              LEFT JOIN avaliacao a  ON a.cd_bebida = b.cd_bebida
              JOIN bebida_ingrediente bi ON bi.cd_bebida = b.cd_bebida
             WHERE bi.cd_ingrediente = ANY(?)
               AND b.cd_bebida <> ALL(?)
             GROUP BY b.cd_bebida, b.nm_bebida, b.ds_imagem, b.id_tipo
            HAVING COUNT(DISTINCT CASE
                       WHEN bi.cd_ingrediente = ANY(?)
                       THEN bi.cd_ingrediente
                   END) > 0
             ORDER BY match_count DESC, nota DESC
             LIMIT 8
        ", [$topIdsLiteral, $topIdsLiteral, $favoritasLiteral, $topIdsLiteral]);

        return view('recomendadas.index', [
            'hasFavorites' => true,
            'recomendadas' => collect($recomendadas),
            'topIngredientes' => $topIngredientes,
        ]);
    }
}
