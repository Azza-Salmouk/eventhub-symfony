<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Test\Constraint\EmailCount;

/**
 * Verifies that a confirmation email is sent when a reservation is submitted.
 *
 * Uses Symfony's built-in MailerAssertionsTrait (available via WebTestCase).
 * MAILER_DSN must be set to null://null (default) so emails are captured in memory.
 */
class ReservationEmailTest extends WebTestCase
{
    public function testConfirmationEmailSentOnReservation(): void
    {
        $client = static::createClient();

        // Find an event that has available seats (fixture data: event id 1 has 200 seats)
        $client->request('GET', '/events/1/reserve');
        self::assertResponseIsSuccessful();

        // Submit the reservation form
        $client->submitForm('Confirm Reservation', [
            'reservation[name]'  => 'Test User',
            'reservation[email]' => 'test@example.com',
            'reservation[phone]' => '+1 555 1234',
        ]);

        // Should redirect to event detail page after success
        self::assertResponseRedirects();

        // Assert exactly 1 email was queued/sent
        self::assertEmailCount(1);

        // Inspect the email
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'to', 'test@example.com');
        self::assertEmailSubjectContains($email, 'Reservation Confirmed');
        self::assertEmailHtmlBodyContains($email, 'Test User');
        self::assertEmailHtmlBodyContains($email, 'Reservation Confirmed');
    }
}
