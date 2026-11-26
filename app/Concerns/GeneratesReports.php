<?php

namespace App\Concerns;

use App\Exports\MultiSheetReportExport;
use App\Models\ReportExport;
use App\Support\Throttle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportComputed\SupportLegacyComputedPropertySyntax;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait GeneratesReports
{
    /**
     * The language the user is browsing in while an export is being built in
     * English, so on-screen messages raised during the export can still use it.
     */
    protected ?string $screenLocale = null;

    /**
     * Run a callback with the interface language forced to English.
     *
     * Exported documents (PDF, Excel, CSV) are formal records that get shared
     * outside the app, so they are always produced in English regardless of
     * the language the signed-in user is browsing in.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function inEnglish(callable $callback): mixed
    {
        // Every export goes through here, so this is the one place to cap how often they can run.
        Throttle::abortIfLimited('export');

        $previous = app()->getLocale();
        $this->screenLocale = $previous;

        // Computed values are cached for the rest of the request. Drop them on
        // the way in (so the export is not built from translated data) and on
        // the way out (so the page is not re-rendered from English data).
        $this->forgetComputedValues();
        app()->setLocale('en');

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
            $this->forgetComputedValues();
        }
    }

    protected function forgetComputedValues(): void
    {
        foreach (SupportLegacyComputedPropertySyntax::getComputedPropertyNames($this) as $name) {
            unset($this->{$name});
        }
    }

    protected function reportId(): string
    {
        return 'RPT-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    }

    protected function logReportExport(string $reportId, string $reportType, string $format): void
    {
        ReportExport::create([
            'user_id' => Auth::id(),
            'report_id' => $reportId,
            'report_type' => $reportType,
            'format' => $format,
        ]);
    }

    /**
     * @param  array<int, array{name: string, kpis?: array<int, array{label: string, value: mixed}>, tables: array<int, array{heading?: string, columns: array<int, string>, rows: array<int, array<int, mixed>>}>}>  $sections
     */
    protected function downloadPdf(string $filename, string $title, string $period, array $sections, ?string $subtitle = null): BinaryFileResponse
    {
        $reportId = $this->reportId();

        $pdf = Pdf::loadView('exports.pdf.report', [
            'title' => $title,
            'subtitle' => $subtitle,
            'reportId' => $reportId,
            'period' => $period,
            'generatedBy' => Auth::user()->name,
            'generatedOn' => now()->format('d M Y, H:i'),
            'org' => 'Malaysian Communications and Multimedia Commission (MCMC)',
            'sections' => $sections,
        ]);

        $this->logReportExport($reportId, $filename, 'pdf');

        // Livewire's native file-download support expects a real
        // BinaryFileResponse backed by a file on disk; dompdf's own
        // ->download() instead returns the PDF bytes inline in a generic
        // Response, which Livewire cannot stream back to the browser.
        $downloadName = $filename.'_'.now()->format('Ymd').'.pdf';
        $tempPath = tempnam(sys_get_temp_dir(), 'sebenarnya-pdf-').'.pdf';
        file_put_contents($tempPath, $pdf->output());

        return response()->download($tempPath, $downloadName)->deleteFileAfterSend();
    }

    /**
     * Stream a UTF-8 CSV (with a BOM so Excel reads accents correctly).
     *
     * Cells that start with =, +, - or @ are prefixed with an apostrophe so
     * spreadsheet software never runs user-supplied text as a formula.
     *
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    protected function downloadCsv(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        $this->logReportExport($this->reportId(), $filename, 'csv');

        // Build every row now. The download is streamed after this method has
        // returned, by which time a lazily mapped cursor would run outside the
        // English-only window that exports are produced in.
        $rows = is_array($rows) ? $rows : iterator_to_array($rows, false);

        $clean = static function (mixed $value): string {
            $text = (string) $value;

            return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$text : $text;
        };

        return response()->streamDownload(function () use ($headings, $rows, $clean): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map($clean, $headings));

            foreach ($rows as $row) {
                fputcsv($out, array_map($clean, $row));
            }

            fclose($out);
        }, $filename.'_'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<int, array{title: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>  $sheets
     */
    protected function downloadExcel(string $filename, array $sheets): BinaryFileResponse|StreamedResponse
    {
        $this->logReportExport($this->reportId(), $filename, 'excel');

        return Excel::download(new MultiSheetReportExport($sheets), $filename.'_'.now()->format('Ymd').'.xlsx');
    }
}
