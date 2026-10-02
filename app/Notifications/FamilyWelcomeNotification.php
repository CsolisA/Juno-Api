<?php

namespace App\Notifications;

use App\Models\Family;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FamilyWelcomeNotification extends Notification
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
        $baseUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        return (new MailMessage)
            ->subject('Bienvenidos - '.config('app.name'))
            ->line("Recibimos la información de la familia {$this->family->last_name_one} {$this->family->last_name_two}.")
            ->line("Su usuario para ingresar al portal es: {$this->family->user}")
            ->action('Crear contraseña', $baseUrl.'/portal/reset-password?token='.$this->token)
            ->line('Este enlace es válido por 7 días.');
    }
}
