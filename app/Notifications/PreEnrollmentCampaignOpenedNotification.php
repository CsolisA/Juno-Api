<?php

namespace App\Notifications;

use App\Models\Family;
use App\Models\PreEnrollmentCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class PreEnrollmentCampaignOpenedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly PreEnrollmentCampaign $campaign,
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
        $portalUrl = rtrim(config('app.frontend_url', config('app.url')), '/').'/portal/pre-enrollment';

        $studentNames = $this->campaign->forms
            ->where('family_id', $this->family->id)
            ->where('is_excluded', false)
            ->map(fn ($form) => trim("{$form->student->name} {$form->student->last_name}"))
            ->unique()
            ->values();

        $message = (new MailMessage)
            ->subject("{$this->campaign->name} está lista para completarse")
            ->line("El formulario de {$this->campaign->name} ya está disponible para su familia.");

        foreach ($studentNames as $studentName) {
            $message->line("- {$studentName}");
        }

        if ($this->campaign->due_date) {
            $message->line('Fecha límite: '.$this->campaign->due_date->toFormattedDateString());
        }

        return $message
            ->action('Completar formulario', $portalUrl)
            ->line('Ingrese con su usuario y contraseña habituales.');
    }
}
