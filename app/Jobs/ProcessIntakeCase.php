<?php

namespace App\Jobs;

use App\Models\IntakeCase;
use App\Services\Intake\IntakeProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessIntakeCase implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public int $timeout = 300;

    public function __construct(public IntakeCase $case) {}

    public function uniqueId(): string
    {
        return (string) $this->case->id;
    }

    public function handle(IntakeProcessor $processor): void
    {
        $processor->process($this->case);
    }

    public function failed(?Throwable $e): void
    {
        $this->case->update([
            'status' => 'failed',
            'issues' => [$e?->getMessage() ?? 'Processing failed.'],
        ]);
    }
}
