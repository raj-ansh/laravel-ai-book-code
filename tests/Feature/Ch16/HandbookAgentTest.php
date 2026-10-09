<?php

use App\Ai\Agents\HandbookAgent;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Filesystem\ReadFile;
use Laravel\Ai\Tools\Request;

it('lists and reads handbook files, then answers', function () {
    HandbookAgent::fake([
        new ToolCall('c1', 'ListFiles', ['path' => 'policies']),
        new ToolCall('c2', 'ReadFile', [
            'path' => 'policies/shipping.md',
        ]),
        'No, Acme does not ship outside India.',
    ]);

    $response = (new HandbookAgent)
        ->prompt('Do you ship to Canada?');

    [$list, $file] = $response->toolResults->all();

    expect($list->text())->toContain('policies/shipping.md')
        ->and($file->text())
        ->toContain('We do not ship outside India.');
});

it('offers read-only tools only', function () {
    $names = collect((new HandbookAgent)->tools())
        ->map(fn ($tool) => class_basename($tool))
        ->all();

    expect($names)->toBe([
        'ListFiles', 'ReadFile', 'FileExists',
        'GetFileMetadata', 'GetFileUrl',
    ]);
});

it('cannot read outside its disk', function (string $path) {
    $result = (new ReadFile('kb'))
        ->handle(new Request(['path' => $path]));

    expect($result)->toBe("File [{$path}] does not exist.");
})->with(['../../.env', '/etc/passwd']);
