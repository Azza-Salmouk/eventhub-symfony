<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-email',
    description: 'Send a test email and show the full SMTP debug trace.',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(env: 'MAILER_DSN')]
        private string $mailerDsn,
        #[Autowire(env: 'MAILER_FROM_EMAIL')]
        private string $fromEmail,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Recipient email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = $input->getArgument('to');

        // Mask password in display
        $safeDsn = preg_replace('/:\/\/([^:]+):([^@]+)@/', '://$1:***@', $this->mailerDsn);

        $io->title('EventHub — Email Debug Test');
        $io->definitionList(
            ['MAILER_DSN'   => $safeDsn],
            ['From'         => $this->fromEmail],
            ['To'           => $to],
        );

        if (str_starts_with($this->mailerDsn, 'null://')) {
            $io->warning('MAILER_DSN=null://null — emails are discarded. Set a real DSN.');
        }

        // Build email
        $email = (new Email())
            ->from(new Address($this->fromEmail, 'EventHub'))
            ->to($to)
            ->subject('EventHub — SMTP Test ✓ ' . date('H:i:s'))
            ->html(
                '<h2 style="color:#6366f1">EventHub SMTP Test</h2>' .
                '<p>Sent at: <strong>' . date('Y-m-d H:i:s') . '</strong></p>' .
                '<p>If you receive this, SMTP is working correctly.</p>' .
                '<p>DSN: <code>' . htmlspecialchars($safeDsn) . '</code></p>'
            );

        // Send via a direct transport to capture the debug output
        try {
            $transport = Transport::fromDsn($this->mailerDsn);
            $directMailer = new Mailer($transport);
            $sent = $directMailer->send($email);

            $io->success('✅ Email sent successfully to ' . $to);

            if ($sent instanceof SentMessage) {
                $debug = $sent->getDebug();
                if ($debug) {
                    $io->section('SMTP Debug Trace');
                    $io->text(explode("\n", $debug));
                }
                $io->text('Message-ID: ' . $sent->getMessageId());
            }

            $io->note([
                'Check your inbox (and spam folder).',
                'Also check: docker exec eventhub_php cat var/log/mailer.log',
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error([
                '❌ FAILED: ' . $e->getMessage(),
                '',
                'Common causes:',
                '  • Wrong App Password (must be 16 chars, no spaces)',
                '  • 2-Step Verification not enabled on Google Account',
                '  • "Less secure app access" blocked (use App Password instead)',
                '  • Port 587 blocked by your network/ISP',
                '  • Gmail account has too many failed attempts (wait 1 hour)',
            ]);

            // Network connectivity check
            $io->section('Network Connectivity Check');
            $host = 'smtp.gmail.com';
            $port = 587;
            $conn = @fsockopen($host, $port, $errno, $errstr, 5);
            if ($conn) {
                fclose($conn);
                $io->text("✅ TCP connection to {$host}:{$port} — OK");
                $io->text('   → Network is fine. The issue is authentication.');
            } else {
                $io->text("❌ Cannot connect to {$host}:{$port} — {$errstr} ({$errno})");
                $io->text('   → Port 587 is blocked. Try Mailpit instead (see below).');
            }

            $io->note([
                'To test without Gmail, use Mailpit:',
                '  1. Set MAILER_DSN=smtp://mailpit:1025 in .env',
                '  2. docker compose restart php',
                '  3. php bin/console app:test-email test@example.com',
                '  4. Open http://localhost:8025 to see the email',
            ]);

            return Command::FAILURE;
        }
    }
}
