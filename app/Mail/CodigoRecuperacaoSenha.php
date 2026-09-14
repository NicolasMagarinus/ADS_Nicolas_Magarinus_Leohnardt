<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CodigoRecuperacaoSenha extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $codigo,
        public int $minutosValidade,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Seu código de recuperação do Drinkerito',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.codigo-recuperacao',
        );
    }
}
