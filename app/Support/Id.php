<?php

namespace App\Support;

/**
 * Valida um id vindo da URL antes que ele chegue ao banco.
 *
 * As chaves do catálogo são `increments`, ou seja, `integer` no Postgres, que
 * vai até 2147483647. As rotas restringem o parâmetro com `whereNumber`, que é
 * `[0-9]+` e não limita a quantidade de dígitos — então `/bebida/9999999999`
 * casa com a rota, cabe folgado no int64 do PHP, e só estoura lá no banco: o
 * Postgres recusa o parâmetro bindado com SQLSTATE 22003 e a QueryException
 * não tratada vira HTTP 500 numa rota pública.
 *
 * Há duas portas, e é por isso que a checagem é feita sobre a string crua:
 * além do erro do Postgres, uma assinatura de método tipada como `int` lança
 * TypeError na coerção de um id de vinte dígitos, antes mesmo do método rodar
 * — um 500 que nenhuma validação dentro do método alcançaria. Por isso os
 * controllers recebem o parâmetro como `string` e passam por aqui.
 *
 * Este defeito já voltou por cinco portas diferentes neste projeto. Antes de
 * escrever a checagem de novo em algum controller, use esta classe.
 */
class Id
{
    /** Maior valor que o tipo `integer` do Postgres aceita. */
    public const MAX_INTEGER_POSTGRES = 2147483647;

    /**
     * O id como inteiro, ou 404 se estiver fora da faixa que o banco aceita.
     *
     * `null` também é 404: as rotas com parâmetro opcional (`{cd_bebida?}`)
     * chamam o controller sem argumento, e sem esse tratamento o PHP lançaria
     * ArgumentCountError — outro 500 onde o certo é 404.
     */
    public static function validar(?string $valor): int
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => self::MAX_INTEGER_POSTGRES],
        ]);

        if ($id === false) {
            abort(404);
        }

        return $id;
    }
}
