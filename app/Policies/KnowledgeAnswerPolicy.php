<?php

namespace App\Policies;

use App\Models\KnowledgeAnswer;
use App\Models\User;

class KnowledgeAnswerPolicy
{
    /**
     * The person who asked, and staff reviewing quality.
     */
    public function view(User $user, KnowledgeAnswer $answer): bool
    {
        return $user->id === $answer->user_id || $user->is_staff;
    }

    /**
     * Only the person who asked can say whether it helped.
     */
    public function rate(User $user, KnowledgeAnswer $answer): bool
    {
        return $user->id === $answer->user_id;
    }
}
