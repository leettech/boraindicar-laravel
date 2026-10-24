<?php

namespace BoraIndicar\Laravel;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * Who referred the visitor: affiliate code, hub click id and UTMs.
 * Stored in the cookie as JSON {c, k, t, u} (code, click, timestamp, utms).
 */
final readonly class Attribution
{
    private const CODE_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    private const CLICK_PATTERN = '/^[0-9A-Za-z]{26}$/';

    /**
     * @param  array<string, string>  $utm
     */
    public function __construct(
        public string $code,
        public ?string $clickId,
        public array $utm,
        public CarbonImmutable $capturedAt,
    ) {}

    /**
     * @param  list<string>  $queryParams
     */
    public static function fromRequest(Request $request, array $queryParams = ['ref', 'via']): ?self
    {
        $code = null;

        foreach ($queryParams as $param) {
            $value = $request->query($param);

            if (is_string($value) && preg_match(self::CODE_PATTERN, trim($value))) {
                $code = trim($value);
                break;
            }
        }

        if ($code === null) {
            return null;
        }

        $query = $request->query();

        return new self(
            $code,
            self::clickId($query['ix'] ?? null),
            self::utm($query),
            CarbonImmutable::now(),
        );
    }

    public static function fromCookie(?string $json): ?self
    {
        if ($json === null || $json === '') {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? self::fromArray($data) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $code = $data['c'] ?? null;

        if (! is_string($code) || ! preg_match(self::CODE_PATTERN, $code)) {
            return null;
        }

        $timestamp = $data['t'] ?? null;

        return new self(
            $code,
            self::clickId($data['k'] ?? null),
            self::utm(is_array($data['u'] ?? null) ? $data['u'] : []),
            is_int($timestamp) ? CarbonImmutable::createFromTimestampUTC($timestamp) : CarbonImmutable::now(),
        );
    }

    /**
     * @return array{c: string, k: string|null, t: int, u: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'c' => $this->code,
            'k' => $this->clickId,
            't' => $this->capturedAt->getTimestamp(),
            'u' => $this->utm,
        ];
    }

    public function toCookieValue(): string
    {
        return json_encode(array_replace($this->toArray(), ['u' => (object) $this->utm]), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function isExpired(int $days, ?CarbonInterface $now = null): bool
    {
        return $this->capturedAt->addDays($days)->lessThan($now ?? CarbonImmutable::now());
    }

    private static function clickId(mixed $value): ?string
    {
        return is_string($value) && preg_match(self::CLICK_PATTERN, $value) ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, string>
     */
    private static function utm(array $values): array
    {
        $utm = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'utm_') && is_string($value) && $value !== '') {
                $utm[$key] = mb_substr($value, 0, 255);
            }
        }

        return $utm;
    }
}
