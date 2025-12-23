<?php

namespace Esikat\Helper;

use PDO;
use PDOException;

class DataHandler
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Menangani request DataTable dan mengembalikan data dalam format JSON.
     *
     * @param string $table Nama tabel database.
     * @param array $columns Daftar kolom yang akan ditampilkan.
     * @param string $primaryKey Kunci utama tabel (default: 'id').
     * @param string $join Query join tambahan (default: '').
     * @param string $explicitWhere Kondisi tambahan yang ditetapkan secara eksplisit.
     *
     * @return array Array data sesuai dengan format DataTable.
     */
    public function datatable(
        string $table,
        array $columns,
        string $primaryKey = 'id',
        string $join = '',
        string $explicitWhere = '',
        string $groupBy = '',
        string $having = ''
    ) {
        $draw = $_GET['draw'] ?? 1;
        $start = $_GET['start'] ?? 0;
        $length = $_GET['length'] ?? -1;
        $searchValue = $_GET['search']['value'] ?? '';

        // ==== 1. Logic Multi-Column Order (BARU) ====
        $orderQueryParts = [];
        
        if (!empty($_GET['order']) && is_array($_GET['order'])) {
            foreach ($_GET['order'] as $order) {
                $columnIndex = (int)$order['column'];
                $columnDir = strtoupper($order['dir']) === 'DESC' ? 'DESC' : 'ASC';

                // Pastikan index kolom valid
                if (isset($columns[$columnIndex])) {
                    $rawColumn = $columns[$columnIndex];
                    
                    // Bersihkan 'AS alias' agar query ORDER BY valid
                    // Contoh: "users.name AS nama_lengkap" menjadi "users.name"
                    // Atau jika database mendukung alias di ORDER BY, Anda bisa pakai aliasnya.
                    // Di sini kita pakai kolom aslinya agar aman.
                    $columnName = preg_replace('/\s+AS\s+\w+$/i', '', $rawColumn);
                    
                    $orderQueryParts[] = "$columnName $columnDir";
                }
            }
        }

        // Default order jika user tidak melakukan sorting
        if (empty($orderQueryParts)) {
            $orderQueryParts[] = "$primaryKey ASC";
        }

        $orderClause = " ORDER BY " . implode(', ', $orderQueryParts);
        // ============================================

        $whereConditions = [];
        $params = [];

        if (!empty($_GET['where']) && is_array($_GET['where'])) {
            foreach ($_GET['where'] as $key => $value) {
                $paramKey = str_replace('.', '_', $key);
                $whereConditions[] = "$key = :$paramKey";
                $params[":$paramKey"] = $value;
            }
        }

        if (!empty($searchValue)) {
            $searchConditions = [];
            foreach ($columns as $col) {
                $colOnly = preg_replace('/\s+AS\s+\w+$/i', '', $col);
                if (stripos($colOnly, '(') === false) {
                    $searchConditions[] = "$colOnly LIKE :search";
                }
            }

            $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            $params[':search'] = "%$searchValue%";
        }

        if (!empty($explicitWhere)) {
            $whereConditions[] = "($explicitWhere)";
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // ==== Perhitungan recordsTotal dan recordsFiltered ====
        if (!empty($groupBy)) {
            $baseSQL = "SELECT " . implode(", ", $columns) . " FROM $table $join $whereClause GROUP BY $groupBy";
            if (!empty($having)) {
                $baseSQL .= " HAVING $having";
            }
            $countSQL = "SELECT COUNT(*) FROM ($baseSQL) AS grouped";
            $stmt = $this->pdo->prepare($countSQL);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $filteredRecords = $stmt->fetchColumn();
            $totalRecords = $filteredRecords; 
        } else {
            $sql = "SELECT COUNT($primaryKey) FROM $table $join";
            $totalRecords = $this->pdo->query($sql)->fetchColumn();

            $sql = "SELECT COUNT($primaryKey) FROM $table $join $whereClause";
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $filteredRecords = $stmt->fetchColumn();
        }

        // ==== Ambil data utama ====
        $sql = "SELECT " . implode(", ", $columns) . " FROM $table $join $whereClause";
        if (!empty($groupBy)) $sql .= " GROUP BY $groupBy";
        if (!empty($having)) $sql .= " HAVING $having";
        
        // Masukkan Order Clause yang sudah dibuat di atas
        $sql .= $orderClause;

        if ($length != -1) {
            $sql .= " LIMIT :start, :length";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($length != -1) {
            $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
            $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'draw' => (int)$draw,
            'recordsTotal' => (int)$totalRecords,
            'recordsFiltered' => (int)$filteredRecords,
            'data' => $data
        ];
    }

    /**
     * Mengambil data untuk Select2 dalam format JSON.
     *
     * @param string $table Nama tabel database.
     * @param string $idColumn Nama kolom yang digunakan sebagai ID.
     * @param string $textColumn Nama kolom yang digunakan sebagai teks.
     * @param string $extraCondition Kondisi tambahan untuk filter data.
     * @param array $joins Daftar tabel join dengan format [['type' => 'INNER', 'table' => 'other_table', 'on' => 'table.id = other_table.table_id']].
     *
     * @return array Data dalam format JSON untuk Select2.
     */
    public function select2(string $table, string $idColumn, string $textColumn, string $extraCondition = '', array $joins = [])
    {
        try {
            $search = $_GET['q'] ?? '';
            $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

            $joinSQL = "";
            if (!empty($joins)) {
                foreach ($joins as $join) {
                    $joinSQL .= " {$join['type']} JOIN {$join['table']} ON {$join['on']}";
                }
            }

            $whereSQL = "WHERE $textColumn LIKE :search";
            if (!empty($extraCondition)) {
                $whereSQL .= " AND $extraCondition";
            }

            $sql = "SELECT $idColumn AS id, $textColumn AS text FROM $table $joinSQL $whereSQL LIMIT :limit";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ["results" => $data];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
