<?php

use Parkcms\Template\AttributeParser as Parser;
use Parkcms\Template\ArgumentConverter;

class AttributeParserTest extends PHPUnit_Framework_TestCase
{
    private $converter;

    public function setUp()
    {
        $this->converter = new ArgumentConverter;
        $this->parser = new Parser($this->converter);
    }

    public function testRemovesAttributes()
    {
        $content = <<<CONTENT
<!DOCTYPE html>
<html lang="en"><body>
    <div hcms-text="id"></div>
    <div hcms-blog="bbb" hcms-blog-limit="a"></div>
</body></html>

CONTENT;
        $expected = <<<EXPECTED
<!DOCTYPE html>
<html lang="en"><body>
    <div></div>
    <div></div>
</body></html>

EXPECTED;

        $this->parser->setSource($content);

        $parsedContent = $this->parser->parse();

        $this->assertEquals($expected, $parsedContent);
    }

    public function testExecutesOneHandler()
    {
        $content = <<<CONTENT
<!DOCTYPE html>
<html lang="en"><body>
    <div hcms-text="id">Prev content</div>
</body></html>

CONTENT;
        $expected = <<<EXPECTED
<!DOCTYPE html>
<html lang="en"><body>
    <div>Bla</div>
</body></html>

EXPECTED;

        $this->parser->setSource($content);

        $this->parser->pushHandler(function ($attr, $params, $nodeValue) {
            return 'Bla';
        });

        $parsedContent = $this->parser->parse();

        $this->assertEquals($expected, $parsedContent);
    }

    public function testInjectingOfHTMLWorks()
    {
        $content = <<<CONTENT
<!DOCTYPE html>
<html lang="en"><body>
    <div hcms-text="id">Prev content<br></div>
    <div hcms-blog="block2"><h1>Marked up content</h1></div>
</body></html>

CONTENT;
        $expected = <<<EXPECTED
<!DOCTYPE html>
<html lang="en"><body>
    <div><h1>Bla</h1></div>
    <div>Bla</div>
</body></html>

EXPECTED;

        $this->parser->setSource($content);

        $this->parser->pushHandler(function ($attr, $params, $nodeValue) {
            if ($attr == "text") {
                return '<h1>Bla</h1>';
            } else {
                return 'Bla';
            }
        });

        $parsedContent = $this->parser->parse();

        $this->assertEquals($expected, $parsedContent);
    }

    public function testRemoveHandler()
    {
        $content = <<<CONTENT
<!DOCTYPE html>
<html lang="en"><body>
    <div hcms-text="id"></div>
</body></html>

CONTENT;

        $this->parser->setSource($content);

        $that = $this;
        $handler = function ($attr, $params, $nodeValue) use ($that) {
            $that->fail('Handler was still called!');
        };

        $this->parser->pushHandler($handler);
        $this->parser->removeHandler($handler);

        $this->parser->parse();
    }
}