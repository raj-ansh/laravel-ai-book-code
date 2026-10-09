<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Chapter 12: each user may listen only to their own channel.
Broadcast::channel(
    'support.{userId}',
    fn (User $user, int $userId) => $user->id === $userId,
);
