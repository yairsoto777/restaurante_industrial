<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content,Envelope};
class SuperadminActivationMail extends Mailable
{
    public function __construct(public readonly string $recipientName,public readonly string $activationUrl,public readonly int $expires){}
    public function envelope():Envelope{return new Envelope(subject:'Configura tu acceso a NUTRILIADO');}
    public function content():Content{return new Content(view:'emails.superadmin-activation');}
}
