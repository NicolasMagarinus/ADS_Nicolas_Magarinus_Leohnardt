<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Chaves do cache da home, limpas quando uma bebida entra no catálogo.
     *
     * @see \App\Http\Controllers\CadastroBebidaController::aprovar()
     */
    public const CHAVES_CACHE = ['home.avaliadas', 'home.ingredientes', 'home.recentes'];

    private const TTL_CACHE = 600;

    public function index()
    {
        $sqlAvaliacao = <<<SQL
            SELECT b.cd_bebida, b.nm_bebida, b.ds_imagem, b.id_tipo,
                   ROUND(AVG(a.id_nota), 1) AS nota,
                   COUNT(a.cd_avaliacao)    AS qt_avaliacao
              FROM bebida b
              JOIN avaliacao a ON a.cd_bebida = b.cd_bebida
             GROUP BY b.cd_bebida, b.nm_bebida, b.ds_imagem, b.id_tipo
            HAVING COUNT(a.cd_avaliacao) >= 2
             ORDER BY (ROUND(AVG(a.id_nota), 1) * LOG(COUNT(a.cd_avaliacao) + 1)) DESC
             LIMIT 8
        SQL;

        // Três GROUP BY sobre o catálogo inteiro, na página mais acessada do
        // site. O conteúdo só muda quando entra bebida nova ou alguém avalia.
        $arrAvaliacao = Cache::remember('home.avaliadas', self::TTL_CACHE,
            fn () => DB::select($sqlAvaliacao));

        $sqlIngrediente = <<<SQL
            SELECT i.cd_ingrediente, i.nm_ingrediente, count(bi.cd_ingrediente) AS qt_utilizado, i.ds_imagem
              FROM ingrediente i
              JOIN bebida_ingrediente bi ON bi.cd_ingrediente = i.cd_ingrediente
             GROUP BY i.cd_ingrediente, i.nm_ingrediente, i.ds_imagem
             ORDER BY qt_utilizado DESC
             LIMIT 4
SQL;

        $arrIngrediente = Cache::remember('home.ingredientes', self::TTL_CACHE,
            fn () => DB::select($sqlIngrediente));

        $sqlRecente = <<<SQL
            SELECT b.cd_bebida, b.nm_bebida, b.ds_imagem
              FROM bebida b
             ORDER BY b.created_at DESC
             LIMIT 8
SQL;

        $arrRecente = Cache::remember('home.recentes', self::TTL_CACHE,
            fn () => DB::select($sqlRecente));

        // Fora do cache de propósito: varia por pessoa, e guardado junto
        // serviria o estado de um usuário para outro.
        $hasFavoritesForRecommend = false;
        if (Auth::check()) {
            $hasFavoritesForRecommend = DB::table('favorito')
                ->where('id_usuario', Auth::id())
                ->exists();
        }

        return view('home')
            ->with('arrAvaliacao', $arrAvaliacao)
            ->with('arrIngrediente', $arrIngrediente)
            ->with('arrRecente', $arrRecente)
            ->with('hasFavoritesForRecommend', $hasFavoritesForRecommend);
    }
}
