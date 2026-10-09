<?php

use App\Ai\Agents\DocumentAnswerer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => Sanctum::actingAs(User::factory()->create()));

it('sends a text file inline, inside tags', function () {
    DocumentAnswerer::fake(['Within 30 days.']);

    $file = UploadedFile::fake()->createWithContent(
        'returns.txt', 'Returns are accepted within 30 days.',
    );

    $this->post('/api/documents/ask', [
        'file' => $file,
        'question' => 'How long do I have to return an item?',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJson(['answer' => 'Within 30 days.']);

    DocumentAnswerer::assertPrompted(
        fn (AgentPrompt $prompt) => $prompt->contains(
            "<document>\nReturns are accepted within 30 days."
        ) && $prompt->attachments->isEmpty()
    );
});

it('attaches images and PDFs instead', function (
    UploadedFile $file, string $class, string $mime,
) {
    DocumentAnswerer::fake(['An answer.']);

    $this->post('/api/documents/ask', [
        'file' => $file,
        'question' => 'What is this?',
    ], ['Accept' => 'application/json'])->assertOk();

    DocumentAnswerer::assertPrompted(function (
        AgentPrompt $prompt,
    ) use ($class, $mime) {
        $attachment = $prompt->attachments->sole();

        return $attachment instanceof $class
            && $attachment->mimeType() === $mime;
    });
})->with([
    'image' => fn () => [
        UploadedFile::fake()->image('lamp.png'),
        Base64Image::class,
        'image/png',
    ],
    'pdf' => fn () => [
        UploadedFile::fake()->createWithContent(
            'policy.pdf', "%PDF-1.4\n%fake pdf for a test\n",
        ),
        Base64Document::class,
        'application/pdf',
    ],
]);

it('rejects file types it does not handle', function () {
    DocumentAnswerer::fake();

    $this->post('/api/documents/ask', [
        'file' => UploadedFile::fake()->create('setup.exe', 10),
        'question' => 'What is this?',
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    DocumentAnswerer::assertNeverPrompted();
});
