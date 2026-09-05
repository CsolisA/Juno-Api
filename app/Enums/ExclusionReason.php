<?php

namespace App\Enums;

enum ExclusionReason: string
{
    case PendingDebt = 'pending_debt';
    case Behavior = 'behavior';
    case ConfirmedWithdrawal = 'confirmed_withdrawal';
    case Other = 'other';
    case Graduating = 'graduating';

    public function label(): string
    {
        return match ($this) {
            self::PendingDebt => 'Deuda pendiente',
            self::Behavior => 'Conducta',
            self::ConfirmedWithdrawal => 'Retiro confirmado',
            self::Other => 'Otro',
            self::Graduating => 'Egresa',
        };
    }
}
