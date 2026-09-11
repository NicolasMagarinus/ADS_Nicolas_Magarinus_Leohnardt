<?php

namespace App\Models;

use App\Enums\TipoBebida;
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

    protected $casts = [
        'id_tipo' => TipoBebida::class,
    ];

    public function avaliacao()
    {
        return $this->hasMany(Avaliacao::class, 'cd_bebida');
    }

    public function favoritos()
    {
        return $this->hasMany(Favorito::class, 'cd_bebida');
    }

    /**
     * Sorteia o código de uma bebida do catálogo, ou null se não houver
     * nenhuma.
     *
     * O ORDER BY RANDOM() ficou, mas agora sobre uma coluna só de bebida — sem
     * o join de avaliações, sem o GROUP BY e, principalmente, sem o json_agg
     * dos ingredientes, que antes era montado para cada bebida do catálogo só
     * para todas serem descartadas menos uma. Num catálogo de 5 mil bebidas,
     * a diferença medida foi de 3,7 ms para 0,7 ms somando as duas etapas.
     */
    private static function sortearCodigo(): ?int
    {
        $sorteada = DB::selectOne('SELECT cd_bebida FROM bebida ORDER BY RANDOM() LIMIT 1');

        return $sorteada?->cd_bebida;
    }

    public static function getBebida($cd_bebida = null)
    {
        if ($cd_bebida === null) {
            $cd_bebida = static::sortearCodigo();

            if ($cd_bebida === null) {
                return null;
            }
        }

        $sql = <<<SQL
            SELECT b.cd_bebida,
                   b.nm_bebida,
                   b.ds_preparo,
                   b.id_tipo,
                   b.ds_bebida,
                   b.ds_imagem,
                   b.created_at,
                   b.updated_at,
                   COALESCE(ROUND(AVG(a.id_nota), 1), 0) AS nota,
                   COUNT(DISTINCT a.cd_avaliacao) AS qt_avaliacao,
                   COALESCE((SELECT json_agg(json_build_object('cd_ingrediente', i.cd_ingrediente, 'nm_ingrediente', i.nm_ingrediente, 'ds_medida', bi.ds_medida) ORDER BY i.nm_ingrediente)
                               FROM ingrediente AS i
                               JOIN bebida_ingrediente AS bi ON i.cd_ingrediente = bi.cd_ingrediente
                              WHERE bi.cd_bebida = b.cd_bebida), '[]') AS ingredientes_json
              FROM bebida AS b
              LEFT JOIN avaliacao AS a ON b.cd_bebida = a.cd_bebida
             WHERE b.cd_bebida = ?
             GROUP BY b.cd_bebida, b.nm_bebida, b.ds_preparo, b.id_tipo, b.ds_bebida, b.ds_imagem, b.created_at, b.updated_at
             LIMIT 1
SQL;

        $bebida = DB::selectOne($sql, [$cd_bebida]);

        if (!$bebida) {
            return null;
        }

        $bebida->ingredientes = json_decode($bebida->ingredientes_json, true) ?? [];
        $bebida->preparo = array_filter(array_map('trim', explode('.', $bebida->ds_preparo)));
        unset($bebida->ingredientes_json);
        $bebida->id = $bebida->cd_bebida;

        $bebida->avaliacoes = DB::table('avaliacao as a')
            ->join('users as u', 'a.id_usuario', '=', 'u.id')
            ->select('u.name as nm_usuario', 'a.ds_avaliacao', 'a.created_at', 'a.id_nota as nota',
                     'a.id_usuario', 'a.cd_avaliacao')
            ->where('a.cd_bebida', $bebida->cd_bebida)
            ->orderByDesc('a.created_at')
            ->get();

        return $bebida;
    }
}
