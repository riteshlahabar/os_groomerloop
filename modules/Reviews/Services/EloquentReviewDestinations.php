<?php

namespace Modules\Reviews\Services;

use Modules\Reviews\Contracts\ReviewDestinations;
use Modules\Reviews\Models\ReviewDestination;

final class EloquentReviewDestinations implements ReviewDestinations
{
    public function primaryUrl(): ?string
    {
        return ReviewDestination::query()
            ->orderBy('position')
            ->orderBy('id')
            ->value('url');
    }
}
