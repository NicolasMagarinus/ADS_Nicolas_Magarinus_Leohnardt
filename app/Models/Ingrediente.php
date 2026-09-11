<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Ingrediente extends Model
{
    protected $table = 'ingrediente';

    protected $primaryKey = 'cd_ingrediente';

    protected $fillable = ['nm_ingrediente', 'ds_imagem'];

    /**
     * Resolve um nome livre de ingrediente para a linha canônica, criando-a
     * se ainda não existir.
     *
     * Ponto único de escrita: a busca ignora acento e caixa, do mesmo jeito
     * que o índice ingrediente_nome_unico, então "Agua", "Água" e "ÁGUA"
     * resolvem sempre para o mesmo registro.
     */
    public static function normalizar(string $nome): self
    {
        $nome = trim(preg_replace('/\s+/u', ' ', $nome));

        if ($existente = static::porNome($nome)) {
            return $existente;
        }

        try {
            // A inserção vai num savepoint (transação aninhada) porque no
            // PostgreSQL a violação de unique aborta o bloco inteiro: sem ele,
            // o porNome() do catch estouraria 25P02 — "current transaction is
            // aborted" — e a recuperação abaixo nunca chegaria a rodar.
            // Importa porque todos os chamadores já estão dentro de uma
            // transação: aprovação, seeder e os dois comandos de IA.
            return DB::transaction(
                fn () => static::create(['nm_ingrediente' => Str::ucfirst(Str::lower($nome))])
            );
        } catch (QueryException $e) {
            // Corrida com outra escrita: a unique barrou, então a linha existe.
            return static::porNome($nome) ?? throw $e;
        }
    }

    /**
     * Ingredientes que aparecem em ao menos uma receita, do mais usado para o
     * menos usado, já com a contagem em qt_receitas.
     *
     * Serve ao índice /ingredientes e ao filtro da busca: órfão fica de fora
     * dos dois, porque app:gerar-ingredientes-ai cria ingredientes que podem
     * nunca ter sido usados, e filtrar por um deles devolve sempre nada.
     */
    public function scopeUsadosEmReceitas($query)
    {
        return $query
            ->select('ingrediente.cd_ingrediente', 'ingrediente.nm_ingrediente', 'ingrediente.ds_imagem')
            ->selectRaw('COUNT(bi.cd_bebida) AS qt_receitas')
            ->join('bebida_ingrediente as bi', 'bi.cd_ingrediente', '=', 'ingrediente.cd_ingrediente')
            ->groupBy('ingrediente.cd_ingrediente', 'ingrediente.nm_ingrediente', 'ingrediente.ds_imagem')
            ->orderByDesc('qt_receitas')
            ->orderBy('ingrediente.nm_ingrediente');
    }

    protected static function porNome(string $nome): ?self
    {
        return static::whereRaw('lower(f_unaccent(nm_ingrediente)) = lower(f_unaccent(?))', [$nome])->first();
    }
}
