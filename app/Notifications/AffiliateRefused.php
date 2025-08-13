<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AffiliateRefused extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reason
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre demande d\'affiliation')
            ->greeting('Bonjour ' . $notifiable->nom_complet . ',')
            ->line('Nous vous remercions pour votre intérêt à rejoindre notre programme d\'affiliation.')
            ->line('Après examen de votre demande, nous ne pouvons malheureusement pas l\'approuver pour le moment.')
            ->line('Raison : ' . $this->reason)
            ->line('N\'hésitez pas à nous contacter si vous avez des questions ou souhaitez soumettre une nouvelle demande à l\'avenir.')
            ->line('Cordialement,')
            ->line('L\'équipe d\'administration');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'affiliate_refused',
            'message' => 'Votre demande d\'affiliation a été refusée',
            'reason' => $this->reason,
        ];
    }
}
