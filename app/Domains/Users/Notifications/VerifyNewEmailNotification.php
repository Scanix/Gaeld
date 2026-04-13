<?php

namespace App\Domains\Users\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyNewEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::signedRoute('profile.email.verify', ['token' => $this->token]);

        return (new MailMessage)
            ->subject(__('app.verify_new_email_subject', ['app' => config('app.name')]))
            ->line(__('app.verify_new_email_line'))
            ->action(__('app.verify_new_email_action'), $url)
            ->line(__('app.verify_new_email_expire'));
    }
}
