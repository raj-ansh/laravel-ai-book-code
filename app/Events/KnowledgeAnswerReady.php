<?php

namespace App\Events;

use App\Models\KnowledgeAnswer;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A checked answer, sent to the asker's private channel
 * (Chapter 12) the moment the job has stored it.
 */
class KnowledgeAnswerReady implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public KnowledgeAnswer $answer,
        public string $requestId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("support.{$this->answer->user_id}");
    }

    public function broadcastAs(): string
    {
        return 'knowledge.answer';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['request_id' => $this->requestId]
            + $this->answer->only([
                'id', 'reply', 'citations', 'route',
                'conversation_id',
            ]);
    }
}
