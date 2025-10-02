<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Esikat\Helper\Log;
use Esikat\Helper\QueryBuilder;

class LogTest extends TestCase
{
    private function mockQueryBuilder(): QueryBuilder
    {
        $pdoMock = $this->createMock(PDO::class);

        /** @var QueryBuilder&PHPUnit\Framework\MockObject\MockObject $queryBuilderMock */
        $queryBuilderMock = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$pdoMock])
            ->onlyMethods(['table', 'insert'])
            ->getMock();

        return $queryBuilderMock;
    }

    private function createLog(QueryBuilder $queryBuilder): Log
    {
        return new Log($queryBuilder, 'log_table', 1, 'admin', 99);
    }

    /**
     * @param QueryBuilder&PHPUnit\Framework\MockObject\MockObject $qb
     */
    private function expectInsertCalled(QueryBuilder $qb, string $jenis, string $subjek, string $aktifitas): void
    {
        $qb->expects($this->once())
            ->method('table')
            ->with('log_table')
            ->willReturnSelf();

        $qb->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($jenis, $subjek, $aktifitas) {
                return $data['jenis'] === $jenis &&
                       $data['subjek'] === $subjek &&
                       $data['aktifitas'] === $aktifitas;
            }));
    }

    #[Test] public function testInsertLog()   { $this->runLogTest('INSERT',  'produk', 'User @admin menambahkan data produk'); }
    #[Test] public function testUpdateLog()   { $this->runLogTest('UPDATE',  'produk', 'User @admin mengubah data produk'); }
    #[Test] public function testDeleteLog()   { $this->runLogTest('DELETE',  'produk', 'User @admin menghapus data produk'); }
    #[Test] public function testApproveLog()  { $this->runLogTest('APPROVE', 'produk', 'User @admin menyetujui data produk'); }
    #[Test] public function testRejectLog()   { $this->runLogTest('REJECT',  'produk', 'User @admin menolak data produk'); }
    #[Test] public function testLoginLog()    { $this->runLogTest('LOGIN',   'sistem', 'User @admin berhasil masuk sistem'); }
    #[Test] public function testLogoutLog()   { $this->runLogTest('LOGOUT',  'sistem', 'User @admin berhasil keluar sistem'); }
    #[Test] public function testPrintLog()    { $this->runLogTest('PRINT',   'nota',   'User @admin mencetak data nota'); }
    #[Test] public function testExportLog()   { $this->runLogTest('EXPORT',  'laporan','User @admin mengkespor data laporan'); }

    private function runLogTest(string $jenis, string $subjek, string $expectedAktifitas): void
    {
        $qbMock = $this->mockQueryBuilder();
        $this->expectInsertCalled($qbMock, $jenis, $subjek, $expectedAktifitas);

        $log = $this->createLog($qbMock);
        $log->aktifitas($jenis, $subjek, 'TX999');
    }
}
