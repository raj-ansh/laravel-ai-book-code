<?php

namespace App\Services\Support;

use App\Models\User;
use Laravel\Ai\Contracts\ConversationStore;

/**
 * The SDK continues any conversation id it is given. Before
 * using an id that came from a request, check its owner.
 */
class ConversationGuard
{
    public function __construct(private ConversationStore $store) {}

    public function ensureOwnedBy(
        ?string $conversationId,
        User $user,
    ): void {
        if ($conversationId === null) {
            return;
        }

        abort_unless($this->store->conversationBelongsTo(
            $conversationId,
            $user->getMorphClass(),
            $user->getKey(),
        ), 404);
    }
}
