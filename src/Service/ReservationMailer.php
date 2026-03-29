<?php

namespace App\Service;

use App\Entity\Reservation;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class ReservationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire(env: 'MAILER_DSN')]
        private string $mailerDsn,
        #[Autowire(env: 'MAILER_FROM_EMAIL')]
        private string $fromEmail,
        #[Autowire(env: 'MAILER_FROM_NAME')]
        private string $fromName,
    ) {}

    public function isNullTransport(): bool
    {
        return str_starts_with($this->mailerDsn, 'null://');
    }

    public function sendConfirmation(Reservation $reservation): void
    {
        if ($this->isNullTransport()) {
            $this->logger->warning(
                '[ReservationMailer] MAILER_DSN=null://null — email discarded. Set a real DSN.',
                ['to' => $reservation->getEmail()]
            );
        }

        $this->logger->info('[ReservationMailer] Sending confirmation email', [
            'to'    => $reservation->getEmail(),
            'name'  => $reservation->getName(),
            'event' => $reservation->getEvent()->getTitle(),
            'from'  => $this->fromEmail,
            'dsn'   => preg_replace('/:\/\/[^@]+@/', '://***@', $this->mailerDsn), // mask password in logs
        ]);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($reservation->getEmail(), $reservation->getName()))
            ->subject('Reservation Confirmed — ' . $reservation->getEvent()->getTitle())
            ->htmlTemplate('emails/reservation_confirmation.html.twig')
            ->context([
                'reservation' => $reservation,
                'event'       => $reservation->getEvent(),
            ]);

        $this->mailer->send($email);

        $this->logger->info('[ReservationMailer] Email sent successfully', [
            'to'             => $reservation->getEmail(),
            'reservation_id' => $reservation->getId(),
        ]);
    }
}
