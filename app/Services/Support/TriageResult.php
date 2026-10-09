<?php

namespace App\Services\Support;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class TriageResult
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public TicketCategory $category,
        public TicketPriority $priority,
        public bool $requiresHuman,
        public array $tags,
        public string $summary,
        public string $source,
    ) {}

    /**
     * Validate a model's structured output before using it.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function fromAi(array $data): self
    {
        $valid = Validator::validate($data, [
            'category' => [
                'required', Rule::enum(TicketCategory::class),
            ],
            'priority' => [
                'required', Rule::enum(TicketPriority::class),
            ],
            'requires_human' => ['required', 'boolean'],
            'tags' => ['present', 'array', 'max:3'],
            'tags.*' => ['string', 'max:30'],
            'summary' => ['required', 'string', 'max:200'],
        ]);

        return new self(
            category: TicketCategory::from($valid['category']),
            priority: TicketPriority::from($valid['priority']),
            requiresHuman: (bool) $valid['requires_human'],
            tags: array_values($valid['tags']),
            summary: $valid['summary'],
            source: 'ai',
        );
    }

    /**
     * Business rules the model is not allowed to override.
     */
    public function withRules(): self
    {
        $mustEscalate = $this->priority === TicketPriority::Urgent
            || in_array($this->category, [
                TicketCategory::Billing,
                TicketCategory::Security,
            ], true);

        if (! $mustEscalate || $this->requiresHuman) {
            return $this;
        }

        return new self(
            $this->category, $this->priority, true,
            $this->tags, $this->summary, $this->source,
        );
    }

    /**
     * The safe answer when the model's output is unusable.
     */
    public static function fallback(): self
    {
        return new self(
            category: TicketCategory::Other,
            priority: TicketPriority::Normal,
            requiresHuman: true,
            tags: [],
            summary: 'Needs manual triage.',
            source: 'fallback',
        );
    }
}
