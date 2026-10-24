<?php

namespace BoraIndicar\Laravel\Models;

use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Enums\EventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One fact reported to the hub (outbox row).
 *
 * @property int $id
 * @property EventType $type
 * @property string $idempotency_key
 * @property array<string, mixed> $payload
 * @property EventStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property array<string, mixed>|null $response
 * @property Carbon|null $sent_at
 */
class BoraIndicarEvent extends Model
{
    protected $table = 'boraindicar_events';

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'status' => EventStatus::class,
            'payload' => 'array',
            'response' => 'array',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function markSent(?array $response): void
    {
        $this->forceFill([
            'status' => EventStatus::Sent,
            'response' => $response,
            'last_error' => null,
            'sent_at' => now(),
        ])->save();
    }

    public function markFailed(string $error, ?array $response = null): void
    {
        $this->forceFill([
            'status' => EventStatus::Failed,
            'last_error' => $error,
            'response' => $response ?? $this->response,
        ])->save();
    }

    public function markRetrying(string $error): void
    {
        $this->forceFill([
            'status' => EventStatus::Pending,
            'last_error' => $error,
        ])->save();
    }

    /** Back to the start of the delivery cycle (manual "Reenviar"). */
    public function resetForRetry(): void
    {
        $this->forceFill([
            'status' => EventStatus::Pending,
            'attempts' => 0,
            'last_error' => null,
        ])->save();
    }

    public function scopeWithStatus(Builder $query, EventStatus $status): void
    {
        $query->where('status', $status);
    }

    public function scopeOfType(Builder $query, EventType $type): void
    {
        $query->where('type', $type);
    }
}
