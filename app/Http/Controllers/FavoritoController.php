<?php

namespace App\Http\Controllers;

use App\Models\Favorito;
use App\Models\Bebida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoritoController extends Controller
{
    public function index()
    {
        $favoritos = DB::table('favorito as f')
            ->join('bebida as b', 'f.cd_bebida', '=', 'b.cd_bebida')
            ->leftJoin('avaliacao as a', 'b.cd_bebida', '=', 'a.cd_bebida')
            ->select('b.cd_bebida', 'b.nm_bebida', 'b.ds_imagem',
                     DB::raw('COALESCE(ROUND(AVG(a.id_nota), 1), 0) AS nota'),
                     DB::raw('COUNT(a.id_nota) AS qt_avaliacao'))
            ->where('f.id_usuario', Auth::id())
            ->groupBy('b.cd_bebida', 'b.nm_bebida', 'b.ds_imagem', 'f.created_at')
            ->orderBy('f.created_at', 'desc')
            ->get();

        return view('favoritos.index', compact('favoritos'));
    }

    public function toggle($cd_bebida)
    {
        $favorito = Favorito::where('id_usuario', Auth::id())
            ->where('cd_bebida', $cd_bebida)
            ->first();

        if ($favorito) {
            $favorito->delete();
            return response()->json([
                'success' => true,
                'favorited' => false,
                'message' => 'Removido dos favoritos'
            ]);
        } else {
            Favorito::create([
                'id_usuario' => Auth::id(),
                'cd_bebida' => $cd_bebida,
            ]);
            return response()->json([
                'success' => true,
                'favorited' => true,
                'message' => 'Adicionado aos favoritos'
            ]);
        }
    }

    public function check($cd_bebida)
    {
        $favorited = Favorito::where('id_usuario', Auth::id())
            ->where('cd_bebida', $cd_bebida)
            ->exists();

        return response()->json([
            'favorited' => $favorited
        ]);
    }
}
