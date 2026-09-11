<?php

namespace App\Enums;

/**
 * Coluna cadastro_bebida.id_status: o estado de uma receita enviada por
 * usuário enquanto ela ainda não entrou no catálogo.
 *
 * Os valores são os que já estão gravados no banco e não podem mudar.
 */
enum StatusCadastro: int
{
    case Pendente = 0;
    case Aprovada = 1;
    case Rejeitada = 2;

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Aprovada => 'Aprovada',
            self::Rejeitada => 'Rejeitada',
        };
    }

    /** Classe Bootstrap do badge que o perfil mostra. */
    public function classeBadge(): string
    {
        return match ($this) {
            self::Pendente => 'bg-warning text-dark',
            self::Aprovada => 'bg-success',
            self::Rejeitada => 'bg-danger',
        };
    }

    /** Ícone Bootstrap Icons que acompanha o badge. */
    public function icone(): string
    {
        return match ($this) {
            self::Pendente => 'bi-clock',
            self::Aprovada => 'bi-check',
            self::Rejeitada => 'bi-x',
        };
    }
}
