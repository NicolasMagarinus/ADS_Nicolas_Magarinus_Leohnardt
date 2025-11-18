<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Bebida extends Model
{
    protected $table = 'bebida';

    protected $primaryKey = 'cd_bebida';

    protected $fillable = [
        'nm_bebida',
        'ds_preparo',
        'id_tipo',
        'ds_bebida',
        'ds_imagem'
    ];

    public static function getBebida($cd_bebida = null)
    {
        $whereClause = '';
        $bindings = [];
        $orderBy = '';

        if ($cd_bebida !== null) {
            $whereClause = 'WHERE b.cd_bebida = ?';
            $bindings = [$cd_bebida];
        } else {
            $orderBy = 'ORDER BY RANDOM()';
        }

        $sql = <<<SQL
            SELECT b.*, 
                   COALESCE(ROUND(AVG(a.id_nota), 1), 0) AS nota,
                   COUNT(a.id_nota) AS qt_avaliacao,
                   COALESCE((SELECT json_agg(json_build_object('nm_ingrediente', i.nm_ingrediente, 'ds_medida', bi.ds_medida) ORDER BY i.nm_ingrediente)
                               FROM ingrediente AS i
                               JOIN bebida_ingrediente AS bi ON i.cd_ingrediente = bi.cd_ingrediente
                              WHERE bi.cd_bebida = b.cd_bebida), '[]') AS ingredientes_json
              FROM bebida AS b
              LEFT JOIN avaliacao AS a ON b.cd_bebida = a.cd_bebida
             {$whereClause}
             GROUP BY b.cd_bebida, b.nm_bebida, b.ds_imagem, b.ds_preparo
             {$orderBy}
             LIMIT 1
SQL;

        $bebida = DB::selectOne($sql, $bindings);

        if (!$bebida) {
            return null;
        }

        $bebida->ingredientes = json_decode($bebida->ingredientes_json, true) ?? [];
        $bebida->preparo = array_filter(array_map('trim', explode('.', $bebida->ds_preparo)));
        unset($bebida->ingredientes_json);
        $bebida->id = $bebida->cd_bebida;

        $bebida->avaliacoes = DB::table('avaliacao as a')
            ->join('users as u', 'a.id_usuario', '=', 'u.id')
            ->select('u.name as nm_usuario', 'a.ds_avaliacao', 'a.created_at', 'a.id_nota as nota')
            ->where('a.cd_bebida', $bebida->cd_bebida)
            ->orderByDesc('a.created_at')
            ->get();

        return $bebida;
    }
}
