<?php

use PHPUnit\Framework\TestCase;
use Esikat\Helper\QueryBuilder;

/**
 * Unit Test Lengkap untuk QueryBuilder Esikat
 */
class QueryBuilderTest extends TestCase
{
    private $pdo;
    private $queryBuilder;

    protected function setUp(): void
    {
        // Mock koneksi PDO
        $this->pdo = $this->createMock(PDO::class);
        $this->queryBuilder = new QueryBuilder($this->pdo);
    }

    /**
     * Skenario 1: Dasar SELECT, Table, dan Alias
     */
    public function testSelectAndTable()
    {
        $this->queryBuilder->table('rusaha', 'u')->select(['kdusaha', 'nmusaha']);
        $this->assertEquals('SELECT kdusaha, nmusaha FROM rusaha AS u', $this->queryBuilder->toSql());
    }

    /**
     * Skenario 2: Kondisi WHERE Kompleks (Standard, Array, Operator)
     */
    public function testWhereScenarios()
    {
        // Skenario: Operator default (=)
        $this->queryBuilder->table('ruser')->where('username', 'kemi');
        $this->assertEquals('SELECT * FROM ruser WHERE username = ?', $this->queryBuilder->toSql());
        $this->assertEquals(['kemi'], $this->queryBuilder->getBindings());

        // Skenario: Operator spesifik (!=)
        $this->queryBuilder->table('ruser')->where('iduser', '!=', 5);
        $this->assertEquals('SELECT * FROM ruser WHERE iduser != ?', $this->queryBuilder->toSql());

        // Skenario: Multiple Where (AND)
        $this->queryBuilder->table('ruser')->where('status', 1)->where('role', 'admin');
        $this->assertEquals('SELECT * FROM ruser WHERE status = ? AND role = ?', $this->queryBuilder->toSql());
    }

    /**
     * Skenario 3: Kondisi NULL dan NOT NULL (Penting untuk MFA tglguna)
     */
    public function testNullConditions()
    {
        $this->queryBuilder->table('ruser_backup_mfa')
            ->where('iduser', 1)
            ->whereNull('tglguna')
            ->whereNotNull('tglentri');

        $this->assertEquals(
            'SELECT * FROM ruser_backup_mfa WHERE iduser = ? AND tglguna IS NULL AND tglentri IS NOT NULL', 
            $this->queryBuilder->toSql()
        );
    }

    /**
     * Skenario 4: WHERE IN dan OR WHERE IN
     */
    public function testWhereInScenarios()
    {
        $this->queryBuilder->table('ruser')
            ->whereIn('role', ['admin', 'manager'])
            ->orWhereIn('iduser', [10, 20]);

        $this->assertEquals(
            'SELECT * FROM ruser WHERE role IN (?, ?) OR iduser IN (?, ?)', 
            $this->queryBuilder->toSql()
        );
        $this->assertEquals(['admin', 'manager', 10, 20], $this->queryBuilder->getBindings());
    }

    /**
     * Skenario 5: Berbagai Jenis JOIN (Inner, Left, Right)
     */
    public function testJoinScenarios()
    {
        $this->queryBuilder->table('ruser', 'a')
            ->leftJoin('rusaha', [['a.kdusaha', '=', 'rusaha.kdusaha']], 'b')
            ->join('rplatform', [['a.kdplatform', '=', 'rplatform.kdplatform']], 'INNER', 'p');

        $sql = $this->queryBuilder->toSql();
        $this->assertStringContainsString('LEFT JOIN rusaha AS b ON a.kdusaha = rusaha.kdusaha', $sql);
        $this->assertStringContainsString('INNER JOIN rplatform AS p ON a.kdplatform = rplatform.kdplatform', $sql);
    }

    /**
     * Skenario 6: Agregasi, GroupBy, dan Having
     */
    public function testAggregationScenarios()
    {
        $this->queryBuilder->table('tlogaktifitas')
            ->select(['iduser', 'COUNT(*) as total'])
            ->groupBy('iduser')
            ->having('total > ?', 5);

        $this->assertEquals(
            'SELECT iduser, COUNT(*) as total FROM tlogaktifitas GROUP BY iduser HAVING total > ?', 
            $this->queryBuilder->toSql()
        );
    }

    /**
     * Skenario 7: INSERT dengan Auto-Fetch (Mendukung lastInsertId)
     */
    public function testInsertWithAutoFetch()
    {
        $data = ['username' => 'kemi'];
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(['id' => 99, 'username' => 'kemi']);

        $this->pdo->method('prepare')->willReturn($stmtMock);
        $this->pdo->method('lastInsertId')->willReturn("99");

        $result = $this->queryBuilder->table('ruser')->insert($data);
        
        $this->assertIsArray($result);
        $this->assertEquals(99, $result['id']);
    }

    /**
     * Skenario 7b: INSERT pada tabel TANPA ID (Misal tabel log atau custom PK)
     * Query Builder tidak boleh error, dan harus mengembalikan true.
     */
    public function testInsertWithoutAutoId()
    {
        $data = ['kdusaha' => 'ABC', 'nmusaha' => 'Usaha'];
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturn($stmtMock);
        // Simulasi lastInsertId mengembalikan 0 atau false
        $this->pdo->method('lastInsertId')->willReturn("0");

        $result = $this->queryBuilder->table('rusaha')->insert($data);
        
        // Karena tidak ada ID untuk di-fetch, hasilnya harus boolean true
        $this->assertTrue($result);
    }

    /**
     * Skenario 8: UPDATE dengan Kondisi
     */
    public function testUpdateWithConditions()
    {
        $data = ['mfa_enabled' => 1];
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(['iduser' => 1, 'mfa_enabled' => 1]);

        $this->pdo->method('prepare')->willReturn($stmtMock);

        $result = $this->queryBuilder->table('ruser')->where('iduser', 1)->update($data);
        $this->assertEquals(1, $result['mfa_enabled']);
    }

    /**
     * Skenario 9: DELETE
     */
    public function testDeleteScenario()
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())->method('execute')->willReturn(true);
        $this->pdo->method('prepare')->willReturn($stmtMock);

        $success = $this->queryBuilder->table('ruser_backup_mfa')->where('iduser', 1)->delete();
        $this->assertTrue($success);
    }

    /**
     * Skenario 10: Shortcut find() dan value()
     */
    public function testShortcuts()
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(['iduser' => 10, 'username' => 'mini']);

        $this->pdo->method('prepare')->willReturn($stmtMock);

        // Cari menggunakan 'iduser' secara eksplisit
        $user = $this->queryBuilder->table('ruser')->find(10, 'iduser');
        $this->assertEquals('mini', $user['username']);
    }

    /**
     * Skenario 11: Manajemen Transaksi
     */
    public function testTransactions()
    {
        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->once())->method('commit')->willReturn(true);
        $this->pdo->expects($this->once())->method('inTransaction')->willReturn(true);

        $this->assertTrue($this->queryBuilder->beginTransaction());
        $this->assertTrue($this->queryBuilder->inTransaction());
        $this->assertTrue($this->queryBuilder->commit());
    }
}