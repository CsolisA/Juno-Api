<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TermsVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Director only. Uploading never overwrites: each file becomes the next version and the old
 * ones are kept, so past acceptances still point at the document the family actually read.
 */
class TermsController extends Controller
{
    public function index(): JsonResponse
    {
        $versions = TermsVersion::with('uploader:id,name')->orderByDesc('version')->get();

        return response()->json($versions->map(fn (TermsVersion $terms) => $this->format($terms)));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $data['file'];
        $disk = Storage::disk(TermsVersion::DISK);

        $path = null;

        try {
            $terms = DB::transaction(function () use ($file, $disk, $admin, &$path) {
                $next = (TermsVersion::lockForUpdate()->max('version') ?? 0) + 1;

                $path = $disk->putFileAs('terms', $file, "terms-v{$next}.pdf");

                return TermsVersion::create([
                    'version' => $next,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'uploaded_by' => $admin->id,
                ]);
            });
        } catch (Throwable $e) {
            if ($path) {
                $disk->delete($path);
            }

            throw $e;
        }

        return response()->json($this->format($terms->load('uploader:id,name')), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(TermsVersion $terms): array
    {
        return $terms->toPublicArray() + [
            'originalName' => $terms->original_name,
            'uploadedBy' => $terms->uploader?->name,
        ];
    }
}
