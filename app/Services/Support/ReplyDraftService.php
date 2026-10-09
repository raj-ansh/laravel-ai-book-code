<?php

namespace App\Services\Support;

use App\Ai\Agents\ReplyDrafter;

class ReplyDraftService
{
    public function draft(
        string $message,
        ?string $customerName = null,
    ): ReplyDraft {
        $response = (new ReplyDrafter)->prompt(
            $this->prompt($message, $customerName),
        );

        return new ReplyDraft(
            text: trim($response->text),
            provider: $response->meta->provider,
            model: $response->meta->model,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
        );
    }

    private function prompt(
        string $message,
        ?string $customerName,
    ): string {
        $name = $customerName ?? 'unknown';

        return <<<TEXT
            Customer name: {$name}

            Customer message:
            <message>
            {$message}
            </message>
            TEXT;
    }
}
