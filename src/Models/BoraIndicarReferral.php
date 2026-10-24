<?php

namespace BoraIndicar\Laravel\Models;

use BoraIndicar\Laravel\Discount;
use Illuminate\Database\Eloquent\Model;

/**
 * A customer of this product who came from an affiliate.
 *
 * @property int $id
 * @property string $customer_id
 * @property string $code
 * @property string|null $click_id
 * @property array<string, string>|null $utm
 * @property bool $attributed
 * @property string|null $hub_referral_id
 * @property int|null $discount_bp null until the hub answers; 0 means no discount
 * @property int|null $discount_months
 */
class BoraIndicarReferral extends Model
{
    protected $table = 'boraindicar_referrals';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'utm' => 'array',
            'attributed' => 'boolean',
            'discount_bp' => 'integer',
            'discount_months' => 'integer',
        ];
    }

    /** False while the hub has not told this product the customer's discount (null discount_bp). */
    public function knowsDiscount(): bool
    {
        return $this->discount_bp !== null;
    }

    public function discount(): ?Discount
    {
        return $this->attributed && $this->discount_bp > 0 && $this->discount_months > 0
            ? new Discount($this->discount_bp, $this->discount_months)
            : null;
    }

    /**
     * Columns from the hub's referral object ({id, attributed, discount}).
     *
     * @param  array<string, mixed>  $referral
     * @return array<string, mixed>
     */
    public static function columnsFromHub(array $referral): array
    {
        $discount = Discount::fromHub($referral['discount'] ?? null);

        return [
            'hub_referral_id' => $referral['id'] ?? null,
            'attributed' => (bool) ($referral['attributed'] ?? false),
            'discount_bp' => $discount?->percentBp ?? 0,
            'discount_months' => $discount?->months,
        ];
    }
}
