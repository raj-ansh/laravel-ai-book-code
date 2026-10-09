<?php

use App\Http\Controllers\Business\AccountAssistantController;
use App\Http\Controllers\Documents\DocumentQuestionController;
use App\Http\Controllers\Knowledge\KnowledgeController;
use App\Http\Controllers\Staff\AiUsageController;
use App\Http\Controllers\Staff\BusinessDocumentController;
use App\Http\Controllers\Staff\ContentDraftController;
use App\Http\Controllers\Staff\KnowledgeDocumentController;
use App\Http\Controllers\Staff\RefundApprovalController;
use App\Http\Controllers\Support\DamageReportController;
use App\Http\Controllers\Support\IntakeController;
use App\Http\Controllers\Support\ReplyDraftController;
use App\Http\Controllers\Support\SupportAssistantController;
use App\Http\Controllers\Support\SupportChatBroadcastController;
use App\Http\Controllers\Support\SupportChatController;
use App\Http\Controllers\Support\SupportChatStreamController;
use App\Http\Controllers\Support\SupportDeskController;
use App\Http\Controllers\Support\VoiceNoteController;
use App\Http\Middleware\MeterAi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Chapter 5: a first real feature, in proper layers.
Route::post('/support/reply-drafts', ReplyDraftController::class)
    ->middleware('throttle:10,1');

// Chapter 9: a support chat that remembers the conversation.
Route::middleware(['auth:sanctum', 'throttle:20,1'])
    ->controller(SupportChatController::class)
    ->group(function () {
        Route::post('/support/chat', 'store');
        Route::get('/support/chat/{conversation}', 'show');
    });

// Chapter 12: the same chat, streamed or broadcast.
Route::middleware(['auth:sanctum', 'throttle:20,1'])
    ->group(function () {
        Route::post('/support/chat/stream',
            SupportChatStreamController::class);
        Route::post('/support/chat/async',
            SupportChatBroadcastController::class);
    });

// Chapter 11: ask a question about an uploaded document.
Route::post('/documents/ask', DocumentQuestionController::class)
    ->middleware(['auth:sanctum', 'throttle:10,1']);

// Chapter 17: refunds wait for staff approval.
Route::middleware(['auth:sanctum', 'throttle:20,1'])
    ->group(function () {
        Route::post('/support/desk', SupportDeskController::class);

        Route::controller(RefundApprovalController::class)
            ->prefix('staff/approvals')
            ->group(function () {
                Route::get('/{conversation}', 'index');
                Route::post('/{conversation}', 'decide');
            });
    });

// Chapter 39: back-office documents, read and sorted by AI.
Route::middleware(['auth:sanctum', 'throttle:20,1'])
    ->controller(BusinessDocumentController::class)
    ->prefix('staff/documents')
    ->group(function () {
        Route::post('/', 'store');
        Route::get('/review', 'index');
        Route::get('/{document}', 'show');
    });

// Chapter 38: the complete support assistant.
Route::post('/support/assistant', SupportAssistantController::class)
    ->middleware(['auth:sanctum', 'throttle:20,1']);

// Chapter 37: the content studio.
Route::middleware(['auth:sanctum', 'throttle:20,1'])
    ->controller(ContentDraftController::class)
    ->prefix('staff/content')
    ->group(function () {
        Route::post('/products/{product}/drafts', 'store');
        Route::get('/products/{product}/drafts', 'index');
        Route::post('/drafts/{draft}/approve', 'approve');
        Route::post('/drafts/{draft}/reject', 'reject');
    });

// Chapter 33: staff add documents to the knowledge base.
Route::middleware(['auth:sanctum', 'throttle:20,1'])
    ->controller(KnowledgeDocumentController::class)
    ->prefix('staff/knowledge')
    ->group(function () {
        Route::post('/documents', 'store');
        Route::get('/documents/{document}', 'show');
    });

// Chapter 26: a photo of an item that arrived damaged.
Route::post('/orders/{order}/damage-reports',
    DamageReportController::class)
    ->middleware(['auth:sanctum', 'throttle:5,1']);

// Chapter 25: customers can send support a voice note.
Route::post('/support/voice-notes', VoiceNoteController::class)
    ->middleware(['auth:sanctum', 'throttle:5,1']);

// Chapter 41: one door for text, photos, documents and audio.
Route::middleware(['auth:sanctum', 'throttle:10,1'])
    ->controller(IntakeController::class)
    ->prefix('support/intake')
    ->group(function () {
        Route::post('/', 'store');
        Route::get('/{case}', 'show');
    });

// Chapter 43: AI features by plan, per tenant.
// Chapter 44: per-user and per-company limits, metering.
Route::middleware(['auth:sanctum', 'throttle:ai-requests'])
    ->controller(AccountAssistantController::class)
    ->group(function () {
        Route::get('/ai/features', 'features');
        Route::post('/business/assistant', 'ask')->middleware([
            // Quoted: a bare word would be read as a route
            // parameter name, and the gate would get null.
            "can:use-ai,'account_assistant'",
            // After the gate: "not in your plan" is a 403,
            // "allowance used" a 429.
            MeterAi::class,
        ]);
    });

// Chapter 44: AI usage and cost, for staff.
Route::get('/staff/ai-usage', AiUsageController::class)
    ->middleware(['auth:sanctum', 'can:view-ai-usage']);

// Chapters 51-53: the Acme Knowledge Platform.
Route::middleware(['auth:sanctum', 'throttle:ai-requests'])
    ->controller(KnowledgeController::class)
    ->prefix('knowledge')
    ->group(function () {
        Route::middleware([
            "can:use-ai,'knowledge_search'", MeterAi::class,
        ])->group(function () {
            Route::post('/ask', 'ask');
            Route::post('/ask-later', 'askLater');
        });
        Route::get('/answers/{answer}', 'show');
        Route::post('/answers/{answer}/feedback', 'feedback');
    });
