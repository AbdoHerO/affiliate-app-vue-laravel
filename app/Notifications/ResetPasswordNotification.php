<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The password reset token.
     */
    public string $token;

    /**
     * The callback that should be used to create the reset password URL.
     */
    public static $createUrlCallback;

    /**
     * The callback that should be used to build the mail message.
     */
    public static $toMailCallback;

    /**
     * Create a notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        return $this->buildMailMessage($this->resetUrl($notifiable));
    }

    /**
     * Get the reset password notification mail message for the given URL.
     */
    protected function buildMailMessage(string $url): MailMessage
    {
        return (new MailMessage)
            ->subject(Lang::get('Reset Password Notification'))
            ->view('emails.password-reset', [
                'resetUrl' => $url,
                'user' => $this->getUser(),
            ]);
    }

    /**
     * Get the reset URL for the given notifiable.
     */
    protected function resetUrl(object $notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        // Create the frontend reset URL
        $frontendUrl = config('app.frontend_url', config('app.url'));
        
        return $frontendUrl . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    /**
     * Get the user for the email template.
     */
    protected function getUser(): object
    {
        // This will be set when the notification is sent
        return $this->user ?? (object) ['nom_complet' => 'User'];
    }

    /**
     * Set the callback that should be used to create the reset password URL.
     */
    public static function createUrlUsing(?callable $callback): void
    {
        static::$createUrlCallback = $callback;
    }

    /**
     * Set the callback that should be used to build the mail message.
     */
    public static function toMailUsing(?callable $callback): void
    {
        static::$toMailCallback = $callback;
    }

    /**
     * Set the user for the email template.
     */
    public function setUser(object $user): self
    {
        $this->user = $user;
        return $this;
    }
}
