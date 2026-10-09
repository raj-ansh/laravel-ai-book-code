<?php

namespace App\Services\Intake;

use App\Ai\Agents\DamagePhotoInspector;
use App\Services\Rag\TextExtractor;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Transcription;

/**
 * Turns an attachment of any kind into text the case
 * extractor can read, using the services of earlier chapters.
 */
class IntakeEvidence
{
    public function __construct(private TextExtractor $text) {}

    public static function kindOf(string $mime): ?string
    {
        $text = in_array(self::textMime($mime),
            TextExtractor::MIMES, true);

        return match (true) {
            str_starts_with($mime, 'image/') => 'photo',
            str_starts_with($mime, 'audio/') => 'voice_note',
            $text => 'document',
            default => null,
        };
    }

    /**
     * Content sniffing calls a forwarded email "message/rfc822"
     * and a CSV "text/csv": for extraction, both are plain text.
     */
    public static function textMime(string $mime): string
    {
        if (in_array($mime, TextExtractor::MIMES, true)) {
            return $mime;
        }

        return str_starts_with($mime, 'text/')
            || $mime === 'message/rfc822' ? 'text/plain' : $mime;
    }

    /**
     * @return array{text: string, damage_visible: ?bool}
     */
    public function read(
        string $path,
        string $kind,
        string $mime,
        string $message = '',
    ): array {
        return match ($kind) {
            'photo' => $this->photo($path, $message),
            'voice_note' => [
                'text' => $this->transcript($path),
                'damage_visible' => null,
            ],
            'document' => [
                'text' => Str::limit($this->text->extract(
                    $path, self::textMime($mime),
                ), 4000),
                'damage_visible' => null,
            ],
        };
    }

    /**
     * Chapter 26: the photo, described.
     *
     * @return array{text: string, damage_visible: ?bool}
     */
    private function photo(string $path, string $message): array
    {
        // The customer's words give the model context; the
        // inspector still reports only what it can see.
        $result = DamagePhotoInspector::make()->prompt(
            'A customer sent this photo with the message: "'
                .Str::limit($message, 300).'" What does the photo '
                .'show?',
            attachments: [Image::fromStorage($path, 'local')],
        );

        return [
            'text' => (string) ($result['description'] ?? ''),
            'damage_visible' => ($result['damage_visible'] ?? null)
                === true,
        ];
    }

    /** Chapter 25: the voice note, transcribed. */
    private function transcript(string $path): string
    {
        return trim(Transcription::fromStorage($path, 'local')
            ->language('en')->timeout(120)->generate()->text);
    }
}
