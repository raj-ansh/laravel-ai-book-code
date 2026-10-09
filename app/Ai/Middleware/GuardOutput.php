<?php

namespace App\Ai\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\PendingStep;

/**
 * Checks every reply before anyone sees it: no leaked
 * system prompt, no images, no links to unknown hosts.
 */
class GuardOutput
{
    public const LEAKED = 'Sorry, I can\'t share that. I can '
        .'help with your Acme orders and account.';

    /** Words in a row shared with the instructions = a leak. */
    private const RUN = 8;

    public function handle(PendingStep $step, Closure $next)
    {
        return $next($step)->then(
            function (StepResponse $response) use ($step) {
                if (self::leaks($response->text,
                    (string) $step->instructions)) {
                    Log::warning('System prompt leaked', [
                        'invocation_id' => $step->invocationId,
                    ]);
                    $response->text = self::LEAKED;

                    return;
                }

                $response->text = $this->links($response->text);
            },
        );
    }

    public static function leaks(
        string $text,
        string $instructions,
    ): bool {
        // The canary, or the provider's tool template, which
        // the model can see even though we never wrote it.
        if (Str::contains($text, [
            config('acme.ai.security.canary'), '<tools>',
        ], ignoreCase: true)) {
            return true;
        }

        $runs = fn (string $s) => collect(
            str_word_count(Str::lower($s), 1)
        )->sliding(self::RUN)->map->implode(' ')->all();

        return array_intersect(
            $runs($text), $runs($instructions),
        ) !== [];
    }

    /**
     * A markdown image is fetched by the browser the moment
     * it is shown: a way to send data out without a click.
     */
    private function links(string $text): string
    {
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/',
            '[image removed]', $text);

        return preg_replace_callback('~https?://[^\s)\]>"\']+~i',
            fn (array $m) => in_array(
                strtolower((string) parse_url($m[0], PHP_URL_HOST)),
                config('acme.ai.security.link_hosts'), true,
            ) ? $m[0] : '[link removed]',
            $text);
    }
}
