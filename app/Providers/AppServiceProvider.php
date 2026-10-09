<?php

namespace App\Providers;

use App\Contracts\Reranker;
use App\Contracts\ReviewClassifier;
use App\Enums\AiFeature;
use App\Models\User;
use App\Services\Ai\AiAccess;
use App\Services\Rag\OverlappingChunker;
use App\Services\Rag\PolicyRetriever;
use App\Services\Rag\ScopedPolicyRetriever;
use App\Services\Reviews\AgentReviewClassifier;
use App\Services\Reviews\ProbabilisticReviewClassifier;
use App\Services\Search\JudgeReranker;
use App\Services\Search\ProviderReranker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Client;
use Laravel\Mcp\Facades\Mcp;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Chapter 22: one interface, two ways to classify.
        $this->app->bind(ReviewClassifier::class, fn () => match (
            config('acme.ai.review_classifier')
        ) {
            'api' => new ProbabilisticReviewClassifier,
            default => new AgentReviewClassifier,
        });

        // Chapter 35: every retrieval sees only what the user may.
        $this->app->bind(PolicyRetriever::class, fn ($app) =>
            new ScopedPolicyRetriever($app['auth']->user()));

        // Chapter 34: the measured chunk size for ingestion.
        $this->app->bind(OverlappingChunker::class, fn () =>
            new OverlappingChunker(config('acme.ai.rag_chunk_chars')));

        // Chapter 30: one interface, two ways to rerank.
        $this->app->bind(Reranker::class, fn () => match (
            config('acme.ai.reranker')
        ) {
            'provider' => new ProviderReranker,
            default => new JudgeReranker,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Chapter 13: queued AI jobs share one budget, so a burst
        // of tickets cannot exceed the provider's rate limit.
        RateLimiter::for('ai', fn () => Limit::perMinute(
            (int) config('acme.ai.jobs_per_minute', 30),
        ));

        // Chapter 44: AI requests per user and per company.
        RateLimiter::for('ai-requests', function (Request $request) {
            $user = $request->user();
            $limits = [Limit::perMinute(10)->by("user:{$user->id}")];

            // A company shares one bucket. Consumers have no
            // company, so no shared "org:null" bucket for them.
            if ($user->organisation_id) {
                $limits[] = Limit::perMinute(60)
                    ->by("org:{$user->organisation_id}");
            }

            return $limits;
        });

        // Chapter 17: who may approve tool calls that move money.
        Gate::define(
            'approve-refunds',
            fn (User $user) => $user->is_staff,
        );

        // Chapter 33: who may add documents to the knowledge base.
        Gate::define(
            'manage-knowledge',
            fn (User $user) => $user->is_staff,
        );

        // Chapter 37: who may draft and publish marketing content.
        Gate::define(
            'manage-content',
            fn (User $user) => $user->is_staff,
        );

        // Chapter 43: AI features by plan. Used in routes as
        // can:use-ai,'account_assistant'.
        // Chapter 44: who may see AI usage and cost.
        Gate::define(
            'view-ai-usage',
            fn (User $user) => $user->is_staff,
        );

        Gate::define('use-ai', fn (User $user, string $feature) =>
            app(AiAccess::class)->allows(
                $user, AiFeature::from($feature),
            ));

        // Chapter 20: a client for Acme's staff MCP server.
        Mcp::registerClient('acme-staff', fn () => Client::web(
            config('services.acme_mcp.url'),
        )->withToken((string) config('services.acme_mcp.token')));
    }
}
