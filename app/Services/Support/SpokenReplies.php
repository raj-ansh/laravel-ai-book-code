<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;

/**
 * Support answers as speech, generated once per text.
 */
class SpokenReplies
{
    private const VOICE = 'Warm, calm and unhurried, like a '
        .'helpful shop assistant.';

    public function urlFor(string $text): string
    {
        $path = 'spoken/'.sha1(self::VOICE."\n".$text).'.mp3';
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            Audio::of($text)
                ->female()
                ->instructions(self::VOICE)
                ->generate()
                ->storePubliclyAs(
                    dirname($path), basename($path), 'public',
                );
        }

        return $disk->url($path);
    }
}
