<?php

use PHPUnit\Framework\TestCase;
use Esikat\Helper\SpreadsheetBuilder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SpreadsheetBuilderTest extends TestCase
{
    public function testSaveToFileCreatesXlsxFile()
    {
        $config = [
            ['koordinat' => 'A', 'text' => 'NOMOR AJU', 'data' => 'nomorAju'],
        ];

        $data = [
            ['nomorAju' => '123'],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'spreadsheet_test_') . '.xlsx';

        $builder = new SpreadsheetBuilder();
        $builder->build($config, $data, 'SheetTest'); // tambahkan nama sheet
        $builder->saveToFile($tempFile);

        $this->assertFileExists($tempFile);
        $this->assertGreaterThan(0, filesize($tempFile));

        // Cek isi file
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($tempFile);
        $sheet = $spreadsheet->getSheetByName('SheetTest'); // ambil berdasarkan nama sheet

        $this->assertEquals('NOMOR AJU', $sheet->getCell('A1')->getValue());
        $this->assertEquals('123', $sheet->getCell('A2')->getValue());

        unlink($tempFile);
    }
}
