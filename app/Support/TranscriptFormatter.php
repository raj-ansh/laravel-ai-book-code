<?php

namespace App\Support;

use Laravel\Ai\Responses\Data\TranscriptionSegment;
use Laravel\Ai\Responses\TranscriptionResponse;

class TranscriptFormatter
{
    /**
     * "[1:05] Speaker 2: text", one line per segment.
     */
    public static function lines(TranscriptionResponse $response): string
    {
        return $response->segments->map(
            fn (TranscriptionSegment $segment) => sprintf(
                '[%d:%02d] %s: %s',
                intdiv((int) $segment->startSeconds, 60),
                (int) $segment->startSeconds % 60,
                $segment->speaker,
                trim($segment->text),
            ),
        )->implode("\n");
    }
}
