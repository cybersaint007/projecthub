<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test {to : Recipient email address}';

    protected $description = 'Send a test email (uses current MAIL_* config; useful for debugging SMTP)';

    public function handle(): int
    {
        $to = $this->argument('to');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');
            return self::FAILURE;
        }

        $this->info('Sending test email to '.$to.'...');

        try {
            Mail::raw('This is a test email from ProjectHub. If you received this, SMTP is working.', function ($message) use ($to) {
                $message->to($to)->subject('ProjectHub SMTP test');
            });
            $this->info('Test email sent. Check inbox (and spam).');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Send failed: '.$e->getMessage());
            $this->line('');
            $this->line('Check: MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD (App Password for Gmail), MAIL_ENCRYPTION=tls');
            $this->line('If using queue: run "php artisan queue:work" or set QUEUE_CONNECTION=sync to test.');
            return self::FAILURE;
        }
    }
}
