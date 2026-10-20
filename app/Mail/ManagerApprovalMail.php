<?php

namespace App\Mail;

use App\Models\CommercialContact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagerApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly CommercialContact $contact, public readonly string $temporaryPassword) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '¡Tu solicitud ha sido aprobada! | Gestión Industrial');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.manager-approval');
    }
}
