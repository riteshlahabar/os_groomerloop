<?php

namespace Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Identity\Models\Invitation;

/**
 * The email that carries an invitation's plaintext token.
 *
 * Queued, because spec §33 keeps outbound mail off the request path. The token is held only in
 * this object and in the recipient's inbox — the database stores its hash.
 */
final class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly Invitation $invitation,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $businessName = $this->invitation->tenant->name;

        // Points at the React SPA (D-006), not at an API route: the invitee needs a screen to set
        // their name and password on, and the SPA posts the token back to the API from there.
        $url = rtrim((string) config('app.frontend_url'), '/')
            .'/accept-invitation?token='.$this->token;

        return (new MailMessage)
            ->subject("You have been invited to join {$businessName} on GroomerLoop")
            ->greeting('You have been invited')
            ->line("{$businessName} has invited you to join their team on GroomerLoop as ".
                $this->invitation->role->label().'.')
            ->action('Accept invitation', $url)
            ->line('This invitation expires in '.Invitation::LIFETIME_DAYS.' days.')
            ->line('If you were not expecting this invitation, you can safely ignore this email.');
    }
}
