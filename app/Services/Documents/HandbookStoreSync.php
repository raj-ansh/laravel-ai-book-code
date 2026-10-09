<?php

namespace App\Services\Documents;

use App\Models\KbDocument;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Store;
use Laravel\Ai\Stores;

/**
 * Mirrors the policy files on the kb disk into a vector
 * store at the provider: add new, replace changed, remove
 * deleted. Unchanged files are not uploaded again.
 */
class HandbookStoreSync
{
    /**
     * @return array{store: Store, added: int, replaced: int,
     *     removed: int}
     */
    public function sync(): array
    {
        $store = $this->store();
        $disk = Storage::disk('kb');
        $paths = $disk->files('policies');
        $stats = ['store' => $store, 'added' => 0,
            'replaced' => 0, 'removed' => 0];

        foreach ($paths as $path) {
            $hash = sha1($disk->get($path));
            $known = KbDocument::firstWhere('path', $path);

            if ($known?->hash === $hash) {
                continue;
            }

            if ($known) {
                $store->remove($known->document_id, deleteFile: true);
            }

            $added = $store->add(Document::fromStorage($path, 'kb'), [
                'topic' => pathinfo($path, PATHINFO_FILENAME),
                'audience' => 'customers',
            ]);

            KbDocument::updateOrCreate(['path' => $path], [
                'hash' => $hash,
                'file_id' => $added->fileId() ?? $added->id(),
                'document_id' => $added->id(),
            ]);

            $stats[$known ? 'replaced' : 'added']++;
        }

        foreach (KbDocument::whereNotIn('path', $paths)->get() as $gone) {
            $store->remove($gone->document_id, deleteFile: true);
            $gone->delete();
            $stats['removed']++;
        }

        return $stats;
    }

    private function store(): Store
    {
        $config = config('acme.ai.handbook_store');

        return $config['id']
            ? Stores::get($config['id'], $config['provider'])
            : Stores::create(
                'Acme handbook',
                'Customer-facing policies: returns, shipping, '
                    .'warranty.',
                provider: $config['provider'],
            );
    }
}
