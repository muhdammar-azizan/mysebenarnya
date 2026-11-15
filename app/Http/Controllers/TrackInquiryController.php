<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackInquiryController extends Controller
{
    /**
     * Guest-friendly status lookup by reference number.
     *
     * Reference numbers are sequential, so this deliberately exposes only the
     * status and dates: never the title, description, agency or submitter.
     */
    public function __invoke(Request $request): View
    {
        $reference = strtoupper(trim((string) $request->query('ref', '')));
        $inquiry = null;
        $error = null;

        if ($reference !== '') {
            if (! preg_match('/^INQ-\d{4}-\d{5}$/', $reference)) {
                $error = __('Please enter a valid reference number, for example INQ-2026-00001.');
            } else {
                $inquiry = Inquiry::where('reference_no', $reference)->first();

                if (! $inquiry) {
                    $error = __('No inquiry was found with that reference number.');
                }
            }
        }

        return view('track', [
            'reference' => $reference,
            'inquiry' => $inquiry,
            'error' => $error,
        ]);
    }
}
