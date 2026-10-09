<?php

namespace App\Listeners;

use App\Models\AiSpan;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\StepFailed;
use Laravel\Ai\Events\ToolFailed;
use Laravel\Ai\Events\ToolInvoked;
use Throwable;

/**
 * Turns the SDK's events into spans: one per agent run,
 * model step and tool call, linked into one trace.
 * Every handle* method is discovered by its event type.
 */
class RecordAiTrace
{
    /** @var array<string, array{trace: string, start: int}> */
    private static array $open = [];

    public function handlePromptingAgent(PromptingAgent $event): void
    {
        $parent = $event->prompt->parentToolInvocationId
            ?? $event->prompt->parentInvocationId;
        $trace = self::$open[$parent ?? '']['trace']
            ?? $event->invocationId;

        self::$open[$event->invocationId] = [
            'trace' => $trace, 'start' => hrtime(true),
        ];

        // Every log line written during this run carries it.
        Context::addIf('ai_trace_id', $trace);
    }

    public function handleAgentPrompted(AgentPrompted $event): void
    {
        $this->closeAgent($event->invocationId, $event->prompt);
    }

    public function handleAgentFailed(AgentFailed $event): void
    {
        $this->closeAgent($event->invocationId, $event->prompt,
            $event->exception);
    }

    public function handleStepCompleted(StepCompleted $event): void
    {
        $usage = $event->response->usage;

        $this->span($event->invocationId,
            "{$event->invocationId}:{$event->stepNumber}",
            'step', $this->stepName($event),
            $event->time, null,
            $usage->inputTokens, $usage->outputTokens);
    }

    public function handleStepFailed(StepFailed $event): void
    {
        $this->span($event->invocationId,
            "{$event->invocationId}:{$event->stepNumber}",
            'step', $this->stepName($event),
            $event->time, $event->exception);
    }

    public function handleInvokingTool(InvokingTool $event): void
    {
        // Agents called by this tool belong to the same trace.
        self::$open[$event->toolInvocationId] = [
            'trace' => self::$open[$event->invocationId]['trace']
                ?? $event->invocationId,
            'start' => hrtime(true),
        ];
    }

    public function handleToolInvoked(ToolInvoked $event): void
    {
        $this->closeTool($event->invocationId,
            $event->toolInvocationId, $event->tool, $event->time);
    }

    public function handleToolFailed(ToolFailed $event): void
    {
        $this->closeTool($event->invocationId,
            $event->toolInvocationId, $event->tool, $event->time,
            $event->exception);
    }

    /** "step 0: ollama-primary/qwen2.5:1.5b" */
    private function stepName(StepCompleted|StepFailed $event): string
    {
        return "step {$event->stepNumber}: "
            .$event->provider->name().'/'.$event->model;
    }

    private function closeAgent(
        string $id,
        object $prompt,
        ?Throwable $error = null,
    ): void {
        $started = self::$open[$id]['start'] ?? hrtime(true);
        $parent = $prompt->parentToolInvocationId
            ?? $prompt->parentInvocationId;

        $this->span($parent, $id, 'agent',
            class_basename($prompt->agent),
            (hrtime(true) - $started) / 1e6, $error);

        if ($parent === null) {
            Context::forget('ai_trace_id');
        }

        unset(self::$open[$id]);
    }

    private function closeTool(
        string $invocation,
        string $id,
        Tool $tool,
        float $ms,
        ?Throwable $error = null,
    ): void {
        $name = method_exists($tool, 'name')
            ? $tool->name() : class_basename($tool);

        $this->span($invocation, $id, 'tool', $name, $ms, $error);
        unset(self::$open[$id]);
    }

    private function span(
        ?string $parent,
        string $id,
        string $kind,
        string $name,
        float $ms,
        ?Throwable $error = null,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
    ): void {
        $owner = explode(':', $id)[0];

        AiSpan::create([
            'trace_id' => self::$open[$owner]['trace']
                ?? self::$open[$parent ?? '']['trace'] ?? $owner,
            'span_id' => $id,
            'parent_id' => $parent,
            'kind' => $kind,
            'name' => $name,
            'status' => $error ? 'error' : 'ok',
            'duration_ms' => round($ms, 1),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'error' => $error ? Str::limit(
                class_basename($error).': '.$error->getMessage(), 490,
            ) : null,
        ]);
    }
}
