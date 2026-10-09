<?php

namespace App\Console\Commands;

use App\Services\Invoices\InvoiceExtractionService;
use App\Support\Money;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('invoices:extract {path}')]
#[Description('Extract and check the data in a supplier invoice')]
class ExtractInvoice extends Command
{
    public function handle(InvoiceExtractionService $service): int
    {
        $result = $service->extract(
            file_get_contents($this->argument('path'))
        );

        if ($invoice = $result->invoice) {
            $this->line("{$invoice->supplier}, {$invoice->number}, "
                .($invoice->date?->toDateString() ?? 'no date'));

            foreach ($invoice->lines as $line) {
                $this->line("  {$line['quantity']} x "
                    .Money::inr($line['unit_cents'])
                    ."  {$line['description']}");
            }

            $this->line('  Total: '.Money::inr($invoice->totalCents));
        }

        foreach ($result->issues as $issue) {
            $this->warn($issue);
        }

        return $result->needsReview()
            ? self::FAILURE : self::SUCCESS;
    }
}
