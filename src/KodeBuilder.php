<?php

namespace Esikat\Helper;

use RuntimeException;
use Esikat\Helper\QueryBuilder;

class KodeBuilder
{
    private QueryBuilder $queryBuilder;
    private int $kdusaha;
    private string $tanggal;

    public function __construct(QueryBuilder $queryBuilder, int $kdusaha)
    {
        $this->kdusaha = $kdusaha;
        $this->queryBuilder = $queryBuilder;
        $this->tanggal = date('ym');
    }

    /**
     * Mengambil data usaha berdasarkan kode usaha yang diberikan saat inisialisasi.
     *
     * @return array Data usaha yang ditemukan.
     * @throws RuntimeException Jika singkatan usaha tidak ditemukan.
     */
    private function getUsaha(): array
    {
        $usaha = $this->queryBuilder->table('rusaha')->where('kdusaha', '=', $this->kdusaha)->first();
        if (empty($usaha['singkatan'])) {
            throw new RuntimeException("Singkatan usaha tidak ditemukan.");
        }
        return $usaha;
    }

    /**
     * Mengambil data transaksi berdasarkan prefix dan singkatan.
     *
     * @param string $prefix Prefix transaksi.
     * @param string $singkatan Singkatan usaha.
     * @return array|null Data transaksi jika ditemukan, null jika tidak.
     */
    private function getTransaksi(string $prefix, string $singkatan): ?array
    {
        return $this->queryBuilder
            ->table('rnotransaksi')
            ->where('kdusaha', '=', $this->kdusaha)
            ->where('tanggal', '=', $this->tanggal)
            ->where('prefix', '=', $prefix)
            ->where('singkatan', '=', $singkatan)
            ->first();
    }

    /**
     * Menghasilkan string nomor transaksi berdasarkan panjang dan angka terakhir.
     *
     * @param int $noakhir Nomor terakhir.
     * @param int $panjang Jumlah digit yang diinginkan.
     * @return string Nomor transaksi yang telah diformat.
     */
    private function generateNomor(int $noakhir, int $panjang): string
    {
        return str_pad($noakhir, $panjang, '0', STR_PAD_LEFT);
    }

    /**
     * Memperbarui data nomor transaksi di tabel `rnotransaksi`.
     *
     * @param string $prefix Prefix transaksi.
     * @param string $singkatan Singkatan usaha.
     * @param string $noakhir Nomor akhir yang akan diperbarui.
     * @return void
     */
    private function updateTransaksi(string $prefix, string $singkatan, string $noakhir): void
    {
        $this->queryBuilder->table('rnotransaksi')
            ->where('kdusaha', '=', $this->kdusaha)
            ->where('tanggal', '=', $this->tanggal)
            ->where('prefix', '=', $prefix)
            ->where('singkatan', '=', $singkatan)
            ->update(['noakhir' => $noakhir]);
    }

    /**
     * Menyisipkan data transaksi baru ke tabel `rnotransaksi`.
     *
     * @param string $prefix Prefix transaksi.
     * @param string $singkatan Singkatan usaha.
     * @param string $noakhir Nomor akhir untuk transaksi baru.
     * @return void
     */
    private function insertTransaksi(string $prefix, string $singkatan, string $noakhir): void
    {
        $this->queryBuilder->table('rnotransaksi')->insert([
            'kdusaha' => $this->kdusaha,
            'tanggal' => $this->tanggal,
            'prefix' => $prefix,
            'singkatan' => $singkatan,
            'noakhir' => $noakhir
        ]);
    }

    /**
     * Menampilkan pratinjau (preview) nomor transaksi berikutnya tanpa menyimpannya.
     *
     * @param string $prefix Prefix transaksi.
     * @return string Nomor transaksi yang dipratinjau.
     */
    public function previewNoTransaksi(string $prefix): string
    {
        $usaha = $this->getUsaha();
        $transaksi = $this->getTransaksi($prefix, $usaha['singkatan']);

        $noakhir = $transaksi ? intval($transaksi['noakhir']) + 1 : 1;
        return "{$prefix}/" . $this->tanggal . "/{$usaha['singkatan']}/" . $this->generateNomor($noakhir, 4);
    }

    /**
     * Membuat nomor transaksi baru dan menyimpan perubahan ke database.
     *
     * @param string $prefix Prefix transaksi.
     * @return string Nomor transaksi yang telah dibuat.
     */
    public function buatNoTransaksi(string $prefix): string
    {
        $usaha = $this->getUsaha();
        $transaksi = $this->getTransaksi($prefix, $usaha['singkatan']);

        $noakhir = $transaksi ? intval($transaksi['noakhir']) + 1 : 1;
        $noakhirStr = $this->generateNomor($noakhir, 4);

        if ($transaksi) {
            $this->updateTransaksi($prefix, $usaha['singkatan'], $noakhirStr);
        } else {
            $this->insertTransaksi($prefix, $usaha['singkatan'], $noakhirStr);
        }

        return "{$prefix}/" . $this->tanggal . "/{$usaha['singkatan']}/{$noakhirStr}";
    }

        /**
     * Mengambil data kategori barang berdasarkan prefix kategori.
     *
     * @param string $prefixKategori Prefix kategori barang (misal: FAB, ACC).
     * @return array|null Data kategori jika ditemukan, null jika tidak.
     */
    private function getKodeBarang(string $prefixKategori): ?array
    {
        return $this->queryBuilder
            ->table('rkatbrg')
            ->where('kdusaha', '=', $this->kdusaha)
            ->where('kdkatbrg', '=', $prefixKategori)
            ->first();
    }

    /**
     * Menyisipkan kode barang baru ke tabel `rkatbrg`.
     *
     * @param string $prefixKategori Prefix kategori barang.
     * @param string $noakhir Nomor akhir baru.
     * @return void
     */
    private function insertKodeBarang(string $prefixKategori, string $noakhir): void
    {
        $this->queryBuilder->table('rkatbrg')->insert([
            'kdusaha' => $this->kdusaha,
            'kdkatbrg' => $prefixKategori,
            'noakhir' => $noakhir
        ]);
    }

    /**
     * Memperbarui kode barang pada tabel `rkatbrg`.
     *
     * @param string $prefixKategori Prefix kategori barang.
     * @param string $noakhir Nomor akhir baru.
     * @return void
     */
    private function updateKodeBarang(string $prefixKategori, string $noakhir): void
    {
        $this->queryBuilder->table('rkatbrg')
            ->where('kdusaha', '=', $this->kdusaha)
            ->where('kdkatbrg', '=', $prefixKategori)
            ->update(['noakhir' => $noakhir]);
    }

    /**
     * Menampilkan preview kode barang berikutnya tanpa menyimpan ke database.
     *
     * @param string $prefixKategori Prefix kategori barang.
     * @return string Kode barang yang dipratinjau.
     */
    public function previewKodeBarang(string $prefixKategori): string
    {
        $data = $this->getKodeBarang($prefixKategori);
        $noakhir = $data ? intval($data['noakhir']) + 1 : 1;
        return "{$prefixKategori}" . $this->generateNomor($noakhir, 6);
    }

    /**
     * Membuat kode barang baru dan menyimpannya ke database.
     *
     * @param string $prefixKategori Prefix kategori barang.
     * @return string Kode barang yang telah dibuat.
     */
    public function buatKodeBarang(string $prefixKategori): string
    {
        $data = $this->getKodeBarang($prefixKategori);
        $noakhir = $data ? intval($data['noakhir']) + 1 : 1;
        $noakhirStr = $this->generateNomor($noakhir, 6);

        if ($data) {
            $this->updateKodeBarang($prefixKategori, $noakhirStr);
        } else {
            $this->insertKodeBarang($prefixKategori, $noakhirStr);
        }

        return "{$prefixKategori}{$noakhirStr}";
    }

}
