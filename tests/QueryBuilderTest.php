<?php

use PHPUnit\Framework\TestCase;
use Esikat\Helper\QueryBuilder; // Sesuaikan dengan namespace kelas Anda

class QueryBuilderTest extends TestCase
{
    private $pdo;
    private $queryBuilder;

    protected function setUp(): void
    {
        // Mock PDO connection
        $this->pdo = $this->createMock(PDO::class);
        $this->queryBuilder = new QueryBuilder($this->pdo);
    }

    public function testSelect()
    {
        $this->queryBuilder->table('users')->select(['id', 'name']);
        $this->assertEquals('SELECT id, name FROM users', $this->queryBuilder->toSql());
    }

    public function testWhere()
    {
        $this->queryBuilder->table('users')->select()->where('id', '=', 1);
        $this->assertEquals('SELECT * FROM users WHERE id = ?', $this->queryBuilder->toSql());
        $this->assertEquals([1], $this->queryBuilder->getBindings());
    }

    public function testJoin()
    {
        $this->queryBuilder->table('users')
            ->select()
            ->join('posts', [['users.id', '=', 'posts.user_id']]);
        $this->assertEquals(
            'SELECT * FROM users INNER JOIN posts ON users.id = posts.user_id',
            $this->queryBuilder->toSql()
        );
    }

    public function testLeftJoin()
    {
        $this->queryBuilder->table('users')
            ->select()
            ->leftJoin('posts', [['users.id', '=', 'posts.user_id']]);
        $this->assertEquals(
            'SELECT * FROM users LEFT JOIN posts ON users.id = posts.user_id',
            $this->queryBuilder->toSql()
        );
    }

    public function testLimit()
    {
        $this->queryBuilder->table('users')->select()->limit(10);
        $this->assertEquals('SELECT * FROM users LIMIT 10', $this->queryBuilder->toSql());
    }

    public function testOrderBy()
    {
        $this->queryBuilder->table('users')->select()->orderBy('name', 'DESC');
        $this->assertEquals('SELECT * FROM users ORDER BY name DESC', $this->queryBuilder->toSql());
    }

    public function testInsert()
    {
        $data = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $this->queryBuilder->table('users');

        // Buat mock untuk statement yang dikembalikan oleh prepare()
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())->method('execute')->with(array_values($data));

        // Pastikan prepare() menerima query yang sesuai
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('INSERT INTO users (name, email) VALUES (?, ?)')
            ->willReturn($stmtMock);

        // Eksekusi insert
        $this->queryBuilder->insert($data);
    }

    public function testUpdate()
    {
        $data = ['name' => 'Jane Doe'];
        $this->queryBuilder->table('users')->where('id', '=', 1);

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())->method('execute')->with(['Jane Doe', 1]); // Urutan benar

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('UPDATE users SET name = ? WHERE id = ?')
            ->willReturn($stmtMock);

        $this->queryBuilder->update($data);
    }

    public function testDelete()
    {
        $this->queryBuilder->table('users')->where('id', '=', 1);

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())->method('execute')->with([1]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM users WHERE id = ?')
            ->willReturn($stmtMock);

        $this->queryBuilder->delete();
    }

    public function testWhereRaw()
    {
        $this->queryBuilder->table('users')
            ->select()
            ->where('status', 'active')
            ->whereRaw('(type = ? OR type = ?)', ['admin', 'user']);

        $this->assertEquals(
            'SELECT * FROM users WHERE status = ? AND (type = ? OR type = ?)',
            $this->queryBuilder->toSql()
        );

        $this->assertEquals(['active', 'admin', 'user'], $this->queryBuilder->getBindings());
    }

        public function testWhereIn()
    {
        $this->queryBuilder->table('users')
            ->select()
            ->whereIn('id', [1, 2, 3]);

        $this->assertEquals(
            'SELECT * FROM users WHERE id IN (?, ?, ?)',
            $this->queryBuilder->toSql()
        );

        $this->assertEquals([1, 2, 3], $this->queryBuilder->getBindings());
    }

            public function testOrWhereIn()
    {
        $this->queryBuilder->table('users')
            ->select()
            ->where('status', '=', 'active')
            ->orWhereIn('id', [10, 20, 30]);

        $this->assertEquals(
            'SELECT * FROM users WHERE status = ? OR id IN (?, ?, ?)',
            $this->queryBuilder->toSql()
        );

        $this->assertEquals(['active', 10, 20, 30], $this->queryBuilder->getBindings());
    }

            public function testCount()
    {
        $this->queryBuilder->table('users')->where('status', '=', 'active');

        // Mock PDOStatement
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(['aggregate' => 7]);

        // Expect query prepare() terima query COUNT
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT COUNT(*) AS aggregate FROM users WHERE status = ?')
            ->willReturn($stmtMock);

        // Eksekusi count()
        $result = $this->queryBuilder->count();

        $this->assertEquals(7, $result);
    }


}
