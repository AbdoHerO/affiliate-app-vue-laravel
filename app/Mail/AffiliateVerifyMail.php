<?php

namespace App\Mail;

use App\Models\Affilie;
use App\Models\AffiliateEmailVerification;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AffiliateVerifyMail extends Mailable
{
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Affilie $affilie,
        public AffiliateEmailVerification $verification
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vérifiez votre adresse email - Arif Affilio',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $verificationUrl = url('/api/public/affiliates/verify?' . http_build_query([
            'token' => $this->verification->token,
            'email' => $this->affilie->email,
        ]));

        return new Content(
            view: 'emails.affiliate-verify',
            with: [
                'affilie' => $this->affilie,
                'verificationUrl' => $verificationUrl,
                'expiresAt' => $this->verification->expires_at,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
