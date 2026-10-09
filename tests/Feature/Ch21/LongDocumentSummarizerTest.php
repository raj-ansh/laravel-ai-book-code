<?php

use App\Ai\Agents\DigestWriter;
use App\Ai\Agents\SectionSummarizer;
use App\Services\Text\LongDocumentSummarizer;
use App\Services\Text\TextChunker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Agents\SummarizeAgent;
use Laravel\Ai\Prompts\AgentPrompt;

it('summarises a short text in one line of code', function () {
    SummarizeAgent::fake(['Tickets rose 15 percent.']);

    $summary = Str::summarize('A long report...', sentences: 1);

    expect($summary)->toBe('Tickets rose 15 percent.');
    SummarizeAgent::assertPrompted('A long report...');
});

it('takes notes on each chunk, then summarises them', function () {
    SectionSummarizer::fake(['- note one', '- note two']);
    DigestWriter::fake(['The quarter in brief.']);

    $summary = (new LongDocumentSummarizer(new TextChunker(12)))
        ->summarize("First part.\n\nSecond part.");

    expect($summary->text)->toBe('The quarter in brief.')
        ->and($summary->sections)->toBe(2)
        ->and($summary->truncated)->toBeFalse();
    SectionSummarizer::assertPrompted(fn (AgentPrompt $prompt) =>
        $prompt->contains("Part 2 of 2:\n\nSecond part."));
    DigestWriter::assertPrompted("- note one\n\n- note two");
});

it('skips the notes for a short document', function () {
    SectionSummarizer::fake();
    DigestWriter::fake(['Short.']);

    (new LongDocumentSummarizer(new TextChunker))
        ->summarize('A short note.');

    SectionSummarizer::assertNeverPrompted();
    DigestWriter::assertPrompted('A short note.');
});

it('reports a summary cut off by the token limit', function () {
    config(['ai.default' => 'ollama']);
    Http::fake(['*/api/chat' => Http::response([
        'model' => 'qwen2.5:1.5b',
        'message' => ['role' => 'assistant', 'content' => 'The q'],
        'done_reason' => 'length',
    ])]);

    $summary = (new LongDocumentSummarizer(new TextChunker))
        ->summarize('A short note.');

    expect($summary->truncated)->toBeTrue();
});

it('flags numbers that are not in the source', function () {
    DigestWriter::fake(['Tickets rose 15% to 18,420; 80% in August.']);

    $summary = (new LongDocumentSummarizer(new TextChunker))
        ->summarize('Tickets rose 15 percent, to 18420.');

    expect($summary->unsupportedNumbers)->toBe(['80'])
        ->and($summary->passesChecks())->toBeFalse();
});
