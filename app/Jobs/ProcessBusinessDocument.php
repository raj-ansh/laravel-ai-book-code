<?php

namespace App\Jobs;

use App\Models\BusinessDocument;
use App\Services\Documents\DocumentProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessBusinessDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public int $timeout = 300;

    public function __construct(public BusinessDocument $document) {}

    public function uniqueId(): string
    {
        return (string) $this->document->id;
    }

    public function handle(DocumentProcessor $processor): void
    {
        $processor->process($this->document);
    }

    public function failed(?Throwable $e): void
    {
        $this->document->update([
            'status' => 'failed',
            'issues' => [$e?->getMessage() ?? 'Processing failed.'],
        ]);
    }
}
