<?php

namespace App\Notifications;

use App\Enums\StatusCadastro;
use App\Models\CadastroBebida;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa o autor de uma receita que ela foi aprovada ou rejeitada.
 *
 * Sem isso a pessoa enviava a receita e nunca mais era avisada: precisava
 * lembrar de voltar ao perfil por conta própria para descobrir o desfecho —
 * e o motivo da rejeição, que já era gravado, ficava invisível na prática.
 *
 * ShouldQueue tira o SMTP do caminho de quem clicou em aprovar. Com
 * QUEUE_CONNECTION=sync não muda nada — o envio continua no mesmo request —,
 * mas no dia em que a fila for de verdade o admin deixa de esperar o
 * provedor. A notificação é serializada inteira, com o CadastroBebida já
 * atualizado, porque CadastroBebidaController::avisarAutor só é chamado
 * depois do commit.
 */
class BebidaModerada extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int|null  $cdBebida  id no catálogo, que só existe depois da
     *                              aprovação e serve para linkar a bebida.
     */
    public function __construct(
        public CadastroBebida $cadastro,
        public ?int $cdBebida = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $aprovada = $this->cadastro->id_status === StatusCadastro::Aprovada;

        return (new MailMessage)
            ->subject($aprovada
                ? "Sua receita {$this->cadastro->nm_bebida} foi aprovada!"
                : "Sobre a sua receita {$this->cadastro->nm_bebida}")
            ->view('emails.bebida-moderada', [
                'nome' => $notifiable->name,
                'cadastro' => $this->cadastro,
                'aprovada' => $aprovada,
                'url' => $aprovada && $this->cdBebida
                    ? route('bebida.show', $this->cdBebida)
                    : route('perfil.index'),
            ]);
    }
}
