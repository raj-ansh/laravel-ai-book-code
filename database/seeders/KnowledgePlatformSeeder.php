<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Services\Rag\DocumentIntake;
use Illuminate\Database\Seeder;

class KnowledgePlatformSeeder extends Seeder
{
    /**
     * The final project's data: the tenants of Chapter 43,
     * and a document only Brightlite's people may read.
     */
    public function run(DocumentIntake $intake): void
    {
        $this->call(TenantSeeder::class);

        $brightlite = Organisation::firstWhere(
            'name', 'Brightlite Retail');

        $intake->accept(
            file_get_contents(resource_path(
                'samples/docs/brightlite-trade-terms.md')),
            'Brightlite trade terms',
            'md',
            audience: 'organisation',
            organisationId: $brightlite->id,
        );
    }
}
