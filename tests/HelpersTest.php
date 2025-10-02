<?php

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function testDorongDanAmbilTumpukan()
    {
        startPush();
        echo "Test1";
        endPush('tes');

        startPush();
        echo "Test2";
        endPush('tes');

        $result = stack('tes');
        $expected = "Test1\nTest2";

        $this->assertEquals($expected, $result);
    }

    public function testSimpanDanAmbilPesanKilat()
    {
        $_SESSION = [];
        flashMessage('test', 'Ini adalah pesan uji', 'info');
        
        $this->assertArrayHasKey('test', $_SESSION['flash']);
        $this->assertEquals('Ini adalah pesan uji', $_SESSION['flash']['test']['message']);
        $this->assertEquals('info', $_SESSION['flash']['test']['type']);
    }

    public function testAmbilDanHapusPesanKilat()
    {
        $_SESSION = [];
        flashMessage('test', 'Ini adalah pesan uji', 'info');
        $messageHtml = flashMessage('test');
        
        $this->assertStringContainsString('Ini adalah pesan uji', $messageHtml);
        $this->assertStringContainsString('alert-info', $messageHtml);
        $this->assertArrayNotHasKey('test', $_SESSION['flash']);
    }
}
