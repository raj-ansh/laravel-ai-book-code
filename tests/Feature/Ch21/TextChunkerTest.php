<?php

use App\Services\Text\TextChunker;

it('packs whole paragraphs into each chunk', function () {
    $text = "Alpha one.\n\nBeta two.\n\nGamma three.";

    expect((new TextChunker(maxChars: 22))->split($text))->toBe([
        "Alpha one.\n\nBeta two.",
        'Gamma three.',
    ]);
});

it('cuts an over-long paragraph at sentences', function () {
    $text = 'First sentence here. Second sentence here.';

    expect((new TextChunker(maxChars: 25))->split($text))->toBe([
        'First sentence here.',
        'Second sentence here.',
    ]);
});

it('never exceeds the limit on a real document', function () {
    $text = file_get_contents(
        resource_path('samples/q3-support-review.md')
    );

    $chunks = (new TextChunker(1500))->split($text);

    expect($chunks)->toHaveCount(3)
        ->and(max(array_map('mb_strlen', $chunks)))
        ->toBeLessThanOrEqual(1500)
        ->and((new TextChunker)->split($text))->toHaveCount(1);
});
