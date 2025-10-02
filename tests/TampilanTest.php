<?php

use PHPUnit\Framework\TestCase;
use Esikat\Helper\Tampilan;

class TampilanTest extends TestCase 
{
    public function testDorongDanAmbilTumpukan()
    {
        Tampilan::mulai();
        echo "Test1";
        Tampilan::dorong('test');

        Tampilan::mulai();
        echo "Test2";
        Tampilan::dorong('test');

        $result = Tampilan::tumpukan('test');
        $expected = "Test1\nTest2";

        $this->assertEquals($expected, $result);
    }

    public function testSimpanDanAmbilPesanKilat()
    {
        $_SESSION = [];
        Tampilan::pesanKilat('test', 'Ini adalah pesan uji', 'info');
        
        $this->assertArrayHasKey('test', $_SESSION['flash']);
        $this->assertEquals('Ini adalah pesan uji', $_SESSION['flash']['test']['message']);
        $this->assertEquals('info', $_SESSION['flash']['test']['type']);
    }

    public function testAmbilDanHapusPesanKilat()
    {
        $_SESSION = [];
        Tampilan::pesanKilat('test', 'Ini adalah pesan uji', 'info');
        $messageHtml = Tampilan::pesanKilat('test');
        
        $this->assertStringContainsString('Ini adalah pesan uji', $messageHtml);
        $this->assertStringContainsString('alert-info', $messageHtml);
        $this->assertArrayNotHasKey('test', $_SESSION['flash']);
    }
}