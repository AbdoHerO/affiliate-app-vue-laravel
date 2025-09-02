<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;

class TestPasswordResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'password:test-reset {email}';

    /**
     * The console command description.
     */
    protected $description = 'Test password reset email functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        $this->info("Testing password reset email...");
        $this->info("Email: {$email}");

        try {
            // Check if user exists
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                $this->error("❌ User with email {$email} not found!");
                return 1;
            }

            $this->info("✅ User found: {$user->nom_complet}");
            $this->info("User status: {$user->statut}");

            // Display current configuration
            $this->info("\n=== Configuration ===");
            $this->info("APP_ENV: " . config('app.env'));
            $this->info("APP_DEBUG: " . (config('app.debug') ? 'true' : 'false'));
            $this->info("MAIL_MAILER: " . config('mail.default'));
            $this->info("MAIL_HOST: " . config('mail.mailers.smtp.host'));
            $this->info("MAIL_PORT: " . config('mail.mailers.smtp.port'));
            $this->info("MAIL_USERNAME: " . config('mail.mailers.smtp.username'));
            $this->info("MAIL_ENCRYPTION: " . config('mail.mailers.smtp.encryption'));
            $this->info("MAIL_FROM_ADDRESS: " . config('mail.from.address'));
            $this->info("MAIL_FROM_NAME: " . config('mail.from.name'));
            $this->info("APP_URL: " . config('app.url'));
            $this->info("FRONTEND_URL: " . config('app.frontend_url', 'Not set'));

            // Check if queue is being used
            $this->info("QUEUE_CONNECTION: " . config('queue.default'));

            // Check mail configuration details
            $mailConfig = config('mail.mailers.smtp');
            $this->info("\n=== SMTP Details ===");
            foreach ($mailConfig as $key => $value) {
                if ($key === 'password') {
                    $this->info("$key: " . (empty($value) ? 'NOT SET' : '***HIDDEN***'));
                } else {
                    $this->info("$key: " . ($value ?? 'NULL'));
                }
            }

            // Check if user is active
            if ($user->statut !== 'actif') {
                $this->warn("⚠️ Warning: User status is '{$user->statut}', not 'actif'");
                $this->warn("Password reset may be blocked for inactive users");
            }

            // Send password reset link
            $this->info("\n🔄 Sending password reset email...");
            
            $status = Password::sendResetLink(['email' => $email]);

            if ($status === Password::RESET_LINK_SENT) {
                $this->info("✅ Password reset email sent successfully!");

                // Check if using queues
                if (config('queue.default') !== 'sync') {
                    $this->warn("⚠️ IMPORTANT: You're using queue driver '" . config('queue.default') . "'");
                    $this->warn("The email may be queued and not sent immediately.");
                    $this->warn("Run 'php artisan queue:work' to process queued emails.");

                    // Check for queued jobs
                    $queuedJobs = DB::table('jobs')->count();
                    $this->info("Queued jobs: {$queuedJobs}");
                }

                $this->info("Check your inbox at: {$email}");

                // Show what the reset URL would look like
                $frontendUrl = config('app.frontend_url', config('app.url'));
                $this->info("\n📧 Reset URL format:");
                $this->info("{$frontendUrl}/reset-password?token=XXXXX&email=" . urlencode($email));

            } else {
                $this->error("❌ Failed to send password reset email");
                $this->error("Status: {$status}");
                
                $errorMessage = match($status) {
                    Password::INVALID_USER => 'Invalid user',
                    Password::RESET_THROTTLED => 'Reset throttled (too many attempts)',
                    default => 'Unknown error'
                };
                
                $this->error("Reason: {$errorMessage}");
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("\n❌ Exception occurred:");
            $this->error("Error: " . $e->getMessage());
            $this->error("File: " . $e->getFile() . ":" . $e->getLine());
            
            // Log the error for debugging
            Log::error('Password reset test failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }

        return 0;
    }
}
