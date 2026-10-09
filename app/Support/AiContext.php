<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Context;

/**
 * Who the AI work in this request, command or job is for.
 * Context is copied into every job dispatched from here.
 */
final class AiContext
{
    public static function forUser(User $user): void
    {
        Context::add([
            'ai_user_id' => $user->id,
            'ai_organisation_id' => $user->organisation_id,
        ]);
    }
}
