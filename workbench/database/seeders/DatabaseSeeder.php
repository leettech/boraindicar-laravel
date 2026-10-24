<?php

namespace Workbench\Database\Seeders;

use BoraIndicar\Laravel\Actions\PingHub;
use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use BoraIndicar\Laravel\Models\BoraIndicarReferral;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Workbench\App\Models\User;

/** Realistic outbox rows for the dashboard screenshot. Nothing is sent: rows are inserted directly. */
class DatabaseSeeder extends Seeder
{
    public function run(Cache $cache): void
    {
        User::query()->create(['name' => 'Dev', 'email' => 'dev@test.com', 'password' => '123']);

        $customers = collect([
            ['Ana Souza', 'ana@cliente.com.br', 'mar7k2qp', true],
            ['Bruno Lima', 'bruno@empresa.com', 'mar7k2qp', true],
            ['Carla Dias', 'carla.dias@gmail.com', 'ze4h9wnc', true],
            ['Diego Alves', 'diego@startup.io', 'ze4h9wnc', false],
            ['Elisa Rocha', 'elisa@rocha.adv.br', 'pq3x8vtd', true],
            ['Fábio Nunes', 'fabio@nunes.dev', 'pq3x8vtd', true],
        ])->map(fn (array $row, int $i) => [
            'user' => User::query()->create([
                'name' => $row[0],
                'email' => $row[1],
                'password' => 'secret',
                'stripe_id' => 'cus_'.str_pad((string) ($i + 1), 14, 'Q', STR_PAD_LEFT),
                'created_at' => now()->subDays(20 - $i * 3),
            ]),
            'code' => $row[2],
            'attributed' => $row[3],
        ]);

        $at = fn (int $hoursAgo) => now()->subHours($hoursAgo);

        foreach ($customers as $i => $customer) {
            $user = $customer['user'];
            $clickId = $i % 2 === 0 ? '01JB'.strtoupper(substr(md5((string) $i), 0, 22)) : null;

            $this->event(EventType::Click, 'click:01JB'.strtoupper(substr(md5("c{$i}"), 0, 22)), [
                'code' => $customer['code'], 'landing_url' => "https://app.exemplo.com.br/?ref={$customer['code']}", 'referer' => 'https://instagram.com/', 'utm' => ['utm_source' => 'instagram'],
            ], EventStatus::Sent, $at(500 - $i * 70), ['click_id' => '01JBCLICK'.$i]);

            $this->event(EventType::Lead, "lead:{$user->id}", [
                'external_id' => (string) $user->id, 'email' => $user->email, 'name' => $user->name,
                'code' => $customer['code'], 'click_id' => $clickId, 'utm' => ['utm_source' => 'instagram'],
                'signed_up_at' => $user->created_at->toIso8601String(),
            ], EventStatus::Sent, $at(480 - $i * 70), ['referral' => ['id' => 100 + $i, 'status' => 'lead', 'attributed' => $customer['attributed']]]);

            BoraIndicarReferral::query()->create([
                'customer_id' => (string) $user->id,
                'code' => $customer['code'],
                'click_id' => $clickId,
                'utm' => ['utm_source' => 'instagram', 'utm_campaign' => 'lancamento'],
                'attributed' => $customer['attributed'],
                'hub_referral_id' => (string) (100 + $i),
                'created_at' => $at(480 - $i * 70),
            ]);

            foreach ([0, 1] as $month) {
                $invoice = 'in_1Q'.strtoupper(substr(md5("{$i}-{$month}"), 0, 20));
                $this->event(EventType::Payment, "payment:{$invoice}", [
                    'customer_external_id' => (string) $user->id, 'external_id' => $invoice, 'amount_cents' => 9700,
                    'currency' => 'BRL', 'paid_at' => $at(400 - $i * 60 - $month * 30)->toIso8601String(),
                ], EventStatus::Sent, $at(400 - $i * 60 - $month * 30), [
                    'payment' => ['id' => 300 + $i * 2 + $month],
                    'commission' => ['amount_cents' => 2910, 'status' => 'pending'],
                    'attributed' => $customer['attributed'],
                ]);
            }
        }

        $this->event(EventType::Refund, 'refund:in_1QREFUNDED0000000000:total', ['payment_external_id' => 'in_1QREFUNDED0000000000'], EventStatus::Sent, $at(30), ['payment' => ['id' => 301, 'refunded_cents' => 9700]]);
        $this->event(EventType::Cancel, 'cancel:4:sub_1QCANCELED0000000', ['external_id' => '4'], EventStatus::Sent, $at(26), ['status' => 'canceled']);

        $this->event(EventType::Payment, 'payment:in_1QINVALIDCURRENCY000', [
            'customer_external_id' => '3', 'external_id' => 'in_1QINVALIDCURRENCY000', 'amount_cents' => 0, 'currency' => 'BRL', 'paid_at' => $at(20)->toIso8601String(),
        ], EventStatus::Failed, $at(20), ['message' => 'The amount cents field must be at least 1.', 'errors' => ['amount_cents' => ['The amount cents field must be at least 1.']]], 1, 'HTTP 422: The amount cents field must be at least 1.');

        $this->event(EventType::Lead, 'lead:999', [
            'external_id' => '999', 'email' => 'teste@exemplo.com', 'name' => 'Teste', 'code' => 'mar7k2qp', 'click_id' => null, 'utm' => [], 'signed_up_at' => $at(12)->toIso8601String(),
        ], EventStatus::Failed, $at(12), ['message' => 'Unauthenticated.'], 1, 'HTTP 401: Unauthenticated.');

        $this->event(EventType::Payment, 'payment:in_1QHUBDOWN000000000000', [
            'customer_external_id' => '2', 'external_id' => 'in_1QHUBDOWN000000000000', 'amount_cents' => 19700, 'currency' => 'BRL', 'paid_at' => $at(2)->toIso8601String(),
        ], EventStatus::Pending, $at(2), null, 3, 'cURL error 7: Failed to connect to boraindicar.com port 443');

        $this->event(EventType::Click, 'click:01JBPENDINGCLICK00000000000', [
            'code' => 'ze4h9wnc', 'landing_url' => 'https://app.exemplo.com.br/precos?ref=ze4h9wnc', 'referer' => null, 'utm' => [],
        ], EventStatus::Pending, $at(1), null, 0);

        $cache->forever(PingHub::CACHE_KEY, [
            'ok' => true,
            'at' => now()->subMinutes(14)->toIso8601String(),
            'product' => ['slug' => 'exemplo', 'name' => 'Exemplo SaaS'],
            'error' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $response
     */
    private function event(
        EventType $type,
        string $key,
        array $payload,
        EventStatus $status,
        Carbon $createdAt,
        ?array $response,
        int $attempts = 1,
        ?string $error = null,
    ): void {
        BoraIndicarEvent::query()->forceCreate([
            'type' => $type,
            'idempotency_key' => $key,
            'payload' => $payload,
            'status' => $status,
            'attempts' => $attempts,
            'last_error' => $error,
            'response' => $response,
            'sent_at' => $status === EventStatus::Sent ? $createdAt->copy()->addSeconds(2) : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
