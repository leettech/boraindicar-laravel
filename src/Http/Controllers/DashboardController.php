<?php

namespace BoraIndicar\Laravel\Http\Controllers;

use BoraIndicar\Laravel\Actions\PingHub;
use BoraIndicar\Laravel\BoraIndicarManager;
use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use BoraIndicar\Laravel\Models\BoraIndicarReferral;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class DashboardController
{
    public function index(Request $request, PingHub $ping, ConfigRepository $config, BoraIndicarManager $boraindicar): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(EventStatus::class)],
            'type' => ['nullable', Rule::enum(EventType::class)],
        ]);

        $status = isset($filters['status']) ? EventStatus::from($filters['status']) : null;
        $type = isset($filters['type']) ? EventType::from($filters['type']) : null;

        $counts = BoraIndicarEvent::query()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $events = BoraIndicarEvent::query()
            ->when($status, fn ($query) => $query->withStatus($status))
            ->when($type, fn ($query) => $query->ofType($type))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $referrals = BoraIndicarReferral::query()->latest('id')->limit(20)->get();

        return view('boraindicar::dashboard', [
            'counts' => collect(EventStatus::cases())->mapWithKeys(fn (EventStatus $case) => [$case->value => (int) ($counts[$case->value] ?? 0)]),
            'lastPing' => $ping->last(),
            'hubUrl' => $config->get('boraindicar.url'),
            'enabled' => (bool) $config->get('boraindicar.enabled'),
            'events' => $events,
            'referrals' => $referrals,
            'customers' => $this->customersOf($referrals, $boraindicar->userModel(), $config),
            'status' => $status,
            'type' => $type,
        ]);
    }

    /**
     * Customer models keyed by the attribute sent as external_id, in one query.
     *
     * @param  Collection<int, BoraIndicarReferral>  $referrals
     * @param  class-string|null  $model
     * @return Collection<string, Model>
     */
    private function customersOf(Collection $referrals, ?string $model, ConfigRepository $config): Collection
    {
        $attribute = $config->get('boraindicar.customer_id', 'id');

        if ($referrals->isEmpty() || $model === null || ! is_subclass_of($model, Model::class)) {
            return collect();
        }

        return $model::query()
            ->whereIn($attribute, $referrals->pluck('customer_id'))
            ->get()
            ->keyBy(fn (Model $customer) => (string) $customer->getAttribute($attribute));
    }
}
