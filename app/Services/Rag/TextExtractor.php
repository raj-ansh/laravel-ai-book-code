<?php

namespace App\Services\Rag;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Smalot\PdfParser\Parser;

/**
 * Turns a stored file into plain text with paragraphs
 * separated by blank lines.
 */
class TextExtractor
{
    public const MIMES = [
        'application/pdf', 'text/html', 'text/markdown', 'text/plain',
    ];

    public function extract(string $path, string $mime): string
    {
        $content = Storage::disk('local')->get($path);

        $text = match ($mime) {
            'application/pdf' => $this->paragraphs((new Parser)
                ->parseContent($content)->getText()),
            'text/html' => $this->fromHtml($content),
            'text/markdown', 'text/plain' => $content,
            default => throw new InvalidArgumentException(
                "Cannot extract text from [{$mime}]."
            ),
        };

        return $this->normalise($text);
    }

    private function fromHtml(string $html): string
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);

        // Page furniture is not content.
        foreach ($xpath->query('//script|//style|//nav|//footer')
            as $node) {
            $node->parentNode->removeChild($node);
        }

        // One paragraph per block element.
        $blocks = $xpath->query('//h1|//h2|//h3|//p|//li|//td');

        return collect(iterator_to_array($blocks))
            ->map(fn ($node) => trim($node->textContent))
            ->filter()
            ->implode("\n\n");
    }

    /**
     * PDFs break lines wherever the page did. Rejoin them into
     * paragraphs; short lines without punctuation are headings.
     */
    private function paragraphs(string $text): string
    {
        $paragraphs = [];
        $current = '';

        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            $heading = $line !== '' && mb_strlen($line) <= 60
                && ! preg_match('/[.!?:;,]$/', $line);

            if ($line === '' || $heading) {
                $paragraphs[] = $current;
                $paragraphs[] = $line;
                $current = '';

                continue;
            }

            $current = trim("{$current} {$line}");
        }

        $paragraphs[] = $current;

        return implode("\n\n", array_filter($paragraphs));
    }

    private function normalise(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/ *\n */', "\n", $text);

        return trim(preg_replace('/\n{3,}/', "\n\n", $text));
    }
}
