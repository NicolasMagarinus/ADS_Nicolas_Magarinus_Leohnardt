<?php

namespace App\Http\Controllers;

use App\Models\Ingrediente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeuBarController extends Controller
{
    public function index()
    {
        $sessionIngredients = session('meubar_ingredientes', []);
        return view('meubar.index', compact('sessionIngredients'));
    }

    public function sincronizarSessao(Request $request)
    {
        $request->validate([
            'ingredientes'                => 'present|array',
            'ingredientes.*.cd_ingrediente' => 'integer|exists:ingrediente,cd_ingrediente',
            'ingredientes.*.nm_ingrediente' => 'string|max:100',
        ]);

        session(['meubar_ingredientes' => $request->input('ingredientes', [])]);

        return response()->json(['success' => true]);
    }

    public function buscarIngredientes(Request $request)
    {
        $query = $request->get('q');

        $ingredientes = Ingrediente::where('nm_ingrediente', 'ilike', "%{$query}%")
            ->limit(20)
            ->get(['cd_ingrediente', 'nm_ingrediente']);

        return response()->json($ingredientes);
    }

    public function obterBebidasPossiveis(Request $request)
    {
        $request->validate([
            'ingredientes' => 'required|array|min:1',
            'ingredientes.*' => 'integer|exists:ingrediente,cd_ingrediente',
        ]);

        $ingredienteIds = $request->input('ingredientes');
        $idsLiteral = '{' . implode(',', $ingredienteIds) . '}';

        $drinks = DB::select("
            SELECT b.cd_bebida,
                   b.nm_bebida,
                   b.ds_imagem,
                   b.id_tipo,
                   COALESCE(ROUND(AVG(a.id_nota), 1), 0) AS nota,
                   COUNT(DISTINCT a.cd_avaliacao) AS qt_avaliacao,
                   COUNT(DISTINCT bi.cd_ingrediente) AS total_ingredientes,
                   COUNT(DISTINCT bi.cd_ingrediente) FILTER (WHERE bi.cd_ingrediente = ANY(?)) AS matched,
                   COUNT(DISTINCT bi.cd_ingrediente) - COUNT(DISTINCT bi.cd_ingrediente) FILTER (WHERE bi.cd_ingrediente = ANY(?)) AS faltando
              FROM bebida b
              JOIN bebida_ingrediente bi ON bi.cd_bebida = b.cd_bebida
              LEFT JOIN avaliacao a ON a.cd_bebida = b.cd_bebida
             GROUP BY b.cd_bebida, b.nm_bebida, b.ds_imagem, b.id_tipo
            HAVING COUNT(DISTINCT bi.cd_ingrediente) - COUNT(DISTINCT bi.cd_ingrediente) FILTER (WHERE bi.cd_ingrediente = ANY(?)) <= 2
               AND COUNT(DISTINCT bi.cd_ingrediente) FILTER (WHERE bi.cd_ingrediente = ANY(?)) > 0
             ORDER BY faltando ASC, nota DESC, b.nm_bebida ASC
        ", [$idsLiteral, $idsLiteral, $idsLiteral, $idsLiteral]);

        $prontos = [];
        $quaseLa = [];

        foreach ($drinks as $drink) {
            if ((int) $drink->faltando === 0) {
                $prontos[] = $drink;
            } else {
                $missing = DB::select("
                    SELECT i.nm_ingrediente
                      FROM bebida_ingrediente bi
                      JOIN ingrediente i ON i.cd_ingrediente = bi.cd_ingrediente
                     WHERE bi.cd_bebida = ?
                       AND bi.cd_ingrediente <> ALL(?)
                ", [$drink->cd_bebida, $idsLiteral]);

                $drink->ingredientes_faltando = array_map(fn($m) => $m->nm_ingrediente, $missing);
                $quaseLa[] = $drink;
            }
        }

        return response()->json([
            'prontos' => $prontos,
            'quase_la' => $quaseLa,
        ]);
    }
}
