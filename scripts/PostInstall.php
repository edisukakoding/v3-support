<?php

namespace Esikat\Helper\Scripts;

class PostInstall 
{
    public static function copyEsikat()
    {
        $sumber = __DIR__ . '/../bin/esikat';
        $tujuan = getcwd() . '/esikat';

        if (!file_exists($sumber)) {
            echo "File esikat tidak ditemukan di: $sumber\n";
            return;
        }

        if (copy($sumber, $tujuan)) {
            chmod($tujuan, 0755);
            echo "✔ File 'esikat' berhasil disalin ke modul\n";
        } else {
            echo "❌ Gagal menyalin file 'esikat'\n";
        }
    }
}