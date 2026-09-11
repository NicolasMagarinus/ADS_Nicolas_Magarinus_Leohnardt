<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Valor de query string como texto, tolerando o que chega torto da URL.
     *
     * Dois casos reais: "?q=" vira null pelo middleware
     * ConvertEmptyStringsToNull, e "?q[]=abc" chega como array. Tanto um cast
     * (string) quanto o $request->string() do Laravel estouram
     * "Array to string conversion" no segundo — e isso derrubava a busca, que
     * é página pública e sem login, ao alcance de qualquer rastreador.
     */
    protected function textoDaQuery(Request $request, string $chave): string
    {
        $valor = $request->get($chave);

        return is_scalar($valor) ? trim((string) $valor) : '';
    }
}
