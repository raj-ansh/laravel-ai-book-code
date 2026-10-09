<?php

namespace App\Console\Commands;

use App\Services\Documents\HandbookStoreSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kb:sync')]
#[Description('Mirror the handbook into the provider vector store')]
class SyncHandbookStore extends Command
{
    public function handle(HandbookStoreSync $sync): int
    {
        $result = $sync->sync();

        $this->info("Store {$result['store']->id}: "
            ."{$result['added']} added, {$result['replaced']} "
            ."replaced, {$result['removed']} removed.");

        if (! config('acme.ai.handbook_store.id')) {
            $this->warn('New store. Set ACME_HANDBOOK_STORE_ID='
                .$result['store']->id.' in .env.');
        }

        return self::SUCCESS;
    }
}
