<?php

use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;

it('refuses to fetch private or local addresses', function (
    string $url,
) {
    expect(fn () => Image::fromUrl($url)->content())
        ->toThrow(InvalidArgumentException::class);
})->with([
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'loopback' => 'http://127.0.0.1:8000/admin',
    'localhost' => 'http://localhost/secret.png',
    'private network' => 'http://10.0.0.5/report.pdf',
    'not http' => 'file:///etc/passwd',
]);

it('says why a URL was blocked', function () {
    Document::fromUrl('http://169.254.169.254/latest')->content();
})->throws(InvalidArgumentException::class, 'blocked address');
