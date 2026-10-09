<?php

namespace App\Jobs;

use App\Events\KnowledgeAnswerReady;
use App\Models\User;
use App\Services\Knowledge\KnowledgePlatform;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Broadcast;
use Throwable;

/**
 * Answers in the background, so a slow model never holds a
 * web worker; the result arrives on the user's channel.
 */
class AnswerKnowledgeQuestion implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 240;

    public function __construct(
        public User $user,
        public string $question,
        public ?string $conversationId,
        public string $requestId,
    ) {
        $this->onQueue('ai-interactive');
    }

    public function handle(KnowledgePlatform $platform): void
    {
        $answer = $platform->ask($this->user, $this->question,
            $this->conversationId);

        KnowledgeAnswerReady::dispatch($answer, $this->requestId);
    }

    public function failed(?Throwable $e): void
    {
        // The person is waiting: tell them, without details.
        Broadcast::on(new PrivateChannel("support.{$this->user->id}"))
            ->as('knowledge.failed')
            ->with(['request_id' => $this->requestId])
            ->sendNow();
    }
}
