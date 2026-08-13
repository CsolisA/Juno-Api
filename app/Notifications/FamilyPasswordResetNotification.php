<?php

namespace App\Notifications;

use App\Models\Family;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FamilyPasswordResetNotification extends Notification
{
    public function __construct(
        private readonly string $token,
        private readonly Family $family,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
            .'/portal/reset-password?token='.$this->token;

        return (new MailMessage)
            ->subject('Restablecer contraseña - '.config('app.name'))
            ->line("Se solicitó restablecer la contraseña de la familia {$this->family->user}.")
            ->action('Restablecer contraseña', $resetUrl)
            ->line('Si no solicitaste este cambio, puedes ignorar este mensaje.');
    }
}
