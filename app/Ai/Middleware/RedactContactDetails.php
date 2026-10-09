<?php

namespace App\Ai\Middleware;

use Closure;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\PendingStep;

class RedactContactDetails
{
    /** Contact details the assistant may give out. */
    private const ALLOWED = ['support@acme.test'];

    /**
     * Remove e-mail addresses and phone numbers from replies.
     */
    public function handle(PendingStep $step, Closure $next)
    {
        return $next($step)->then(function (StepResponse $response) {
            $response->text = $this->redact($response->text);
        });
    }

    private function redact(string $text): string
    {
        $text = preg_replace_callback(
            '/[\w.+-]+@[\w-]+(\.[\w-]+)+/',
            fn (array $match) => in_array($match[0], self::ALLOWED)
                ? $match[0] : '[email removed]',
            $text,
        );

        // Indian mobile numbers, with or without +91 and spaces.
        return preg_replace(
            '/(\+91[\s-]?)?\b[6-9]\d{4}[\s-]?\d{5}\b/',
            '[phone removed]',
            $text,
        );
    }
}
