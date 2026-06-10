<?php

namespace App\Notifications;

use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Upload $upload,
        public bool $hasErrors = false,
        public ?string $message = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->hasErrors
            ? "Import #{$this->upload->id} completed with errors"
            : "Import #{$this->upload->id} completed successfully";

        $mail = (new MailMessage)
            ->subject($subject)
            ->line("File: {$this->upload->original_filename}")
            ->line("Total rows: {$this->upload->total_rows}")
            ->line("Successful: {$this->upload->successful_rows}")
            ->line("Failed: {$this->upload->failed_rows}");

        if ($this->message) {
            $mail->line($this->message);
        }

        return $mail->action('View Dashboard', url('/dashboard'));
    }
}
