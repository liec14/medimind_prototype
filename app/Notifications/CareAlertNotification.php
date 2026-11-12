<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A short alert about a patient: to a doctor (in-app, linked to the patient),
 * to a trusted contact (email only, no health details), or a notice to the patient.
 */
class CareAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $channels any of 'database', 'mail' */
    public function __construct(
        public string $subject,
        public string $message,
        public ?string $url = null,
        public array $channels = ['database', 'mail'],
    ) {}

    public function via(object $notifiable): array
    {
        // Trusted contacts have no account, so they can only be reached by email.
        return $notifiable instanceof User
            ? $this->channels
            : array_values(array_intersect($this->channels, ['mail']));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting($notifiable instanceof User ? 'Hello '.$notifiable->name.',' : 'Hello,')
            ->line($this->message);

        if ($this->url) {
            $mail->action('Open MediMind', $this->url);
        }

        return $mail->line(config('medimind.disclaimer'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['subject' => $this->subject, 'message' => $this->message, 'url' => $this->url];
    }
}
