<?php

namespace App\Domains\Organizations\Notifications;

use App\Domains\Organizations\Models\OrganizationInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    public function __construct(
        private readonly OrganizationInvitation $invitation,
        private readonly string $plainToken = '',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $orgName = $this->invitation->organization->name;
        $token = $this->plainToken ?: $this->invitation->token;
        $url = url("/invitations/{$token}/accept");

        return (new MailMessage)
            ->subject(__('app.invitation_email_subject', ['organization' => $orgName]))
            ->greeting(__('app.invitation_email_greeting'))
            ->line(__('app.invitation_email_line', ['organization' => $orgName]))
            ->action(__('app.invitation_email_action'), $url)
            ->line(__('app.invitation_email_expiry', ['days' => 7]));
    }
}
