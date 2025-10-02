<?php

use PHPUnit\Framework\TestCase;
use Esikat\Helper\MataUang;

class MataUangTest extends TestCase 
{
    public function testFormatTanpaDesimal()
    {
        $this->assertEquals('Rp 1.500.000', MataUang::format(1500000, 'Rp ', 0));
        $this->assertEquals('IDR 1.500.000', MataUang::format(1500000, 'IDR ', 0));
    }

    public function testFormatDenganDesimal()
    {
        $this->assertEquals('Rp 1.500.000,75', MataUang::format(1500000.75, 'Rp ', 2));
        $this->assertEquals('USD 1,500,000.75', MataUang::format(1500000.75, 'USD ', 2, ',', '.'));
    }

    public function testFormatTanpaPrefix()
    {
        $this->assertEquals('1.500.000', MataUang::format(1500000, '', 0));
    }

    public function testFormatDenganPemisahKustom()
    {
        $this->assertEquals('Rp 1,500,000.75', MataUang::format(1500000.75, 'Rp ', 2, ',', '.'));
        $this->assertEquals('€ 1 500 000,75', MataUang::format(1500000.75, '€ ', 2, ' ', ','));
    }
}