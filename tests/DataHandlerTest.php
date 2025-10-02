<?php

use PHPUnit\Framework\TestCase;
use Esikat\Helper\DataHandler;

class DataHandlerTest extends TestCase
{
    private $pdo;
    private $dataHandler;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->dataHandler = new DataHandler($this->pdo);
    }

    public function testHandleDatatableRequest()
    {
        $_GET = [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'test'],
            'order' => [['column' => 0, 'dir' => 'asc']]
        ];

        $columns = ['id', 'name', 'email'];

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('fetchColumn')->willReturn(10);
        $stmtMock->method('fetchAll')->willReturn([]);

        $this->pdo->method('prepare')->willReturn($stmtMock);
        $this->pdo->method('query')->willReturn($stmtMock);

        $result = $this->dataHandler->datatable('users', $columns, 'id');

        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('recordsTotal', $result);
        $this->assertArrayHasKey('recordsFiltered', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testGetSelect2Data()
    {
        $_GET = ['q' => 'test', 'limit' => 5];

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('fetchAll')->willReturn([
            ['id' => 1, 'text' => 'test user']
        ]);

        $this->pdo->method('prepare')->willReturn($stmtMock);

        $result = $this->dataHandler->select2('users', 'id', 'name');

        $this->assertArrayHasKey('results', $result);
        $this->assertNotEmpty($result['results']);
    }
}
