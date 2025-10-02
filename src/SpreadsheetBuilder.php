<?php

namespace Esikat\Helper;

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpreadsheetBuilder
{
    protected Spreadsheet $spreadsheet;

    public function __construct()
    {
        $this->spreadsheet = new Spreadsheet();
        // Hapus sheet default agar tidak kosong
        $this->spreadsheet->removeSheetByIndex(0);
    }

    public function build(array $config, array $data, string $sheetName): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle($sheetName);

        // Header
        foreach ($config as $col) {
            $cell = $col['koordinat'] . '1';
            $sheet->setCellValue($cell, $col['text']);
            $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($cell)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Data
        $rowIndex = 2;
        foreach ($data as $row) {
            foreach ($config as $col) {
                $cell = $col['koordinat'] . $rowIndex;
                $value = $row[$col['data']] ?? '';
                $sheet->setCellValue($cell, $value);
                $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }
            $rowIndex++;
        }
    }

    public function download(string $filename = 'export.xlsx'): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($this->spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function saveToFile(string $path): void
    {
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($path);
    }
}