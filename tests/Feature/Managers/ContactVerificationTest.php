<?php

namespace Persona\Tests\Feature\Managers;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Persona\Persona;
use Persona\Tests\TestCase;

class ContactVerificationTest extends TestCase
{
    public function test_otp_is_locked_after_max_attempts_even_with_valid_code(): void
    {
        NotificationFacade::fake();

        $user = $this->createUser();
        $contact = Persona::for($user)->addContact('email', 'throttle@example.com');

        $otp = Persona::for($user)->sendContactVerification($contact);

        $maxAttempts = (int) config('persona.otp.max_attempts', 5);

        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->assertFalse(Persona::for($user)->verifyContact($contact, '000000'));
        }

        $this->assertFalse(Persona::for($user)->verifyContact($contact, $otp));
        $this->assertFalse($contact->fresh()->is_verified);
    }

    public function test_sending_a_fresh_otp_grants_a_new_attempt_window(): void
    {
        NotificationFacade::fake();

        $user = $this->createUser();
        $contact = Persona::for($user)->addContact('email', 'refresh@example.com');

        $otp = Persona::for($user)->sendContactVerification($contact);

        $maxAttempts = (int) config('persona.otp.max_attempts', 5);

        for ($i = 0; $i < $maxAttempts; $i++) {
            Persona::for($user)->verifyContact($contact, '000000');
        }

        $freshOtp = Persona::for($user)->sendContactVerification($contact);

        $this->assertTrue(Persona::for($user)->verifyContact($contact, $freshOtp));
        $this->assertTrue($contact->fresh()->is_verified);
    }

    public function test_custom_verification_notification_is_used_without_supports_channel(): void
    {
        NotificationFacade::fake();

        $user = $this->createUser();
        $contact = Persona::for($user)->addContact('email', 'custom@example.com');

        Persona::verifyContactsUsing(
            fn ($contact, $otp) => new CustomOtpNotification($otp)
        );

        try {
            $otp = Persona::for($user)->sendContactVerification($contact);

            $this->assertSame(6, strlen($otp));
            NotificationFacade::assertSentOnDemand(CustomOtpNotification::class);
        } finally {
            Persona::$verifyContactNotificationCallback = null;
        }
    }

    public function test_custom_verification_notification_survives_sms_route(): void
    {
        config(['persona.otp.sms_channel' => 'mail']);

        NotificationFacade::fake();

        $user = $this->createUser();
        $contact = Persona::for($user)->addContact('phone', '+1 555 0100 2000');

        Persona::verifyContactsUsing(
            fn ($contact, $otp) => new CustomOtpNotification($otp)
        );

        try {
            $otp = Persona::for($user)->sendContactVerification($contact);

            $this->assertSame(6, strlen($otp));
            NotificationFacade::assertSentOnDemand(CustomOtpNotification::class);
        } finally {
            Persona::$verifyContactNotificationCallback = null;
        }
    }
}

class CustomOtpNotification extends Notification
{
    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}