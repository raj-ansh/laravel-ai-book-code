<?php

namespace App\Services\Rag;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;

/**
 * Flags documents whose text tries to instruct a model.
 * Flagged documents are excluded from retrieval until a
 * person has looked at them.
 */
class KnowledgeScanner
{
    // \s+ not " ": documents wrap lines anywhere.
    private const PATTERNS = [
        '/ignore\s+(all\s+|any\s+)?(your\s+)?(previous\s+|prior\s+'
            .'|above\s+)?(instructions|rules)/i',
        '/note\s+(for|to)\s+the\s+(assistant|ai|model)/i',
        '/(system|developer)\s+(prompt|message)/i',
        '/(tell|say\s+to)\s+(the\s+)?(customer|user)s?\s+(that|to)/i',
    ];

    /**
     * @return int How many documents were flagged.
     */
    public function scan(): int
    {
        $flagged = 0;

        KnowledgeDocument::whereNull('flagged_at')->each(
            function (KnowledgeDocument $document) use (&$flagged) {
                $reason = $this->reason($document);

                if ($reason !== null) {
                    $document->update([
                        'flagged_at' => now(),
                        'flag_reason' => $reason,
                    ]);
                    $flagged++;
                }
            },
        );

        return $flagged;
    }

    private function reason(KnowledgeDocument $document): ?string
    {
        foreach ($document->chunks()->pluck('content') as $content) {
            foreach (self::PATTERNS as $pattern) {
                if (preg_match($pattern, $content, $match)) {
                    return "Contains \"{$match[0]}\"";
                }
            }
        }

        return null;
    }
}
