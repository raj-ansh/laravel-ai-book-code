<?php

namespace App\Console\Commands;

use App\Services\Ai\AiPreflight;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\AnonymousAgent;
use Throwable;

#[Signature('ai:preflight {--ping : Call each provider once}')]
#[Description('Check this environment is ready for AI features')]
class AiPreflightCheck extends Command
{
    public function handle(AiPreflight $preflight): int
    {
        $results = $preflight->run();

        if ($this->option('ping')) {
            $results = [...$results, ...$this->ping()];
        }

        $this->table(['Check', 'Status', 'Detail'], array_map(
            fn (array $r) => [$r['check'], strtoupper($r['status']),
                $r['detail']],
            $results,
        ));

        // A deploy pipeline stops on a failure, not a warning.
        return collect($results)->contains('status', 'fail')
            ? self::FAILURE : self::SUCCESS;
    }

    /**
     * One tiny request to each provider in the failover chain.
     *
     * @return list<array{check: string, status: string,
     *     detail: string}>
     */
    private function ping(): array
    {
        $agent = new AnonymousAgent('Reply with the word OK.', [], []);
        $results = [];

        foreach (config('acme.ai.failover') as $provider => $model) {
            $start = hrtime(true);

            try {
                $agent->prompt('Ping.', provider: $provider,
                    model: $model, timeout: 30);
                $ms = intdiv(hrtime(true) - $start, 1_000_000);
                $results[] = ['check' => "ping {$provider}",
                    'status' => 'pass',
                    'detail' => "{$model}, {$ms} ms"];
            } catch (Throwable $e) {
                $results[] = ['check' => "ping {$provider}",
                    'status' => 'fail', 'detail' => class_basename($e)];
            }
        }

        return $results;
    }
}
