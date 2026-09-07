<?php

namespace App\Notifications;

use App\Models\AdminUser;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInviteNotification extends Notification
{
    public function __construct(
        private readonly string $token,
        private readonly AdminUser $staff,
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
        $inviteUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
            .'/staff/accept-invite?token='.$this->token;

        return (new MailMessage)
            ->subject('Configura tu cuenta - '.config('app.name'))
            ->line("Hola {$this->staff->name}, se creó una cuenta de personal para ti en ".config('app.name').'.')
            ->action('Configurar contraseña', $inviteUrl)
            ->line('Este enlace expira en 7 días.');
    }
}
