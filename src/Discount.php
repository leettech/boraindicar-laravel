<?php

namespace BoraIndicar\Laravel;

use InvalidArgumentException;

/**
 * The discount the hub promises to a referred customer: a percentage (in
 * basis points) for a number of months, applied at checkout as a coupon.
 */
final readonly class Discount
{
    public function __construct(
        public int $percentBp,
        public int $months,
    ) {
        if ($percentBp < 1 || $percentBp > 10000 || $months < 1) {
            throw new InvalidArgumentException("Desconto inválido: {$percentBp} bp por {$months} meses.");
        }
    }

    /** The hub's "discount" object ({percent_bp, months}); null when absent or empty. */
    public static function fromHub(mixed $discount): ?self
    {
        if (! is_array($discount) || ! is_numeric($discount['percent_bp'] ?? null) || ! is_numeric($discount['months'] ?? null)) {
            return null;
        }

        $percentBp = (int) $discount['percent_bp'];
        $months = (int) $discount['months'];

        return $percentBp > 0 && $months > 0 ? new self($percentBp, $months) : null;
    }

    /** 1250 bp is 12.5. */
    public function percent(): float
    {
        return $this->percentBp / 100;
    }

    /** Stable per combination, so the coupon is created once: boraindicar-10pct-12m, boraindicar-12_5pct-3m. */
    public function couponId(): string
    {
        return 'boraindicar-'.str_replace('.', '_', $this->formattedPercent()).'pct-'.$this->months.'m';
    }

    /** "boraindicar: 10% por 12 meses", "boraindicar: 12,5% por 1 mês". */
    public function label(): string
    {
        $months = $this->months === 1 ? '1 mês' : "{$this->months} meses";

        return 'boraindicar: '.str_replace('.', ',', $this->formattedPercent())."% por {$months}";
    }

    private function formattedPercent(): string
    {
        return (string) $this->percent();
    }
}
