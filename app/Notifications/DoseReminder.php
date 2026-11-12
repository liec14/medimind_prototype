<?php

namespace App\Notifications;

use App\Models\DoseLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class DoseReminder extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public DoseLog $dose) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** Skip the send if the patient already confirmed the dose while this waited in the queue. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return (bool) $this->dose->fresh()?->isPending();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $medicine = $this->dose->medicine;

        return (new MailMessage)
            ->subject('Time to take '.$medicine->name)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('It is time to take '.$medicine->name.' ('.$medicine->dosage.') scheduled for '.$this->dose->scheduled_at->format('g:i A').'.')
            ->action('Confirm in MediMind', route('patient.dashboard'))
            ->line(config('medimind.disclaimer'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $medicine = $this->dose->medicine;

        return [
            'dose_id' => $this->dose->id,
            'medicine' => $medicine->name,
            'dosage' => $medicine->dosage,
            'scheduled_at' => $this->dose->scheduled_at->toIso8601String(),
            'message' => 'Time to take '.$medicine->name.' ('.$medicine->dosage.').',
        ];
    }
}
