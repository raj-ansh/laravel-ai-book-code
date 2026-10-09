<?php

namespace App\Jobs;

use App\Ai\Agents\DamagePhotoInspector;
use App\Models\DamageReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image;

class InspectDamagePhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(public DamageReport $report) {}

    public function handle(): void
    {
        $order = $this->report->order;

        $result = DamagePhotoInspector::make()->prompt(
            "Order {$order->number}. The customer says this item "
                .'arrived damaged. What does the photo show?',
            attachments: [
                Image::fromStorage($this->report->photo_path, 'local'),
            ],
        );

        $type = in_array($result['damage_type'] ?? null,
            DamagePhotoInspector::TYPES, true)
            ? $result['damage_type'] : 'other';

        // The model describes; a person decides the claim.
        $this->report->update([
            'status' => 'inspected',
            'damage_visible' => ($result['damage_visible'] ?? null)
                === true,
            'damage_type' => $type,
            'description' => Str::limit(
                (string) ($result['description'] ?? ''), 500,
            ),
        ]);
    }
}
