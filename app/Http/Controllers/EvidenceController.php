<?php

namespace App\Http\Controllers;

use App\Models\InquiryEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams an evidence file only to people allowed to see its inquiry:
 * the submitter, MCMC staff, and staff of the assigned agency.
 */
class EvidenceController extends Controller
{
    /** Content types we are willing to render inline; anything else is a download. */
    private const INLINE_TYPES = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    public function __invoke(Request $request, InquiryEvidence $evidence): Response
    {
        Gate::authorize('view', $evidence->inquiry);

        $disk = $this->diskFor($evidence->file_path);

        abort_if($disk === null, 404);

        // Derive the type from the stored extension, never from the client-supplied value.
        $extension = strtolower(pathinfo($evidence->file_path, PATHINFO_EXTENSION));
        $inline = isset(self::INLINE_TYPES[$extension]);

        return Storage::disk($disk)->response(
            $evidence->file_path,
            $evidence->file_name,
            [
                'Content-Type' => self::INLINE_TYPES[$extension] ?? 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                // Neutralise any active content hidden inside an uploaded file.
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ],
            $inline ? 'inline' : 'attachment'
        );
    }

    /**
     * New uploads live on the private disk. Files uploaded before evidence
     * became private stay readable here until `evidence:make-private` moves them.
     */
    private function diskFor(string $path): ?string
    {
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }
}
