<?php

use App\Ai\Agents\SupportChatAgent;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Jobs\BroadcastAgent;
use Laravel\Sanctum\Sanctum;

it('queues the agent and answers 202 at once', function () {
    Queue::fake();
    SupportChatAgent::fake();

    $asha = User::factory()->create();
    Sanctum::actingAs($asha);

    $this->postJson('/api/support/chat/async', [
        'message' => 'Where is my parcel?',
    ])->assertAccepted()->assertJson(['status' => 'queued']);

    SupportChatAgent::assertQueued('Where is my parcel?');

    Queue::assertPushed(
        BroadcastAgent::class,
        fn (BroadcastAgent $job) => $job->channels->name
            === "private-support.{$asha->id}"
    );
});

it('lets users listen only to their own channel', function () {
    $asha = User::factory()->create();
    $ravi = User::factory()->create();

    $authorize = fn (User $user, int $channelUserId) => (
        Broadcast::driver()->getChannels()['support.{userId}']
    )($user, $channelUserId);

    expect($authorize($asha, $asha->id))->toBeTrue()
        ->and($authorize($ravi, $asha->id))->toBeFalse();
});
