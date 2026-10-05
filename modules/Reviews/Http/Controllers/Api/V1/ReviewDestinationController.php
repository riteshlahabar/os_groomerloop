<?php

namespace Modules\Reviews\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Reviews\Actions\UpsertReviewDestination;
use Modules\Reviews\Http\Requests\StoreReviewDestinationRequest;
use Modules\Reviews\Http\Resources\ReviewDestinationResource;
use Modules\Reviews\Models\ReviewDestination;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where review requests should point (spec §20).
 */
final class ReviewDestinationController
{
    public function index(): AnonymousResourceCollection
    {
        return ReviewDestinationResource::collection(
            ReviewDestination::query()
                ->orderBy('position')
                ->orderBy('label')
                ->paginate(100)
        );
    }

    /**
     * 200 rather than 201 when the label already existed, so a client asking twice can tell
     * nothing new was made.
     */
    public function store(StoreReviewDestinationRequest $request, UpsertReviewDestination $upsert): JsonResponse
    {
        $destination = $upsert->execute(
            $request->string('label')->toString(),
            $request->string('url')->toString(),
            (int) $request->input('position', 0),
        );

        return ReviewDestinationResource::make($destination)
            ->response()
            ->setStatusCode($destination->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function destroy(ReviewDestination $reviewDestination, AuditRecorder $audit): JsonResponse
    {
        $audit->record('review_destination.deleted', $reviewDestination, ['label' => $reviewDestination->label]);

        $reviewDestination->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
