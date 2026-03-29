<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;

/**
 * Logs every email attempt with full SMTP details.
 * Check var/log/mailer.log for the complete trace.
 */
class MailerLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $mailerLogger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class      => 'onMessage',
            SentMessageEvent::class  => 'onSent',
            FailedMessageEvent::class => 'onFailed',
        ];
    }

    public function onMessage(MessageEvent $event): void
    {
        $msg = $event->getMessage();
        $this->mailerLogger->info('[Mailer] Preparing to send email', [
            'subject' => $msg->getSubject(),
            'to'      => array_map(fn($a) => $a->toString(), $msg->getTo()),
            'from'    => array_map(fn($a) => $a->toString(), $msg->getFrom()),
        ]);
    }

    public function onSent(SentMessageEvent $event): void
    {
        $sent    = $event->getMessage();
        $debug   = $sent->getDebug();
        $this->mailerLogger->info('[Mailer] ✅ Email sent successfully', [
            'message_id' => $sent->getMessageId(),
            'debug'      => $debug,
        ]);
    }

    public function onFailed(FailedMessageEvent $event): void
    {
        $this->mailerLogger->error('[Mailer] ❌ Email FAILED', [
            'error'   => $event->getError()->getMessage(),
            'subject' => $event->getMessage()->getSubject(),
            'to'      => array_map(fn($a) => $a->toString(), $event->getMessage()->getTo()),
        ]);
    }
}
