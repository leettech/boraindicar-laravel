<?php

namespace BoraIndicar\Laravel\Enums;

enum EventStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Sent => 'Enviado',
            self::Failed => 'Com falha',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendentes',
            self::Sent => 'Enviados',
            self::Failed => 'Com falha',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'badge-pending',
            self::Sent => 'badge-sent',
            self::Failed => 'badge-failed',
        };
    }
}
