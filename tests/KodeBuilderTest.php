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
}
