<?php

namespace App\Enums;

/**
 * Coluna bebida.id_tipo e cadastro_bebida.id_tipo.
 *
 * Os valores são os que já estão gravados no banco e não podem mudar.
 */
enum TipoBebida: int
{
    case Alcoolica = 1;
    case NaoAlcoolica = 2;

    public function label(): string
    {
        return match ($this) {
            self::Alcoolica => 'Alcoólica',
            self::NaoAlcoolica => 'Não alcoólica',
        };
    }
}
