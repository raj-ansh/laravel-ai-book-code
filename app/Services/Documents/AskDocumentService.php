<?php

namespace App\Services\Documents;

use App\Ai\Agents\DocumentAnswerer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\AgentResponse;

class AskDocumentService
{
    /** Plain-text files are sent inline, up to this size. */
    public const MAX_INLINE_CHARS = 20_000;

    private const TEXT_TYPES = [
        'text/plain', 'text/markdown', 'text/csv',
    ];

    public function ask(
        UploadedFile $file,
        string $question,
    ): AgentResponse {
        // Trust the server's view of the content, not the
        // browser's: getMimeType() inspects the bytes.
        $mime = $file->getMimeType();

        if (in_array($mime, self::TEXT_TYPES, true)) {
            return $this->askInline($file, $question);
        }

        $attachment = str_starts_with($mime, 'image/')
            ? Image::fromUpload($file, $mime)
            : Document::fromUpload($file, $mime);

        return (new DocumentAnswerer)->prompt(
            "Question: {$question}",
            attachments: [$attachment],
        );
    }

    /**
     * Any provider can read text: put it in the prompt.
     */
    private function askInline(
        UploadedFile $file,
        string $question,
    ): AgentResponse {
        $text = Str::limit(
            $file->get(), self::MAX_INLINE_CHARS, '[truncated]',
        );

        return (new DocumentAnswerer)->prompt(<<<TEXT
            <document>
            {$text}
            </document>

            Question: {$question}
            TEXT);
    }
}
