<?php

namespace App\Domains\Expenses\Notifications;

use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OcrScanCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $filename,
        public readonly bool $success,
    ) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable instanceof User && $notifiable->wantsEmailNotification('ocr_email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->success ? 'ocr_completed' : 'ocr_failed',
            'filename' => $this->filename,
            'url' => route('expenses.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->success
            ? __('app.notification_ocr_completed', ['filename' => $this->filename])
            : __('app.notification_ocr_failed', ['filename' => $this->filename]);

        return (new MailMessage)
            ->subject($subject)
            ->line($subject)
            ->action(__('app.expenses'), route('expenses.index'));
    }
}
