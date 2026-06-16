<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Eksport Excel generik — terima tajuk lajur + baris data (array biasa).
 * Digunakan oleh semua laporan (?format=xls) supaya tiada kelas berulang.
 */
class JadualExport implements FromArray, WithHeadings
{
    public function __construct(
        private array $headings,
        private array $rows,
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
