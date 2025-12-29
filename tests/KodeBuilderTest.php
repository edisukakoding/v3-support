<?php

use Esikat\Helper\KodeBuilder;
use Esikat\Helper\QueryBuilder;
use PHPUnit\Framework\TestCase;

class KodeBuilderTest extends TestCase
{
    private $mockQueryBuilder;
    private $kodeBuilder;

    protected function setUp(): void
    {
        $this->mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $this->kodeBuilder = new KodeBuilder($this->mockQueryBuilder, 1);
    }

    public function testPreviewNoTransaksiTanpaTransaksiSebelumnya()
    {
        $this->mockQueryBuilder->method('table')->willReturnSelf();

        $this->mockQueryBuilder->method('where')->willReturnSelf();

        $this->mockQueryBuilder->method('first')->willReturnOnConsecutiveCalls(
            ['singkatan' => 'ABC'], // getUsaha
            null                     // getTransaksi (tidak ditemukan)
        );

        $hasil = $this->kodeBuilder->previewNoTransaksi('TRX');

        $this->assertStringContainsString('/' . date('ym') . '/ABC/0001', $hasil);
    }

    public function testBuatNoTransaksiDenganTransaksiSebelumnya()
    {
        $this->mockQueryBuilder->method('table')->willReturnSelf();
        $this->mockQueryBuilder->method('where')->willReturnSelf();

        $this->mockQueryBuilder->method('first')->willReturnOnConsecutiveCalls(
            ['singkatan' => 'DEF'], // getUsaha
            ['noakhir' => '5']      // getTransaksi (ditemukan)
        );

        $this->mockQueryBuilder->expects($this->once())
            ->method('update')
            ->with($this->equalTo(['noakhir' => '0006']));

        $hasil = $this->kodeBuilder->buatNoTransaksi('INV');

        $this->assertStringContainsString('/' . date('ym') . '/DEF/0006', $hasil);
    }

    public function testPreviewKodeBarangBaru()
    {
        $this->mockQueryBuilder->method('table')->willReturnSelf();
        $this->mockQueryBuilder->method('where')->willReturnSelf();

        $this->mockQueryBuilder->method('first')->willReturn(null); // Belum ada data kode barang

        $hasil = $this->kodeBuilder->previewKodeBarang('FAB');

        $this->assertEquals('FAB000001', $hasil);
    }

    public function testBuatKodeBarangDenganDataSebelumnya()
    {
        $this->mockQueryBuilder->method('table')->willReturnSelf();
        $this->mockQueryBuilder->method('where')->willReturnSelf();

        $this->mockQueryBuilder->method('first')->willReturn(['noakhir' => '12']);

        $this->mockQueryBuilder->expects($this->once())
            ->method('update')
            ->with(['noakhir' => '000013']);

        $hasil = $this->kodeBuilder->buatKodeBarang('ACC');

        $this->assertEquals('ACC000013', $hasil);
    }

    public function testPreviewKodeEntitasDenganDataExisting()
    {
        $this->mockQueryBuilder->method('table')->willReturnSelf();
        $this->mockQueryBuilder->method('where')->willReturnSelf();

        // Mock return untuk getKodeEntitas (ada data sebelumnya)
        $this->mockQueryBuilder->method('first')->willReturn(['noakhir' => 5]);

        $hasil = $this->kodeBuilder->previewKodeEntitas('INV');

        $this->assertEquals('INV' . date('ym') . '0006', $hasil);
    }

    public function testPreviewKodeEntitasTanpaDataExisting()
    {
        $this->mockQueryBuilder->method('table')->willReturnSelf();
        $this->mockQueryBuilder->method('where')->willReturnSelf();

        // Mock return untuk getKodeEntitas (tidak ada data)
        $this->mockQueryBuilder->method('first')->willReturn(null);

        $hasil = $this->kodeBuilder->previewKodeEntitas('SO');

        $this->assertEquals('SO' . date('ym') . '0001', $hasil);
    }

    public function testBuatKodeEntitasDenganDataExisting()
    {
        $this->mockQueryBuilder->method('table')->willReturnSelf();
        $this->mockQueryBuilder->method('where')->willReturnSelf();

        // Mock untuk getUsaha dan getKodeEntitas
        $this->mockQueryBuilder->method('first')->willReturnOnConsecutiveCalls(
            ['singkatan' => 'ABC'],  // getUsaha
            ['noakhir' => 10]        // getKodeEntitas (ada data)
        );

        // Expect update dipanggil dengan noakhir baru
        $this->mockQueryBuilder->expects($this->once())
            ->method('update')
            ->with(['noakhir' => 11]);

        $hasil = $this->kodeBuilder->buatKodeEntitas('INV');

        $this->assertEquals('INV' . date('ym') . '0011', $hasil);
    }

    public function testBuatKodeEntitasTanpaDataExisting()
    {
        $this->mockQueryBuilder->method('table')->willReturnSelf();
        $this->mockQueryBuilder->method('where')->willReturnSelf();

        // Mock untuk getUsaha dan getKodeEntitas
        $this->mockQueryBuilder->method('first')->willReturnOnConsecutiveCalls(
            ['singkatan' => 'XYZ'],  // getUsaha
            null                      // getKodeEntitas (tidak ada data)
        );

        // Expect insert dipanggil dengan data baru
        $this->mockQueryBuilder->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return $data['kdusaha'] === 1 &&
                    $data['prefix'] === 'PO' &&
                    $data['singkatan'] === 'XYZ' &&
                    $data['noakhir'] === 1 &&
                    $data['asaldata'] === 'web' &&
                    isset($data['tanggal']);
            }));

        $hasil = $this->kodeBuilder->buatKodeEntitas('PO');

        $this->assertEquals('PO' . date('ym') . '0001', $hasil);
    }

    public function testPreviewKodeEntitasFormatKonsisten()
    {
        $this->mockQueryBuilder->method('table')->willReturnSelf();
        $this->mockQueryBuilder->method('where')->willReturnSelf();
        $this->mockQueryBuilder->method('first')->willReturn(['noakhir' => 999]);

        $hasil = $this->kodeBuilder->previewKodeEntitas('TEST');

        // Format: PREFIX (4 char) + YYMM (4 digit) + Sequential (4 digit)
        $this->assertEquals('TEST' . date('ym') . '1000', $hasil);
        $this->assertEquals(12, strlen($hasil)); // TEST(4) + 2512(4) + 1000(4) = 12

        // Pastikan 8 karakter terakhir adalah angka (YYMM + nomor urut)
        $this->assertMatchesRegularExpression('/^TEST\d{8}$/', $hasil);
    }

    public function testBuatKodeEntitasUpdateDenganNomorBesar()
    {
        $this->mockQueryBuilder->method('table')->willReturnSelf();
        $this->mockQueryBuilder->method('where')->willReturnSelf();

        $this->mockQueryBuilder->method('first')->willReturnOnConsecutiveCalls(
            ['singkatan' => 'TST'],
            ['noakhir' => 9998]
        );

        $this->mockQueryBuilder->expects($this->once())
            ->method('update')
            ->with(['noakhir' => 9999]);

        $hasil = $this->kodeBuilder->buatKodeEntitas('TST');

        $this->assertEquals('TST' . date('ym') . '9999', $hasil);
    }
}
