<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'email:test {email} {--subject=Test Email} {--message=This is a test email}';

    /**
     * The console command description.
     */
    protected $description = 'Send a test email to verify email configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $subject = $this->option('subject');
        $message = $this->option('message');

        $this->info("Testing email configuration...");
        $this->info("To: {$email}");
        $this->info("Subject: {$subject}");
        $this->info("Message: {$message}");

        try {
            // Display current mail configuration
            $this->info("\n=== Mail Configuration ===");
            $this->info("MAIL_MAILER: " . config('mail.default'));
            $this->info("MAIL_HOST: " . config('mail.mailers.smtp.host'));
            $this->info("MAIL_PORT: " . config('mail.mailers.smtp.port'));
            $this->info("MAIL_USERNAME: " . config('mail.mailers.smtp.username'));
            $this->info("MAIL_ENCRYPTION: " . config('mail.mailers.smtp.encryption'));
            $this->info("MAIL_FROM_ADDRESS: " . config('mail.from.address'));
            $this->info("MAIL_FROM_NAME: " . config('mail.from.name'));
            $this->info("FRONTEND_URL: " . config('app.frontend_url', 'Not set'));

            // Send test email
            Mail::raw($message, function ($mail) use ($email, $subject) {
                $mail->to($email)
                     ->subject($subject);
            });

            $this->info("\n✅ Test email sent successfully!");
            $this->info("Check your inbox at: {$email}");

        } catch (\Exception $e) {
            $this->error("\n❌ Failed to send test email:");
            $this->error("Error: " . $e->getMessage());
            
            // Log the error for debugging
            Log::error('Test email failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }

        return 0;
    }
}
