<?php

namespace Tests\Live;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests against a real local model. Not part of the normal
 * suites: run them with `php artisan test tests/Live` and
 * AI_LIVE_TESTS=1. They assert properties, never wording.
 */
abstract class LiveTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! env('AI_LIVE_TESTS')) {
            $this->markTestSkipped('Set AI_LIVE_TESTS=1 to run.');
        }

        // A local model only: live tests must never spend money.
        config([
            'ai.default' => 'ollama',
            'ai.providers.ollama.models.text.default' => 'qwen2.5:1.5b',
            'acme.ai.failover' => ['ollama' => 'qwen2.5:1.5b'],
        ]);
    }
}
