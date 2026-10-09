<?php

namespace App\Services\Support;

use App\Ai\Agents\SupportAssistant;
use App\Ai\Tools\EscalateToHuman;
use App\Models\User;
use App\Services\Rag\QuotedAnswerService;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Tools\Request;

/**
 * One turn of the support conversation, with the safeguards a
 * model cannot provide: requests for a person and policy
 * questions are routed in code, and claims of actions are
 * checked against what the tools actually did.
 */
class SupportConversation
{
    private const WANTS_A_PERSON = '/\b(speak|talk|chat)\s+(to|with)\s+'
        .'(a\s+)?(person|human|someone|agent|manager)\b'
        .'|\b(real|human)\s+(person|agent)\b/i';

    public const UNCONFIRMED = 'I could not complete that just now. '
        .'Would you like me to pass this to a member of staff?';

    public function __construct(
        private IntentRouter $router,
        private QuotedAnswerService $policies,
    ) {}

    /**
     * @return array{conversation_id: ?string, reply: string,
     *     awaiting_approval: bool, escalated: bool}
     */
    public function reply(
        User $customer,
        ?string $conversationId,
        string $message,
    ): array {
        // 1. A clear request for a person never depends on a model.
        if (preg_match(self::WANTS_A_PERSON, $message)) {
            $result = (new EscalateToHuman($customer))->handle(
                new Request([
                    'reason' => 'Customer asked for a person',
                    'summary' => Str::limit($message, 2000),
                ])
            );

            return [
                'conversation_id' => $conversationId,
                'reply' => "Of course. {$result}",
                'awaiting_approval' => false,
                'escalated' => true,
            ];
        }

        // 2. Policy questions go straight to verified RAG answers.
        if ($this->router->route($message) === 'policy') {
            $answer = $this->policies->ask($message);

            return [
                'conversation_id' => $conversationId,
                'reply' => $answer->answered
                    ? $answer->text.' (Source: '.implode(', ',
                        array_unique(array_column(
                            $answer->sources, 'title'))).')'
                    : 'I could not find that in our policies. '
                        .'Would you like me to pass this to a '
                        .'member of staff?',
                'awaiting_approval' => false,
                'escalated' => false,
            ];
        }

        $response = SupportAssistant::make(customer: $customer)
            ->continueOrStart($conversationId, as: $customer)
            ->prompt($message);

        $waiting = $response->hasPendingApprovals();

        return [
            'conversation_id' => $response->conversationId,
            'reply' => $waiting && $response->text === ''
                ? 'Your request is waiting for a member of staff.'
                : $this->checkedText($response),
            'awaiting_approval' => $waiting,
            'escalated' => $this->ran($response, 'escalate_to_human'),
        ];
    }

    /**
     * 3. Replace replies that claim an action no tool performed.
     */
    private function checkedText(AgentResponse $response): string
    {
        $text = $response->text;

        $claimsEscalation = preg_match(
            '/\b(escalated|ticket|passed (this|it) to)\b/i', $text);
        $claimsRefund = preg_match('/\brefunded\b/i', $text);

        if (($claimsEscalation && ! $this->ran($response,
                'escalate_to_human'))
            || ($claimsRefund && ! $this->refunded($response))) {
            return self::UNCONFIRMED;
        }

        return $text;
    }

    private function ran(AgentResponse $response, string $tool): bool
    {
        return $response->toolCalls->contains('name', $tool);
    }

    private function refunded(AgentResponse $response): bool
    {
        return $response->toolResults->contains(
            fn (ToolResult $result) => str_starts_with(
                $result->text(), 'Refunded'),
        );
    }
}
