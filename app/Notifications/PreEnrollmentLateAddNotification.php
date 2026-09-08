<?php

namespace App\Notifications;

use App\Models\Family;
use App\Models\PreEnrollmentCampaign;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Distinct from PreEnrollmentCampaignOpenedNotification per spec: this family wasn't originally
 * eligible and is now being invited after the fact, which reads very differently than "your
 * yearly pre-enrollment is open".
 */
class PreEnrollmentLateAddNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly PreEnrollmentCampaign $campaign,
        private readonly Family $family,
        private readonly Student $student,
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
        $studentName = trim("{$this->student->name} {$this->student->last_name}");

        return (new MailMessage)
            ->subject("Ahora puede completar la pre-matrícula de {$studentName}")
            ->line("{$studentName} ahora califica para {$this->campaign->name}, aunque no estaba incluido originalmente.")
            ->action('Completar formulario', $portalUrl)
            ->line('Ingrese con su usuario y contraseña habituales.');
    }
}
