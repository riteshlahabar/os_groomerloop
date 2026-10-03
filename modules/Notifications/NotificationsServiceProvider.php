<?php

namespace Modules\Notifications;

use App\Support\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\Notifications\Actions\SendTestEmail;
use Modules\Notifications\Console\RetryFailedNotificationsCommand;
use Modules\Notifications\Console\SendAppointmentRemindersCommand;
use Modules\Notifications\Contracts\MailProvider;
use Modules\Notifications\Contracts\SmsProvider;
use Modules\Notifications\Contracts\TenantMailSettings;
use Modules\Notifications\Contracts\TestMailSender;
use Modules\Notifications\Listeners\SendAppointmentBookedNotification;
use Modules\Notifications\Listeners\SendAppointmentRescheduledNotification;
use Modules\Notifications\Listeners\SendAppointmentStatusNotification;
use Modules\Notifications\Services\Providers\LogSmsProvider;
use Modules\Notifications\Services\Providers\SmtpMailProvider;
use Modules\Notifications\Services\TenantMailConfiguration;
use Modules\Scheduling\Events\AppointmentBooked;
use Modules\Scheduling\Events\AppointmentRescheduled;
use Modules\Scheduling\Events\AppointmentStatusChanged;

/**
 * Notifications owns every message that leaves the business (spec §13, §26).
 *
 * It depends on Crm (consent and contact details), Catalog (the service name in the copy), Scheduling
 * (the events it reacts to and the reminder sweep's appointment list) and Tenancy — all through
 * contracts and domain events, never another module's model (`D-007`). Nothing depends on *it*: the
 * modules that cause a message are decoupled by events, which is how Scheduling can book an
 * appointment without knowing this module exists.
 *
 * **Email sends for real; SMS still logs.** `MailProvider` is bound to `SmtpMailProvider` (`D-032`),
 * which sends through the business's own SMTP account when a GroomerLoop admin has configured one,
 * through the platform account otherwise, and falls back to `LogMailProvider` when neither exists —
 * so an unconfigured install stays as honest as it was before. `SmsProvider` is still `LogSmsProvider`
 * for want of a provider (`D-025`). Either swap is one binding and touches no business logic
 * (invariant #5).
 *
 * **Nothing here is queued** (`D-031`): this host has no persistent queue worker (`D-011`), so sends
 * happen inline with the request that caused them and the two scheduled halves — reminders and failure
 * retries — run from cron. See `D-031` for what that costs and why it is still the right trade today.
 */
final class NotificationsServiceProvider extends ModuleServiceProvider
{
    /**
     * The §13 events this module reacts to, and what handles each.
     *
     * Declared here rather than discovered: an event with no listener is a message a customer silently
     * never receives, and that should be visible in one place.
     *
     * @var array<class-string, class-string>
     */
    private const LISTENERS = [
        AppointmentBooked::class => SendAppointmentBookedNotification::class,
        AppointmentRescheduled::class => SendAppointmentRescheduledNotification::class,
        AppointmentStatusChanged::class => SendAppointmentStatusNotification::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(MailProvider::class, SmtpMailProvider::class);
        $this->app->bind(SmsProvider::class, LogSmsProvider::class);

        $this->app->bind(TenantMailSettings::class, TenantMailConfiguration::class);
        $this->app->bind(TestMailSender::class, SendTestEmail::class);
    }

    public function boot(): void
    {
        parent::boot();

        foreach (self::LISTENERS as $event => $listener) {
            Event::listen($event, $listener);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                SendAppointmentRemindersCommand::class,
                RetryFailedNotificationsCommand::class,
            ]);
        }
    }
}
