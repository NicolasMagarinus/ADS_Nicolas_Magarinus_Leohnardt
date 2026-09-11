<?php

namespace App\Http\Controllers;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $searchTerm = $request->get('q', '');
        
        $query = Bebida::query()
            ->select('bebida.*', 
                DB::raw('COALESCE(ROUND(AVG(avaliacao.id_nota), 1), 0) AS nota'),
                DB::raw('COUNT(avaliacao.id_nota) AS qt_avaliacao'))
            ->leftJoin('avaliacao', 'bebida.cd_bebida', '=', 'avaliacao.cd_bebida')
            ->groupBy('bebida.cd_bebida', 'bebida.nm_bebida', 'bebida.ds_preparo', 'bebida.id_tipo', 'bebida.ds_bebida', 'bebida.ds_imagem', 'bebida.created_at', 'bebida.updated_at');
        
        if ($searchTerm) {
            // Nada de tirar acento do termo aqui: o unaccent() do SQL abaixo já
            // roda nos dois lados da comparação e cobre mais casos que um mapa
            // manual de caracteres.
            //
            // O /u é obrigatório nos regexes: sem ele [aã] compara byte a byte e
            // nunca casa com o "ã" de "não", que ocupa dois bytes em UTF-8.
            if (preg_match('/^(n[aã]o\s+)?alco[oó]lica?$/iu', $searchTerm)) {
                $isNonAlcoholic = preg_match('/^n[aã]o/iu', $searchTerm);
                $query->where('bebida.id_tipo', $isNonAlcoholic ? TipoBebida::NaoAlcoolica : TipoBebida::Alcoolica);
            } else {
                $query->where(function($q) use ($searchTerm) {
                    $q->whereRaw('unaccent(LOWER(bebida.nm_bebida)) LIKE unaccent(LOWER(?))', ["%{$searchTerm}%"])
                      ->orWhereExists(function($subQ) use ($searchTerm) {
                          $subQ->select(DB::raw(1))
                               ->from('bebida_ingrediente')
                               ->join('ingrediente', 'bebida_ingrediente.cd_ingrediente', '=', 'ingrediente.cd_ingrediente')
                               ->whereColumn('bebida_ingrediente.cd_bebida', 'bebida.cd_bebida')
                               ->whereRaw('unaccent(LOWER(ingrediente.nm_ingrediente)) LIKE unaccent(LOWER(?))', ["%{$searchTerm}%"]);
                      });
                });
            }
        }
        
        if ($searchTerm) {
            $bebidas = $query->orderByDesc('nota')
                             ->orderBy('bebida.cd_bebida', 'desc')
                             ->paginate(12)->withQueryString();
        } else {
            $bebidas = $query->orderBy('bebida.created_at', 'desc')
                             ->orderBy('bebida.cd_bebida', 'desc')
                             ->paginate(12)->withQueryString();
        }
        
        return view('search', compact('bebidas', 'searchTerm'));
    }
}
