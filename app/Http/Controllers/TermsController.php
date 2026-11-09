<?php

namespace App\Http\Controllers;

use App\Models\TermsVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public: onboarding families only hold an invite token, so there is no auth to lean on.
 */
class TermsController extends Controller
{
    public function current(): JsonResponse
    {
        $terms = TermsVersion::current();

        abort_unless($terms, 404, 'Aún no hay términos y condiciones publicados.');

        return response()->json($terms->toPublicArray());
    }

    public function currentPdf(): StreamedResponse
    {
        $terms = TermsVersion::current();

        abort_unless($terms, 404);

        return $this->stream($terms);
    }

    public function pdf(int $version): StreamedResponse
    {
        return $this->stream(TermsVersion::where('version', $version)->firstOrFail());
    }

    private function stream(TermsVersion $terms): StreamedResponse
    {
        $disk = Storage::disk(TermsVersion::DISK);

        abort_unless($disk->exists($terms->path), 404);

        return $disk->response($terms->path, 'terminos-y-condiciones-v'.$terms->version.'.pdf', [
            'Content-Type' => 'application/pdf',
        ], 'inline');
    }
}
