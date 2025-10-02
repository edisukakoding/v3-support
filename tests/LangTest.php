<?php

use PHPUnit\Framework\TestCase;
use Esikat\Helper\Lang;

class LangTest extends TestCase
{
    protected function setUp(): void
    {
        $langPath = realpath(__DIR__ . '/../lang');
        Lang::setLangPath($langPath);
    }

    public function test_bahasa_default_dimuat_dengan_benar()
    {
        Lang::setDefault('id-ID');
        Lang::setCurrent('id-ID');
        Lang::init();

        $this->assertSame('Selamat Datang', Lang::get('title'));
    }

    public function test_fallback_ke_bahasa_default()
    {
        Lang::setDefault('id-ID');
        Lang::setCurrent('en-US');
        Lang::init();

        $this->assertSame('Nilai Terbenam', Lang::get('nested.key'));
    }

    public function test_set_dan_get_bahasa_saat_ini()
    {
        Lang::setDefault('id-ID');
        Lang::setCurrent('en-US');
        Lang::init();

        $this->assertSame('en-US', Lang::current());
    }

    public function test_jika_key_tidak_ada_maka_kembali_default()
    {
        Lang::setDefault('id-ID');
        Lang::setCurrent('en-US');
        Lang::init();

        $this->assertSame('fallback', Lang::get('tidak.ada', 'fallback'));
    }

    public function test_mendeteksi_semua_file_json()
    {
        $langs = Lang::available();
        $this->assertContains('id-ID', $langs);
        $this->assertContains('en-US', $langs);
    }

    public function test_helper_mengembalikan_terjemahan()
    {
        Lang::setDefault('id-ID');
        Lang::setCurrent('id-ID');
        Lang::init();

        $this->assertSame('Selamat Datang', __('title'));
    }
}
