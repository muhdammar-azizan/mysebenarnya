<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MultiSheetReportExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array{title: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>  $sheets
     */
    public function __construct(protected array $sheets) {}

    public function sheets(): array
    {
        return array_map(
            fn (array $sheet) => new GenericSheetExport($sheet['title'], $sheet['headings'], $sheet['rows']),
            $this->sheets,
        );
    }
}
