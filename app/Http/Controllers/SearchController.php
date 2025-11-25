<?php

namespace App\Http\Controllers;

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
            // Normalizar termo de busca (remover acentos)
            $normalizedTerm = $this->limpaString($searchTerm);
            
            // Verificar se é busca por tipo de bebida (alcoólica/não alcoólica)
            if (preg_match('/^(n[aã]o\s+)?alco[oó]lica?$/i', $normalizedTerm)) {
                // Busca por tipo: alcoólica (id_tipo = 1) ou não alcoólica (id_tipo = 2)
                $isNonAlcoholic = preg_match('/^n[aã]o/i', $normalizedTerm);
                $query->where('bebida.id_tipo', $isNonAlcoholic ? 2 : 1);
            } else {
                // Busca universal: nome da bebida OU ingrediente
                $query->where(function($q) use ($normalizedTerm) {
                    // Busca no nome da bebida (sem acento)
                    $q->whereRaw('unaccent(LOWER(bebida.nm_bebida)) LIKE unaccent(LOWER(?))', ["%{$normalizedTerm}%"])
                      // OU busca em ingredientes (sem acento)
                      ->orWhereExists(function($subQ) use ($normalizedTerm) {
                          $subQ->select(DB::raw(1))
                               ->from('bebida_ingrediente')
                               ->join('ingrediente', 'bebida_ingrediente.cd_ingrediente', '=', 'ingrediente.cd_ingrediente')
                               ->whereColumn('bebida_ingrediente.cd_bebida', 'bebida.cd_bebida')
                               ->whereRaw('unaccent(LOWER(ingrediente.nm_ingrediente)) LIKE unaccent(LOWER(?))', ["%{$normalizedTerm}%"]);
                      });
                });
            }
        }
        
        $bebidas = $query->orderBy('bebida.created_at', 'desc')->paginate(12)->withQueryString();
        
        return view('search', compact('bebidas', 'searchTerm'));
    }
    
    private function limpaString($string)
    {
        $unwanted_array = [
            'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A',
            'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I',
            'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U',
            'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a',
            'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
            'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u',
            'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
        ];
        return strtr($string, $unwanted_array);
    }
}
