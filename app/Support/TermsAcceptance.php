<?php

namespace App\Support;

use App\Models\TermsVersion;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Validation shared by every submit that requires accepting the terms PDF.
 */
class TermsAcceptance
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $current = TermsVersion::current();

        if (! $current) {
            throw new HttpException(503, 'Los términos y condiciones aún no están configurados. Comunícate con la escuela.');
        }

        return [
            'termsAccepted' => ['required', 'accepted'],
            'termsVersion' => ['required', Rule::in([(string) $current->version])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'termsAccepted.required' => 'Debes aceptar los términos y condiciones para continuar.',
            'termsAccepted.accepted' => 'Debes aceptar los términos y condiciones para continuar.',
            'termsVersion.required' => 'No pudimos identificar la versión de los términos aceptados.',
            'termsVersion.in' => 'Los términos y condiciones fueron actualizados. Léelos de nuevo para continuar.',
        ];
    }
}
