<?php

namespace Modules\Notifications\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Notifications\Actions\RetryNotification;
use Modules\Notifications\Http\Resources\NotificationLogResource;
use Modules\Notifications\Models\NotificationLog;
use Symfony\Component\HttpFoundation\Response;

/**
 * Send a failed message again (spec §35: failures are logged and **retryable**).
 *
 * Its own controller because it is a different capability from reading the log — gated by
 * `messages.send` rather than `messages.view` (`D-031`) — and the one action on this screen that
 * reaches a customer.
 */
final class NotificationRetryController
{
    /**
     * Route model binding, resolved after ResolveTenant (`D-014`), so another business's log row is
     * simply not found.
     */
    public function __invoke(NotificationLog $notificationLog, RetryNotification $retry): JsonResponse
    {
        $attempt = $retry->execute($notificationLog);

        if ($attempt === null) {
            // Already sent, skipped for lack of consent, or out of attempts. 422 rather than 500: the
            // request was understood and deliberately refused, which is what a stale screen offering
            // Retry on a row that has since changed deserves.
            return response()->json([
                'message' => 'This message cannot be retried — it either already went out, was skipped because the customer opted out, or has used all of its attempts.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return NotificationLogResource::make($attempt)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
