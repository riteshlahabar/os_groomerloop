<?php

namespace Modules\Notifications\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Notifications\Http\Requests\ListNotificationsRequest;
use Modules\Notifications\Http\Resources\NotificationLogResource;
use Modules\Notifications\Services\NotificationLogIndex;

/**
 * The §13 delivery log — what was sent, to whom, and what happened to it.
 *
 * Read-only by design. Nothing updates or deletes a log row: a retry appends a new attempt
 * (`NotificationDispatcher::retry()`), which is what keeps "what did we actually try" answerable.
 */
final class NotificationLogController
{
    public function index(ListNotificationsRequest $request, NotificationLogIndex $index): AnonymousResourceCollection
    {
        return NotificationLogResource::collection($index->paginate($request->filters()))
            // Travels with the first page so the screen's tiles and its table are one request.
            ->additional(['meta' => ['status_counts' => $index->statusCounts()]]);
    }
}
