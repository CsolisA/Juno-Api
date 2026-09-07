<?php

namespace App\Services\Staff;

use App\Enums\CredentialMethod;
use App\Models\AdminUser;
use App\Notifications\StaffInviteNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The two credential-setup paths shared by staff creation and director-triggered password
 * resets: an email invite (no password until accepted) or a director-issued temporary password.
 */
class StaffCredentialService
{
    /**
     * @return string|null The plaintext temporary password when `$method` is TempPassword, so
     *                     the caller can return it once in the API response; null for Invite.
     */
    public function issue(AdminUser $staff, CredentialMethod $method): ?string
    {
        return match ($method) {
            CredentialMethod::Invite => $this->issueInvite($staff),
            CredentialMethod::TempPassword => $this->issueTempPassword($staff),
        };
    }

    private function issueInvite(AdminUser $staff): ?string
    {
        $token = Str::random(64);

        $staff->update([
            'credential_method' => CredentialMethod::Invite,
            'invite_token' => $token,
            'invite_expires_at' => now()->addDays(7),
            'must_reset_password' => false,
            'status' => false,
        ]);

        Notification::route('mail', $staff->email)
            ->notify(new StaffInviteNotification($token, $staff));

        return null;
    }

    private function issueTempPassword(AdminUser $staff): string
    {
        $plainPassword = Str::password(12);

        $staff->update([
            'credential_method' => CredentialMethod::TempPassword,
            'password' => $plainPassword,
            'must_reset_password' => true,
            'status' => true,
            'invite_token' => null,
            'invite_expires_at' => null,
        ]);

        return $plainPassword;
    }
}
