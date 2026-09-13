<?php

namespace Persona\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class VerifyContactNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $channels  The notification channels the
     *                                        OTP should be sent over.
     */
    public function __construct(
        public string $code,
        public ?string $url = null,
        public array $channels = ['mail'],
    ) {}

    /**
     * Determine which channels the notification will broadcast on.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /**
     * Determine whether this notification can be rendered for the given
     * channel, e.g. 'mail' ⇒ toMail(), 'vonage' ⇒ toVonage().
     */
    public function supportsChannel(string $channel): bool
    {
        return method_exists($this, 'to' . Str::studly($channel));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Verify your contact')
            ->greeting('Hello!')
            ->line('Your verification code is: ' . $this->code . '.')
            ->line('This code expires shortly and should be treated as confidential.');

        if ($this->url !== null) {
            $message->action('Verify Now', $this->url);
        }

        return $message;
    }

    /**
     * SMS payload for the telephony notification channel (returning a plain
     * string is supported by the Vonage channel out of the box).
     */
    public function toVonage(object $notifiable): string
    {
        return 'Your verification code is: ' . $this->code . '.';
    }
}