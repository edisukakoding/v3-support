<?php

namespace Esikat\Helper;

use PDO;
use Exception;

/**
 * Class QueryBuilder
 * Helper untuk membangun query SQL secara dinamis menggunakan PDO.
 */
class QueryBuilder
{
    private $pdo;
    private $table;
    private $columns = '*';
    private $conditions = [];
    private $bindings = [];
    private $limit;
    private $offset;
    private $orderBy;
    private $joins = [];
    private $groupBy;
    private $having = [];

    /**
     * Konstruktor untuk inisialisasi koneksi PDO.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Reset semua properti query builder untuk query baru.
     */
    private function reset(): void
    {
        $this->table = null;
        $this->columns = '*';
        $this->conditions = [];
        $this->bindings = [];
        $this->limit = null;
        $this->offset = null;
        $this->orderBy = null;
        $this->joins = [];
        $this->groupBy = null;
        $this->having = [];
    }

    public function table(string $table, ?string $alias = null): self
    {
        $this->reset();
        $this->table = $alias ? "$table AS $alias" : $table;
        return $this;
    }

    public function select($columns = '*'): self
    {
        if (is_array($columns)) {
            $this->columns = implode(', ', $columns);
        } else {
            $this->columns = $columns;
        }
        return $this;
    }

    public function where(string|array $column, ?string $operator = null, mixed $value = null): self
    {
        if (is_array($column)) {
            foreach ($column as $col => $val) {
                $this->where($col, '=', $val);
            }
            return $this;
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = "=";
        }

        $prefix = empty($this->conditions) ? "" : "AND ";
        $this->conditions[] = "{$prefix}{$column} {$operator} ?";
        $this->bindings[] = $value;
        
        return $this;
    }

    public function orWhere(string $column, ?string $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = "=";
        }

        $prefix = empty($this->conditions) ? "" : "OR ";
        $this->conditions[] = "{$prefix}{$column} {$operator} ?";
        $this->bindings[] = $value;
        
        return $this;
    }

    public function whereNull(string $column): self
    {
        $prefix = empty($this->conditions) ? "" : "AND ";
        $this->conditions[] = "{$prefix}{$column} IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $prefix = empty($this->conditions) ? "" : "AND ";
        $this->conditions[] = "{$prefix}{$column} IS NOT NULL";
        return $this;
    }

    public function whereIn(string $column, array $values, bool $not = false): self
    {
        if (empty($values)) return $this;
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $operator = $not ? "NOT IN" : "IN";
        $prefix = empty($this->conditions) ? "" : "AND ";
        $this->conditions[] = "{$prefix}{$column} {$operator} ({$placeholders})";
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function orWhereIn(string $column, array $values, bool $not = false): self
    {
        if (empty($values)) return $this;
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $operator = $not ? "NOT IN" : "IN";
        $prefix = empty($this->conditions) ? "" : "OR ";
        $this->conditions[] = "{$prefix}{$column} {$operator} ({$placeholders})";
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereRaw(string $raw, array $bindings = []): self
    {
        $prefix = empty($this->conditions) ? "" : "AND ";
        $this->conditions[] = "{$prefix}{$raw}";
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }

    public function join(string $table, array $conditions, string $type = 'INNER', ?string $alias = null): self
    {
        $aliasClause = $alias ? " AS $alias" : '';
        $joinConditions = [];
        foreach ($conditions as $condition) {
            [$first, $operator, $second] = $condition;
            $joinConditions[] = "$first $operator $second";
        }
        $this->joins[] = "$type JOIN $table$aliasClause ON " . implode(' AND ', $joinConditions);
        return $this;
    }

    public function leftJoin(string $table, array $conditions, ?string $alias = null): self
    {
        return $this->join($table, $conditions, 'LEFT', $alias);
    }

    public function rightJoin(string $table, array $conditions, ?string $alias = null): self
    {
        return $this->join($table, $conditions, 'RIGHT', $alias);
    }

    public function groupBy($columns): self
    {
        $this->groupBy = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }

    public function having(string $condition, mixed $value): self
    {
        $this->having[] = $condition;
        $this->bindings[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "$column $direction";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Mendapatkan binding parameter yang sedang aktif.
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function toSql(): string
    {
        $sql = "SELECT {$this->columns} FROM {$this->table}";
        if ($this->joins) $sql .= ' ' . implode(' ', $this->joins);
        if ($this->conditions) $sql .= " WHERE " . implode(' ', $this->conditions);
        if ($this->groupBy) $sql .= " GROUP BY {$this->groupBy}";
        if ($this->having) $sql .= " HAVING " . implode(' AND ', $this->having);
        if ($this->orderBy) $sql .= " ORDER BY {$this->orderBy}";
        if ($this->limit) $sql .= " LIMIT {$this->limit}";
        if ($this->offset) $sql .= " OFFSET {$this->offset}";
        return $sql;
    }

    public function get(): array
    {
        $sql = $this->toSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->reset();
        return $result;
    }

    public function first(): ?array
    {
        $sql = $this->toSql() . " LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $this->reset();
        return $result;
    }

    public function find($id, string $primaryKey = 'id'): ?array
    {
        return $this->where($primaryKey, $id)->first();
    }

    public function count(string $column = '*'): int
    {
        $this->columns = "COUNT($column) AS aggregate";
        $sql = $this->toSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->reset();
        return (int) ($result['aggregate'] ?? 0);
    }

    public function insert(array $data): ?array
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute(array_values($data));
        if ($result) {
            $lastId = $this->pdo->lastInsertId();
            $tableName = explode(' ', $this->table)[0];
            return $lastId ? $this->table($tableName)->find($lastId) : null;
        }
        return null;
    }

    public function update(array $data): ?array
    {
        $setClauses = [];
        $updateValues = [];
        foreach ($data as $column => $value) {
            $setClauses[] = "$column = ?";
            $updateValues[] = $value;
        }
        $currentConditions = $this->conditions;
        $currentBindings = $this->bindings;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses);
        if ($currentConditions) $sql .= " WHERE " . implode(' ', $currentConditions);
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute(array_merge($updateValues, $currentBindings));
        if ($result) {
            $tableName = explode(' ', $this->table)[0];
            $qb = $this->table($tableName);
            $qb->conditions = $currentConditions;
            $qb->bindings = $currentBindings;
            return $qb->first();
        }
        return null;
    }

    public function delete(): bool
    {
        $sql = "DELETE FROM {$this->table}";
        if ($this->conditions) $sql .= " WHERE " . implode(' ', $this->conditions);
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($this->bindings);
        $this->reset();
        return $result;
    }

    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollBack() { return $this->pdo->rollBack(); }
    public function inTransaction() { return $this->pdo->inTransaction(); }
}