<?php

namespace Esikat\Helper;

/**
 * Kelas untuk mengelola berbagai fungionalitas yang berhubungan dengan mata uang.
 */
class MataUang 
{
    /**
     * Format angka ke dalam bentuk mata uang (default Rupiah).
     *
     * @param float  $angka             Angka yang akan diformat.
     * @param string $prefix            Simbol mata uang (default: 'Rp ').
     * @param int    $desimal           Jumlah angka di belakang koma (default: 0).
     * @param string $pemisahRibuan     Pemisah ribuan (default: '.').
     * @param string $pemisahDesimal    Pemisah desimal (default: ',').
     * 
     * @return string Format mata uang dalam bentuk Rupiah.
     * 
     * @example
     * // Contoh penggunaan:
     * echo MataUang::format(1500000); // Output: Rp 1.500.000
     * echo MataUang::format(1500000, 'IDR '); // Output: IDR 1.500.000
     * echo MataUang::format(1500000.75, 'Rp ', 2); // Output: Rp 1.500.000,75
     */
    public static function format(float $angka, string $prefix = 'Rp ', int $desimal = 0, string $pemisahRibuan = '.', string $pemisahDesimal = ','): string
    {
        return $prefix . number_format($angka, $desimal, $pemisahDesimal, $pemisahRibuan);
    }
}