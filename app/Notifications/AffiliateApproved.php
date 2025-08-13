<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AffiliateApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ?string $reason = null
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
        $message = (new MailMessage)
            ->subject('Félicitations ! Votre demande d\'affiliation a été approuvée')
            ->greeting('Bonjour ' . $notifiable->nom_complet . ',')
            ->line('Nous avons le plaisir de vous informer que votre demande d\'affiliation a été approuvée.')
            ->line('Vous pouvez maintenant accéder à votre espace affilié et commencer à passer des commandes.');

        if ($this->reason) {
            $message->line('Note de l\'administrateur : ' . $this->reason);
        }

        return $message
            ->action('Accéder à mon espace', url('/affiliate/dashboard'))
            ->line('Merci de votre confiance et bienvenue dans notre programme d\'affiliation !');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'affiliate_approved',
            'message' => 'Votre demande d\'affiliation a été approuvée',
            'reason' => $this->reason,
            'action_url' => url('/affiliate/dashboard'),
        ];
    }
}
