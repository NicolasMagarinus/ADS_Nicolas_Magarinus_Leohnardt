<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
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
            return static::create(['nm_ingrediente' => Str::ucfirst(Str::lower($nome))]);
        } catch (QueryException $e) {
            // Corrida com outra escrita: a unique barrou, então a linha existe.
            return static::porNome($nome) ?? throw $e;
        }
    }

    protected static function porNome(string $nome): ?self
    {
        return static::whereRaw('lower(f_unaccent(nm_ingrediente)) = lower(f_unaccent(?))', [$nome])->first();
    }
}
