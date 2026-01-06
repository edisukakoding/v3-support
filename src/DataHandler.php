<?php

namespace Esikat\Helper;

use PDO;
use PDOException;

class DataHandler
{
    private PDO $pdo;

    /**
     * Daftar kolom yang harus diperlakukan sebagai DATETIME saat ORDER BY
     * Contoh: ['orders.created_at', 'users.last_login']
     */
    private array $datetimeColumns = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Optional: set kolom datetime
     */
    public function setDatetimeColumns(array $columns): void
    {
        $this->datetimeColumns = $columns;
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
        $draw   = $_GET['draw'] ?? 1;
        $start  = $_GET['start'] ?? 0;
        $length = $_GET['length'] ?? -1;
        $searchValue = $_GET['search']['value'] ?? '';

        /* ===================== ORDER BY (MULTI COLUMN + DATETIME FIX) ===================== */
        $orderQueryParts = [];

        if (!empty($_GET['order']) && is_array($_GET['order'])) {
            foreach ($_GET['order'] as $order) {
                $columnIndex = (int)$order['column'];
                $dir = strtoupper($order['dir']) === 'DESC' ? 'DESC' : 'ASC';

                if (!isset($columns[$columnIndex])) {
                    continue;
                }

                // Hilangkan alias AS
                $rawColumn = $columns[$columnIndex];
                $columnName = preg_replace('/\s+AS\s+\w+$/i', '', $rawColumn);

                // DATETIME FIX
                if (in_array($columnName, $this->datetimeColumns, true)) {
                    $orderQueryParts[] = "CAST($columnName AS DATETIME) $dir";
                } else {
                    $orderQueryParts[] = "$columnName $dir";
                }
            }
        }

        if (empty($orderQueryParts)) {
            $orderQueryParts[] = "$primaryKey ASC";
        }

        $orderClause = ' ORDER BY ' . implode(', ', $orderQueryParts);
        /* ================================================================================ */

        /* ===================== WHERE & SEARCH ===================== */
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
            $searchParts = [];
            foreach ($columns as $col) {
                $colOnly = preg_replace('/\s+AS\s+\w+$/i', '', $col);
                if (stripos($colOnly, '(') === false) {
                    $searchParts[] = "$colOnly LIKE :search";
                }
            }
            if ($searchParts) {
                $whereConditions[] = '(' . implode(' OR ', $searchParts) . ')';
                $params[':search'] = "%$searchValue%";
            }
        }

        if (!empty($explicitWhere)) {
            $whereConditions[] = "($explicitWhere)";
        }

        $whereClause = $whereConditions
            ? ' WHERE ' . implode(' AND ', $whereConditions)
            : '';
        /* ========================================================== */

        /* ===================== RECORD COUNT ===================== */
        if ($groupBy) {
            $baseSQL = "SELECT " . implode(', ', $columns) . "
                        FROM $table $join $whereClause
                        GROUP BY $groupBy";

            if ($having) {
                $baseSQL .= " HAVING $having";
            }

            $countSQL = "SELECT COUNT(*) FROM ($baseSQL) AS t";
            $stmt = $this->pdo->prepare($countSQL);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $recordsFiltered = $stmt->fetchColumn();
            $recordsTotal = $recordsFiltered;
        } else {
            $recordsTotal = $this->pdo
                ->query("SELECT COUNT($primaryKey) FROM $table $join")
                ->fetchColumn();

            $stmt = $this->pdo->prepare(
                "SELECT COUNT($primaryKey) FROM $table $join $whereClause"
            );
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $recordsFiltered = $stmt->fetchColumn();
        }
        /* ======================================================== */

        /* ===================== MAIN DATA QUERY ===================== */
        $sql = "SELECT " . implode(', ', $columns) . "
                FROM $table $join
                $whereClause";

        if ($groupBy) {
            $sql .= " GROUP BY $groupBy";
        }

        if ($having) {
            $sql .= " HAVING $having";
        }

        $sql .= $orderClause;

        if ($length != -1) {
            $sql .= " LIMIT :start, :length";
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }

        if ($length != -1) {
            $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
            $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        }

        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        /* ========================================================== */

        return [
            'draw'            => (int)$draw,
            'recordsTotal'    => (int)$recordsTotal,
            'recordsFiltered' => (int)$recordsFiltered,
            'data'            => $data
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
