<?php

namespace Modules\Reviews\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Reviews\Actions\RecordReview;
use Modules\Reviews\Actions\UpdateReview;
use Modules\Reviews\Http\Requests\ListReviewsRequest;
use Modules\Reviews\Http\Requests\StoreReviewRequest;
use Modules\Reviews\Http\Requests\UpdateReviewRequest;
use Modules\Reviews\Http\Resources\ReviewResource;
use Modules\Reviews\Models\Review;
use Symfony\Component\HttpFoundation\Response;

/**
 * The manual review log (spec §20) — what staff saw on an external platform, never what this
 * product generated or submitted.
 */
final class ReviewController
{
    public function index(ListReviewsRequest $request): AnonymousResourceCollection
    {
        $query = Review::query()->orderByDesc('reviewed_at')->orderByDesc('id');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        return ReviewResource::collection(
            $query->paginate($request->integer('per_page', 25))
        );
    }

    public function store(StoreReviewRequest $request, RecordReview $record): JsonResponse
    {
        $review = $record->execute($request->validated(), $request->user()->getKey());

        return ReviewResource::make($review)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateReviewRequest $request, Review $review, UpdateReview $update): JsonResponse
    {
        $review = $update->execute($review, $request->validated());

        return ReviewResource::make($review)->response();
    }

    public function destroy(Review $review, AuditRecorder $audit): JsonResponse
    {
        $audit->record('review.deleted', $review, ['platform' => $review->platform]);

        $review->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
