<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Events\ToolApprovalRequested;

class NotifyStaffOfPendingApproval
{
    public function handle(ToolApprovalRequested $event): void
    {
        // In production: a notification to the support queue.
        Log::info('Tool call awaiting approval', [
            'conversation_id' => $event->conversationId,
            'calls' => $event->pendingApprovals->map(
                fn (PendingApproval $approval) => $approval->tool
                    .' '.json_encode($approval->arguments),
            )->all(),
        ]);
    }
}
