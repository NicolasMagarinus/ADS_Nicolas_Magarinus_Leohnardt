<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        // Ajustar para pegar melhor pela colocação
        $sqlAvaliacao = <<<SQL
            SELECT b.cd_bebida, b.nm_bebida, b.ds_imagem, b.id_tipo,
                   ROUND(AVG(a.id_nota), 1) AS nota,
                   COUNT(a.cd_avaliacao)    AS qt_avaliacao
              FROM bebida b
              JOIN avaliacao a ON a.cd_bebida = b.cd_bebida
             GROUP BY b.cd_bebida, b.nm_bebida, b.ds_imagem, b.id_tipo
            HAVING COUNT(a.cd_avaliacao) >= 2
             ORDER BY (ROUND(AVG(a.id_nota), 1) * LOG(COUNT(a.cd_avaliacao) + 1)) DESC
             LIMIT 4
        SQL;

        $arrAvaliacao = DB::select($sqlAvaliacao);

        $sqlIngrediente = <<<SQL
            SELECT i.nm_ingrediente, count(bi.cd_ingrediente) AS qt_utilizado, i.ds_imagem
              FROM ingrediente i
              JOIN bebida_ingrediente bi ON bi.cd_ingrediente = i.cd_ingrediente
             GROUP BY i.nm_ingrediente, i.ds_imagem
             ORDER BY qt_utilizado DESC
             LIMIT 4
SQL;

        $arrIngrediente = DB::select($sqlIngrediente);

        $sqlRecente = <<<SQL
            SELECT b.cd_bebida, b.nm_bebida, b.ds_imagem
              FROM bebida b
             ORDER BY b.created_at DESC
             LIMIT 8
SQL;

        $arrRecente = DB::select($sqlRecente);

        return view('home')
            ->with('arrAvaliacao',   $arrAvaliacao)
            ->with('arrIngrediente', $arrIngrediente)
            ->with('arrRecente',     $arrRecente);
    }
}
