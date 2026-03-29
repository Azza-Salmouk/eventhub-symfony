<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:debug-mailer',
    description: 'Display the MAILER_DSN actually used by Symfony in this container/environment.',
)]
class DebugMailerCommand extends Command
{
    public function __construct(
        #[Autowire(env: 'MAILER_DSN')]
        private string $mailerDsn,
        #[Autowire('%kernel.environment%')]
        private string $env,
        #[Autowire(env: 'MAILER_FROM_EMAIL')]
        private string $fromEmail,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('EventHub — Mailer Diagnostic');

        // Mask password in DSN for display
        $safeDsn = preg_replace('/:\/\/([^:]+):([^@]+)@/', '://$1:***@', $this->mailerDsn);

        $io->definitionList(
            ['Symfony environment' => $this->env],
            ['MAILER_DSN'          => $safeDsn],
            ['From address'        => $this->fromEmail],
            ['Transport type'      => $this->detectTransport()],
            ['Emails will be sent' => str_starts_with($this->mailerDsn, 'null://') ? '❌ NO (null transport)' : '✅ YES'],
        );

        if (str_starts_with($this->mailerDsn, 'null://')) {
            $io->warning([
                'MAILER_DSN is null://null — all emails are silently discarded.',
                '',
                'To enable real email delivery:',
                '  1. Create .env.local (never commit it)',
                '  2. Add: MAILER_DSN=smtp://user:pass@sandbox.smtp.mailtrap.io:2525?encryption=tls',
                '  3. Restart the container: docker compose restart php',
                '  4. Test: php bin/console app:test-email your@email.com',
            ]);
            return Command::SUCCESS;
        }

        if (str_contains($this->mailerDsn, 'mailtrap')) {
            $io->success('Mailtrap DSN detected — emails will appear in your Mailtrap inbox.');
        } elseif (str_contains($this->mailerDsn, 'gmail')) {
            $io->success('Gmail SMTP DSN detected — emails will be sent via Gmail.');
        } else {
            $io->success('Custom SMTP DSN detected — emails will be sent via your SMTP server.');
        }

        $io->text([
            'Run a test send:',
            '  php bin/console app:test-email your@email.com',
        ]);

        return Command::SUCCESS;
    }

    private function detectTransport(): string
    {
        if (str_starts_with($this->mailerDsn, 'null://'))  return 'null (discard)';
        if (str_contains($this->mailerDsn, 'mailtrap'))    return 'Mailtrap SMTP';
        if (str_contains($this->mailerDsn, 'gmail'))       return 'Gmail SMTP';
        if (str_starts_with($this->mailerDsn, 'smtp://'))  return 'SMTP';
        if (str_starts_with($this->mailerDsn, 'sendmail')) return 'Sendmail';
        return 'Unknown';
    }
}
