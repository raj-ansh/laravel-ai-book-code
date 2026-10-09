<?php

namespace App\Services\Ai;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Is this environment ready to run the AI features? Each
 * check returns pass, warn or fail with a reason a person
 * can act on. Run before every deploy.
 */
class AiPreflight
{
    /**
     * @return list<array{check: string, status: string,
     *     detail: string}>
     */
    public function run(): array
    {
        return array_map(
            fn (string $check) => ['check' => $check]
                + $this->{Str::camel($check)}(),
            [
                'debug_off', 'queue_async', 'shared_cache',
                'provider_keys', 'failover_chain', 'pricing',
                'token_budgets', 'canary_rotated', 'migrations',
                'vector_extension', 'trace_pruning',
            ],
        );
    }

    /** @return array{status: string, detail: string} */
    private function debugOff(): array
    {
        return config('app.debug')
            ? $this->fail('APP_DEBUG is on: errors would show '
                .'prompts and provider responses.')
            : $this->pass('APP_DEBUG is off.');
    }

    /** @return array{status: string, detail: string} */
    private function queueAsync(): array
    {
        $queue = config('queue.default');

        return $queue === 'sync'
            ? $this->fail('QUEUE_CONNECTION is sync: AI jobs '
                .'would run inside web requests.')
            : $this->pass("Queue: {$queue}.");
    }

    /** @return array{status: string, detail: string} */
    private function sharedCache(): array
    {
        $store = config('cache.default');

        // Rate limits, circuits and cached answers must be
        // shared by every server.
        return in_array($store, ['array', 'file'], true)
            ? $this->fail("Cache store {$store} is not shared "
                .'between servers.')
            : $this->pass("Cache store: {$store}.");
    }

    /** @return array{status: string, detail: string} */
    private function providerKeys(): array
    {
        $missing = collect($this->providers())
            ->reject(fn (string $p) => $p === 'ollama'
                || str_starts_with($p, 'ollama-')
                || filled(config("ai.providers.{$p}.key")))
            ->all();

        return $missing
            ? $this->fail('No API key for: '
                .implode(', ', $missing).'.')
            : $this->pass('Keys present for: '
                .implode(', ', $this->providers()).'.');
    }

    /** @return array{status: string, detail: string} */
    private function failoverChain(): array
    {
        $chain = array_keys(config('acme.ai.failover', []));

        return count($chain) < 2
            ? $this->warn('One provider in acme.ai.failover: an '
                .'outage stops the assistant.')
            : $this->pass('Failover: '.implode(' -> ', $chain).'.');
    }

    /** @return array{status: string, detail: string} */
    private function pricing(): array
    {
        $prices = config('acme.ai.pricing');
        $unpriced = collect($this->providers())
            ->reject(fn (string $p) => isset($prices[$p])
                || str_starts_with($p, 'ollama'))
            ->all();

        return $unpriced
            ? $this->warn('No prices for: '.implode(', ', $unpriced)
                .'. Usage will be recorded as free.')
            : $this->pass('Every provider has a price.');
    }

    /** @return array{status: string, detail: string} */
    private function tokenBudgets(): array
    {
        $budgets = config('acme.ai.token_budgets');

        return ($budgets['consumer_daily'] ?? 0) <= 0
            ? $this->fail('Consumers have no daily allowance.')
            : $this->pass('Consumer allowance: '
                .number_format($budgets['consumer_daily'])
                .' tokens a day.');
    }

    /** @return array{status: string, detail: string} */
    private function canaryRotated(): array
    {
        // The default is printed in a book: everyone knows it.
        return config('acme.ai.security.canary') === 'ACME-7F3A9C'
            ? $this->fail('ACME_AI_CANARY is the published default.')
            : $this->pass('Canary set.');
    }

    /** @return array{status: string, detail: string} */
    private function migrations(): array
    {
        $migrator = app('migrator');

        if (! $migrator->repositoryExists()) {
            return $this->fail('The migrations table does not exist.');
        }

        $files = $migrator->getMigrationFiles(array_merge(
            [database_path('migrations')], $migrator->paths()));
        $pending = array_diff(array_keys($files),
            $migrator->getRepository()->getRan());

        return $pending
            ? $this->fail(count($pending).' pending migration(s).')
            : $this->pass('Migrations up to date.');
    }

    /** @return array{status: string, detail: string} */
    private function vectorExtension(): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return $this->warn('Not PostgreSQL: vector search '
                .'(Chapters 28-35) is unavailable.');
        }

        return DB::table('pg_extension')
            ->where('extname', 'vector')->exists()
            ? $this->pass('pgvector installed.')
            : $this->fail('pgvector extension missing.');
    }

    /** @return array{status: string, detail: string} */
    private function tracePruning(): array
    {
        $scheduled = collect(app(Schedule::class)->events())
            ->contains(fn ($event) => str_contains(
                (string) $event->command, 'model:prune'));

        return $scheduled
            ? $this->pass('model:prune is scheduled.')
            : $this->warn('model:prune is not scheduled: traces '
                .'grow without limit.');
    }

    /**
     * Every provider the configuration will call.
     *
     * @return list<string>
     */
    private function providers(): array
    {
        return array_values(array_unique(array_filter([
            config('ai.default'),
            config('ai.default_for_embeddings'),
            ...array_keys(config('acme.ai.failover', [])),
        ])));
    }

    /** @return array{status: string, detail: string} */
    private function pass(string $detail): array
    {
        return ['status' => 'pass', 'detail' => $detail];
    }

    /** @return array{status: string, detail: string} */
    private function warn(string $detail): array
    {
        return ['status' => 'warn', 'detail' => $detail];
    }

    /** @return array{status: string, detail: string} */
    private function fail(string $detail): array
    {
        return ['status' => 'fail', 'detail' => $detail];
    }
}
