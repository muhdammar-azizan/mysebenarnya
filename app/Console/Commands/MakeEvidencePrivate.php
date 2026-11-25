<?php

namespace App\Console\Commands;

use App\Models\InquiryEvidence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MakeEvidencePrivate extends Command
{
    protected $signature = 'evidence:make-private';

    protected $description = 'Move evidence files that were uploaded to the public disk onto the private disk (safe to run more than once)';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        $moved = 0;
        $missing = 0;

        InquiryEvidence::query()->orderBy('id')->each(function (InquiryEvidence $evidence) use ($public, $private, &$moved, &$missing) {
            $path = $evidence->file_path;

            if (! $public->exists($path)) {
                if (! $private->exists($path)) {
                    $missing++;
                }

                return;
            }

            $private->put($path, $public->get($path));

            // Only remove the public copy once the private copy is confirmed.
            if ($private->exists($path)) {
                $public->delete($path);
                $moved++;
            }
        });

        $this->info("Moved {$moved} file(s) to private storage.");

        if ($missing > 0) {
            $this->warn("{$missing} evidence record(s) have no file on disk (for example demo data).");
        }

        return self::SUCCESS;
    }
}
