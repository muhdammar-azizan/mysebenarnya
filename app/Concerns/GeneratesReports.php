<?php

namespace App\Concerns;

use App\Exports\MultiSheetReportExport;
use App\Models\ReportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait GeneratesReports
{
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
