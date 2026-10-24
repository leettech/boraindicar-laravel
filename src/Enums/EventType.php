<?php

namespace BoraIndicar\Laravel\Enums;

enum EventType: string
{
    case Click = 'click';
    case Lead = 'lead';
    case Payment = 'payment';
    case Refund = 'refund';
    case Cancel = 'cancel';

    public function label(): string
    {
        return match ($this) {
            self::Click => 'Clique',
            self::Lead => 'Cadastro',
            self::Payment => 'Pagamento',
            self::Refund => 'Reembolso',
            self::Cancel => 'Cancelamento',
        };
    }
}
