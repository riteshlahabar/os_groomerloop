<?php

namespace Modules\Notifications\Services;

use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Crm\Domain\CommunicationChannel;
use Modules\Notifications\Contracts\MailProvider;
use Modules\Notifications\Contracts\SmsProvider;
use Modules\Notifications\Domain\DeliveryStatus;
use Modules\Notifications\Domain\NotificationType;
use Modules\Notifications\Models\NotificationLog;

/**
 * The one place a message actually goes out (spec §13). Every caller — a Scheduling event
 * listener today, a future Reviews/Retention module tomorrow — goes through this rather than
 * reaching a provider directly, so opt-out (invariant #9) and the delivery log are enforced
 * exactly once, not re-implemented per caller.
 *
 * Tries email and SMS independently: a customer who allows one but not the other still gets
 * whichever they said yes to, rather than an all-or-nothing send. Push is not wired yet — no
 * `PushProvider` exists this phase (see `D-025`).
 */
final class NotificationDispatcher
{
    public function __construct(
        private readonly CustomerDirectory $customers,
        private readonly MailProvider $mail,
        private readonly SmsProvider $sms,
    ) {}

    /**
     * @param  array<string, string>  $context  values the templates below interpolate
     */
    public function send(int $customerId, NotificationType $type, array $context, ?int $appointmentId = null): void
    {
        $this->attempt($customerId, $type, CommunicationChannel::Email, $context, $appointmentId);
        $this->attempt($customerId, $type, CommunicationChannel::Sms, $context, $appointmentId);
    }

    /**
     * @param  array<string, string>  $context
     */
    private function attempt(
        int $customerId,
        NotificationType $type,
        CommunicationChannel $channel,
        array $context,
        ?int $appointmentId,
    ): void {
        $mayContact = $type->isTransactional()
            ? $this->customers->mayContact($customerId, $channel)
            : $this->customers->mayMarketTo($customerId, $channel);

        if (! $mayContact) {
            $this->log($customerId, $type, $channel, DeliveryStatus::SkippedNoConsent, null, $appointmentId);

            return;
        }

        $contact = $this->customers->contactDetailsOf($customerId);
        $address = $channel === CommunicationChannel::Email ? $contact?->email : $contact?->phone;

        if ($contact === null || $address === null) {
            // Consent was given, but there is nothing to send to (no email/phone on file) —
            // distinct from a refusal, but still nothing went out.
            $this->log($customerId, $type, $channel, DeliveryStatus::Failed, null, $appointmentId);

            return;
        }

        [$subject, $body] = $this->render($type, $channel, $context, $contact->fullName);

        $sent = $channel === CommunicationChannel::Email
            ? $this->mail->send($contact->email, $contact->fullName, $subject, $body)
            : $this->sms->send($contact->phone, $body);

        $this->log(
            $customerId,
            $type,
            $channel,
            $sent ? DeliveryStatus::Sent : DeliveryStatus::Failed,
            $address,
            $appointmentId,
            $subject,
        );
    }

    /**
     * Fixed copy per type rather than a tenant-editable template store — spec §13 asks for
     * templates, but a business-configurable template editor is more than this phase needs to
     * prove the delivery mechanism works end to end. Revisit once a real need for per-business
     * wording shows up.
     *
     * @param  array<string, string>  $context
     * @return array{0: string, 1: string} subject, body
     */
    private function render(NotificationType $type, CommunicationChannel $channel, array $context, string $customerName): array
    {
        $service = $context['service_name'] ?? 'your appointment';
        $when = $context['starts_at'] ?? '';
        $previousWhen = $context['previous_starts_at'] ?? '';

        $subject = match ($type) {
            NotificationType::BookingRequested => "Booking received: {$service}",
            NotificationType::BookingConfirmed => "Booking confirmed: {$service}",
            NotificationType::BookingCancelled => "Booking cancelled: {$service}",
            NotificationType::BookingRescheduled => "Booking rescheduled: {$service}",
            NotificationType::AppointmentReminder => "Reminder: {$service} tomorrow",
            NotificationType::NoShowFollowUp => "We missed you — {$service}",
        };

        $body = match ($type) {
            NotificationType::BookingRequested => "Hi {$customerName}, we've received your request for {$service} on {$when}. We'll confirm shortly.",
            NotificationType::BookingConfirmed => "Hi {$customerName}, your {$service} appointment on {$when} is confirmed.",
            NotificationType::BookingCancelled => "Hi {$customerName}, your {$service} appointment on {$when} has been cancelled.",
            NotificationType::BookingRescheduled => "Hi {$customerName}, your {$service} appointment has moved from {$previousWhen} to {$when}.",
            NotificationType::AppointmentReminder => "Hi {$customerName}, a reminder that {$service} is coming up on {$when}.",
            NotificationType::NoShowFollowUp => "Hi {$customerName}, we missed you for {$service} on {$when}. Let us know if you'd like to rebook.",
        };

        if ($channel === CommunicationChannel::Sms) {
            // Same facts, no subject line — a text message has nowhere to put one.
            $body = strip_tags($body);
        }

        return [$subject, $body];
    }

    private function log(
        int $customerId,
        NotificationType $type,
        CommunicationChannel $channel,
        DeliveryStatus $status,
        ?string $recipient,
        ?int $appointmentId,
        ?string $subject = null,
    ): void {
        NotificationLog::query()->create([
            'customer_id' => $customerId,
            'type' => $type->value,
            'channel' => $channel->value,
            'status' => $status->value,
            'recipient' => $recipient,
            'subject' => $subject,
            'appointment_id' => $appointmentId,
        ]);
    }
}
