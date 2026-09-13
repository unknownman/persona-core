<?php

namespace Persona\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Model $document,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Document status updated')
            ->greeting('Hello!')
            ->line('The status of your document has been updated.')
            ->line('Document: ' . $this->document->type)
            ->line('Current status: ' . $this->document->status)
            ->line('If you did not expect this change, please contact support.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'document_id' => $this->document->id,
            'type' => $this->document->type,
            'status' => $this->document->status,
        ];
    }
}