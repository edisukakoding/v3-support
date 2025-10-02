<?php

namespace Esikat\Helper;

use Exception;
use Esikat\Helper\QueryBuilder;

class Log
{
    public const INSERT     = 'INSERT';
    public const UPDATE     = 'UPDATE';
    public const DELETE     = 'DELETE';
    public const APPROVE    = 'APPROVE';
    public const REJECT     = 'REJECT';
    public const LOGIN      = 'LOGIN';
    public const LOGOUT     = 'LOGOUT';
    public const PRINT      = 'PRINT';
    public const EXPORT     = 'EXPORT';

    private QueryBuilder    $queryBuilder;
    private string          $tabel;
    private int             $iduser;
    private string          $username;
    private int             $kdusaha;

    /**
     * Konstruktor kelas Log.
     *
     * @param QueryBuilder  $queryBuilder Dependency QueryBuilder untuk query database.
     * @param string        $tabel Nama tabel log.
     * @param int           $iduser ID user yang melakukan aksi.
     * @param string        $username Nama user.
     * @param int           $kdusaha Kode usaha.
     */
    public function __construct(QueryBuilder $queryBuilder, string $tabel, int $iduser, string $username, int $kdusaha)
    {
        $this->tabel        = $tabel;
        $this->iduser       = $iduser;
        $this->username     = $username;
        $this->kdusaha      = $kdusaha;
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Mencatat log aktifitas user.
     *
     * @param string        $jenis Jenis aksi (INSERT, UPDATE, dsb).
     * @param string        $subjek Subjek data (misal: 'produk').
     * @param string        $kdtransaksi Kode transaksi atau referensi.
     * @param int|null      $row_id ID baris data yang diubah (opsional).
     * @param string|null   $data informasi data dalam bentuk string (opsional).
     */
    public function aktifitas(string $jenis, string $subjek, string $kdtransaksi, ?int $row_id = null, ?string $data = null)
    {
        try {
            $data = [
                'jenis'         => $jenis,
                'subjek'        => $subjek,
                'kdtransaksi'   => $kdtransaksi,
                'iduser'        => $this->iduser,
                'kdusaha'       => $this->kdusaha,
                'row_id'        => $row_id,
                'data'          => $data,
                'aktifitas'     => match ($jenis) {
                    self::INSERT    => "User @{$this->username} menambahkan data $subjek",
                    self::UPDATE    => "User @{$this->username} mengubah data $subjek",
                    self::DELETE    => "User @{$this->username} menghapus data $subjek",
                    self::APPROVE   => "User @{$this->username} menyetujui data $subjek",
                    self::REJECT    => "User @{$this->username} menolak data $subjek",
                    self::LOGIN     => "User @{$this->username} berhasil masuk $subjek",
                    self::LOGOUT    => "User @{$this->username} berhasil keluar $subjek",
                    self::PRINT     => "User @{$this->username} mencetak data $subjek",
                    self::EXPORT    => "User @{$this->username} mengkespor data $subjek",
                    default         => "User @{$this->username} melakukan aksi $jenis pada $subjek"
                }
            ];

            $this->queryBuilder->table($this->tabel)->insert($data);
        } catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }
}
