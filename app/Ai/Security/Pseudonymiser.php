<?php

namespace App\Ai\Security;

/**
 * Swaps personal data for placeholders before text leaves
 * the application, and swaps them back in what returns.
 * One instance per request: the map lives only in memory.
 */
final class Pseudonymiser
{
    /** Checked in this order: cards before phone numbers. */
    private const PATTERNS = [
        'EMAIL' => '/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/',
        'CARD' => '/\b(?:\d[ -]?){12,18}\d\b/',
        'PAN' => '/\b[A-Z]{5}\d{4}[A-Z]\b/',
        'PHONE' => '/(?:\+91[\s-]?)?\b[6-9]\d{4}[\s-]?\d{5}\b/',
    ];

    /** @var array<string, string> placeholder => original */
    private array $map = [];

    public function hide(string $text): string
    {
        foreach (self::PATTERNS as $type => $pattern) {
            $text = preg_replace_callback($pattern,
                fn (array $m) => $type === 'CARD' && ! self::luhn($m[0])
                    ? $m[0] : $this->placeholder($type, $m[0]),
                $text);
        }

        return $text;
    }

    public function restore(string $text): string
    {
        return strtr($text, $this->map);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function restoreAll(array $data): array
    {
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                $value = $this->restore($value);
            }
        });

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function hidden(): array
    {
        return $this->map;
    }

    private function placeholder(string $type, string $value): string
    {
        $existing = array_search($value, $this->map, true);

        if ($existing !== false) {
            return $existing;
        }

        $number = count(array_filter(array_keys($this->map),
            fn (string $key) => str_starts_with($key, "[{$type}_")));
        $key = "[{$type}_".($number + 1).']';
        $this->map[$key] = $value;

        return $key;
    }

    /** Card numbers pass the Luhn check; order totals do not. */
    private static function luhn(string $number): bool
    {
        $digits = array_reverse(str_split(
            preg_replace('/\D/', '', $number)
        ));
        $sum = 0;

        foreach ($digits as $i => $digit) {
            $digit = (int) $digit * ($i % 2 ? 2 : 1);
            $sum += $digit > 9 ? $digit - 9 : $digit;
        }

        return $sum % 10 === 0;
    }
}
