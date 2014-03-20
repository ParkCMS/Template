<?php

use Parkcms\Template\ArgumentConverter;

class ArgumentConverterTest extends PHPUnit_Framework_TestCase
{
    public function testBooleanArgumentConverted()
    {
        $converter = new ArgumentConverter;

        $this->assertSame($converter->convert("true"), true);
        $this->assertSame($converter->convert("false"), false);
    }

    public function testIntegerArgumentConverted()
    {
        $converter = new ArgumentConverter;

        $this->assertSame($converter->convert("10"), 10);
    }

    public function testFloatingArgumentConverted()
    {
        $converter = new ArgumentConverter;

        $this->assertSame($converter->convert("10.3"), 10.3);
    }

    public function testStringArgumentConverted()
    {
        $converter = new ArgumentConverter;

        $this->assertSame($converter->convert("test string"), "test string");
    }

    public function testArrayArgumentConverted()
    {
        $converter = new ArgumentConverter;

        $returnedValue = $converter->convert("[1,2,3]");
        $this->assertTrue(is_array($returnedValue) && count($returnedValue) == 3);
    }
}