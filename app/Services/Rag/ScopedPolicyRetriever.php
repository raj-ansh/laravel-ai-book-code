<?php

namespace App\Services\Rag;

use App\Models\KnowledgeChunk;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Retrieval limited to what one user may read. Access is
 * decided here, in the query, never by the model.
 */
class ScopedPolicyRetriever extends PolicyRetriever
{
    public function __construct(private ?User $user) {}

    /**
     * @return Builder<KnowledgeChunk>
     */
    protected function chunks(): Builder
    {
        return parent::chunks()->whereHas('document', function ($doc) {
            $doc->whereNull('flagged_at')->where(function ($q) {
                $q->where('audience', 'public');

                if ($this->user?->is_staff) {
                    $q->orWhere('audience', 'staff');
                }

                if ($this->user?->organisation_id) {
                    $q->orWhere(fn ($org) => $org
                        ->where('audience', 'organisation')
                        ->where('organisation_id',
                            $this->user->organisation_id));
                }
            });
        });
    }
}
