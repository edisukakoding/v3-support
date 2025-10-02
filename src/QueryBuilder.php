<?php

namespace Esikat\Helper;

use PDO;

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
     *
     * @param PDO $pdo Koneksi PDO untuk mengakses database.
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

    /**
     * Menentukan tabel yang akan digunakan dalam query.
     *
     * @param string $table Nama tabel.
     * @param string|null $alias Alias tabel (opsional).
     * 
     * @return self Instance dari QueryBuilder.
     */
    public function table(string $table, ?string $alias = null): self
    {
        $this->reset();
        $this->table = $alias ? "$table AS $alias" : $table;
        return $this;
    }

    /**
     * Menentukan kolom yang akan diambil dalam query.
     *
     * @param mixed $columns Kolom yang akan dipilih (default: semua kolom).
     * 
     * @return self Instance dari QueryBuilder.
     */
    public function select($columns = '*'): self
    {
        if (is_array($columns)) {
            $this->columns = implode(', ', $columns);
        } else {
            $this->columns = $columns;
        }
        return $this;
    }

    /**
     * Menambahkan kondisi WHERE pada query.
     *
     * @param string|array $column Nama kolom atau array kondisi.
     * @param string|null $operator Operator perbandingan (e.g., '=', '<', '>').
     * @param mixed $value Nilai yang akan dibandingkan (jika $column adalah string).
     * 
     * @return self Instance dari QueryBuilder.
     */
    public function where(string|array $column, ?string $operator = null, mixed $value = null): self
    {
        if (is_array($column)) {
            foreach ($column as $col => $val) {
                $this->conditions[] = (empty($this->conditions) ? "" : "AND ") . "$col = ?";
                $this->bindings[] = $val;
            }
        } else {
            if ($value === null) {
                $value = $operator;
                $operator = "=";
            }
            $this->conditions[] = (empty($this->conditions) ? "" : "AND ") . "$column $operator ?";
            $this->bindings[] = $value;
        }
        return $this;
    }



    /**
     * Menambahkan kondisi OR WHERE pada query.
     *
     * @param string|array $column Nama kolom atau array kondisi.
     * @param string|null $operator Operator perbandingan (e.g., '=', '<', '>').
     * @param mixed $value Nilai yang akan dibandingkan.
     * 
     * @return self Instance dari QueryBuilder.
     */
    public function orWhere(string|array $column, ?string $operator = null, mixed $value = null): self
    {
        if (is_array($column)) {
            foreach ($column as $col => $val) {
                $this->conditions[] = (empty($this->conditions) ? "" : "OR ") . "$col = ?";
                $this->bindings[] = $val;
            }
        } else {
            if ($value === null) {
                $value = $operator;
                $operator = "=";
            }
            $this->conditions[] = (empty($this->conditions) ? "" : "OR ") . "$column $operator ?";
            $this->bindings[] = $value;
        }
        return $this;
    }

    /**
     * Menambahkan kondisi OR WHERE pada query.
     *
     * @param string    $raw Raw Query.
     * @param array     $binding value (e.g., '=', '<', '>').
     * 
     * @return self Instance dari QueryBuilder.
     */
    public function whereRaw(string $raw, array $bindings = []): self
    {
        $this->conditions[] = (empty($this->conditions) ? "" : "AND ") . $raw;
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }



    /**
     * Menambahkan join (INNER, LEFT, RIGHT) pada query.
     *
     * @param string $table Nama tabel yang akan di-join.
     * @param array $conditions Kondisi join yang akan digunakan.
     * @param string $type Jenis join (default: 'INNER').
     * @param string|null $alias Alias untuk tabel yang di-join (opsional).
     * 
     * @return self Instance dari QueryBuilder.
     */
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

    /**
     * Menambahkan LEFT JOIN pada query.
     *
     * @param string $table Nama tabel yang akan di-join.
     * @param array $conditions Kondisi join yang akan digunakan.
     * @param string|null $alias Alias untuk tabel yang di-join (opsional).
     * 
     * @return self Instance dari QueryBuilder.
     */
    public function leftJoin(string $table, array $conditions, ?string $alias = null): self
    {
        if ($alias) {
            $table = "$table $alias";
        }
        return $this->join($table, $conditions, 'LEFT');
    }

    /**
     * Menambahkan RIGHT JOIN pada query.
     *
     * @param string $table Nama tabel yang akan di-join.
     * @param array $conditions Kondisi join yang akan digunakan.
     * @param string|null $alias Alias untuk tabel yang di-join (opsional).
     * 
     * @return self Instance dari QueryBuilder.
     */
    public function rightJoin(string $table, array $conditions, ?string $alias = null): self
    {
        if ($alias) {
            $table = "$table $alias";
        }
        return $this->join($table, $conditions, 'RIGHT');
    }

    /**
     * Menambahkan limit pada query.
     *
     * @param int $limit Jumlah data yang akan dibatasi.
     * 
     * @return self Instance dari QueryBuilder.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Menambahkan offset pada query.
     *
     * @param int $offset Posisi data mulai diambil.
     * 
     * @return self Instance dari QueryBuilder.
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }
    /**
     * Menambahkan GROUP BY pada query.
     *
     * @param string|array $columns Kolom yang akan digunakan untuk pengelompokan.
     * 
     * @return self Instance dari QueryBuilder.
     */
    public function groupBy(string|array $columns): self
    {
        if (is_array($columns)) {
            $this->groupBy = implode(', ', $columns);
        } else {
            $this->groupBy = $columns;
        }
        return $this;
    }

    /**
     * Menambahkan kondisi HAVING pada query.
     *
     * @param string $condition Kondisi HAVING (misalnya: "COUNT(id) > ?").
     * @param mixed $value Nilai parameter untuk kondisi.
     * 
     * @return self Instance dari QueryBuilder.
     */
    public function having(string $condition, mixed $value): self
    {
        $this->having[] = $condition;
        $this->bindings[] = $value;
        return $this;
    }


    /**
     * Menambahkan pengurutan data pada query.
     *
     * @param string $column Kolom yang akan diurutkan.
     * @param string $direction Arah pengurutan ('ASC' atau 'DESC').
     * 
     * @return self Instance dari QueryBuilder.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "$column $direction";
        return $this;
    }

    /**
     * Menjalankan query dan mengembalikan hasil dalam bentuk array.
     *
     * @return array Hasil query dalam bentuk array asosiatif.
     */
    public function get(): array
    {
        $sql = $this->toSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->reset();
        return $result;
    }

    /**
     * Menjalankan query dan mengembalikan hasil pertama.
     *
     * @return array|null Hasil query pertama atau null jika tidak ada.
     */
    public function first(): ?array
    {
        $sql = $this->toSql() . ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $this->reset();
        return $result;
    }

    /**
     * Menghasilkan query SQL dalam bentuk string.
     *
     * @return string Query SQL lengkap.
     */
    public function toSql(): string
    {
        $sql = "SELECT {$this->columns} FROM {$this->table}";

        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if ($this->conditions) {
            $conditions = implode(' ', $this->conditions);
            $conditions = preg_replace('/\b(AND|OR)\s+(AND|OR)\b/', '$2', $conditions); // Hapus "AND AND" atau "OR AND"
            $sql .= " WHERE " . $conditions;
        }

        if ($this->groupBy) {
            $sql .= " GROUP BY {$this->groupBy}";
        }

        if ($this->having) {
            $sql .= " HAVING " . implode(' AND ', $this->having);
        }

        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }
        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
        }
        if ($this->offset) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }



    /**
     * Mendapatkan semua binding parameter untuk query.
     *
     * @return array Daftar parameter binding.
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Menyisipkan data baru ke dalam tabel.
     *
     * @param array $data Data yang akan disisipkan (kolom => nilai).
     * 
     * @return bool Status eksekusi query.
     */
    public function insert(array $data): ?array
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute(array_values($data));

        if ($result) {
            $table = explode(' ', $this->table)[0]; // Buang alias
            $this->reset();
            $this->table($table);

            // Coba ambil kembali data berdasarkan data yang disisipkan
            foreach ($data as $key => $value) {
                $this->where($key, $value);
            }

            return $this->first();
        }

        $this->reset();
        return null;
    }


    /**
     * Memperbarui data di dalam tabel.
     *
     * @param array $data Data yang akan diperbarui (kolom => nilai).
     * 
     * @return bool Status eksekusi query.
     */
    public function update(array $data): ?array
    {
        $setClauses = [];
        $updateBindings = [];

        foreach ($data as $column => $value) {
            $setClauses[] = "$column = ?";
            $updateBindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses);

        if ($this->conditions) {
            $sql .= " WHERE " . implode(' ', $this->conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $bindings = array_merge($updateBindings, $this->bindings);
        $result = $stmt->execute($bindings);

        if ($result) {
            // Simpan table dan kondisi sebelum reset
            $table = $this->table;
            $conditions = $this->conditions;
            $bindings = $this->bindings;

            $this->reset();
            $this->table($table); // pakai kembali nama tabel
            foreach ($conditions as $cond) {
                preg_match('/(\w+)\s*=\s*\?/', $cond, $match);
                if (isset($match[1])) {
                    $this->where($match[1], array_shift($bindings));
                }
            }

            return $this->first();
        }

        $this->reset();
        return null;
    }


    /**
     * Menghapus data dari tabel.
     *
     * @return bool Status eksekusi query.
     */
    public function delete(): bool
    {
        $sql = "DELETE FROM {$this->table}";

        if ($this->conditions) {
            $conditions = implode(' ', $this->conditions);
            $conditions = preg_replace('/\b(AND|OR)\s+(AND|OR)\b/', '$2', $conditions); // Fix format WHERE
            $sql .= " WHERE " . $conditions;
        }

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($this->bindings);
        $this->reset();
        return $result;
    }
}
