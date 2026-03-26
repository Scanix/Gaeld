<?php

namespace App\Domains\Users\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorDisabledNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.two_factor_disabled_subject', ['app' => config('app.name')]))
            ->line(__('app.two_factor_disabled_notification'))
            ->line(__('app.two_factor_disabled_warning'));
    }
}
