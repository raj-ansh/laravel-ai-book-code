<?php

namespace App\Services\Intake;

use App\Ai\Agents\IntakeCaseExtractor as Extractor;
use App\Models\IntakeCase;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Any input in, one checked case out: the attachment becomes
 * text, the text becomes a case, and code checks the case
 * against the customer's own data.
 */
class IntakeProcessor
{
    /** Categories that cannot be handled without an order. */
    private const NEEDS_ORDER = [
        'damaged_item', 'not_delivered', 'refund_request',
    ];

    public function __construct(private IntakeEvidence $evidence) {}

    public function process(IntakeCase $case): IntakeCase
    {
        $issues = [];
        $seen = null; // Damage visible in a photo: true, false, null.

        if ($case->attachment_path && ! $case->attachment_kind) {
            $issues[] = 'The attachment type is not supported.';
        } elseif ($case->attachment_path) {
            $read = $this->evidence->read($case->attachment_path,
                $case->attachment_kind, $case->attachment_mime,
                $case->message);
            [$case->evidence, $seen] = [$read['text'],
                $read['damage_visible']];

            if ($read['text'] === '') {
                $issues[] = "The {$case->attachment_kind} could "
                    .'not be read.';
            }
        }

        $result = Extractor::make()->prompt($this->prompt($case));
        $category = self::listed($result, 'category',
            Extractor::CATEGORIES);
        $number = strtoupper(trim(
            (string) ($result['order_number'] ?? '')
        ));

        $order = $number !== '' ? $this->orderOf($case, $number) : null;

        if ($number !== '' && ! $order) {
            $issues[] = "Order {$number} is not one of this "
                .'customer\'s orders.';
        } elseif ($number === ''
            && in_array($category, self::NEEDS_ORDER, true)) {
            $issues[] = 'No order number: ask the customer which '
                .'order.';
        }

        if ($category === 'damaged_item' && $seen === false) {
            $issues[] = 'The photo shows no visible damage.';
        }

        if ($category === 'other') {
            $issues[] = 'Not a known request type: route by hand.';
        }

        $case->fill([
            'category' => $category,
            'order_id' => $order?->id,
            'fields' => [
                'order_number' => $number ?: null,
                'wants' => self::listed($result, 'wants',
                    Extractor::WANTS),
                'summary' => Str::limit(
                    (string) ($result['summary'] ?? ''), 300,
                ),
                'damage_visible' => $seen,
            ],
            'issues' => $issues,
            'status' => $issues === [] ? 'open' : 'needs_review',
        ])->save();

        return $case;
    }

    private function prompt(IntakeCase $case): string
    {
        $kind = str_replace('_', ' ', (string) $case->attachment_kind);

        return "Customer message:\n{$case->message}"
            .($case->evidence
                ? "\n\nAttachment ({$kind}):\n{$case->evidence}"
                : '');
    }

    /**
     * The order must appear in what the customer sent, and
     * must be theirs.
     */
    private function orderOf(IntakeCase $case, string $number): ?Order
    {
        $source = $case->message."\n".$case->evidence;

        return Str::contains($source, $number, ignoreCase: true)
            ? $case->user->orders()->where('number', $number)->first()
            : null;
    }

    /**
     * @param  mixed  $result
     * @param  list<string>  $allowed
     */
    private static function listed(
        $result,
        string $field,
        array $allowed,
    ): string {
        $value = $result[$field] ?? null;

        return in_array($value, $allowed, true) ? $value : 'other';
    }
}
