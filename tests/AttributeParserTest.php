<?php

use Parkcms\Template\AttributeParser as Parser;
use Parkcms\Template\ArgumentConverter;
use Illuminate\Events\Dispatcher as Event;

class AttributeParserTest extends PHPUnit_Framework_TestCase
{
    private $converter;
    private $event;

    public function setUp()
    {
        $this->event = new Event;
        $this->converter = new ArgumentConverter;
        $this->parser = new Parser($this->converter, $this->event);
    }

    public function testRemovesAttributes()
    {
        $content = <<<CONTENT
<!DOCTYPE html>
<html lang="en"><body>
    <div hcms-program="text" hcms-text="id"><h1>Ganon</h1></div>
    <div hcms-program="blog" hcms-blog="bbb" hcms-blog-limit="a"></div>
</body></html>

CONTENT;
        $expected = <<<EXPECTED
<!DOCTYPE html>
<html lang="en"><body>
    <div><h1>Ganon</h1></div>
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
    <div hcms-program="text" hcms-text="id">Prev content</div>
</body></html>

CONTENT;
        $expected = <<<EXPECTED
<!DOCTYPE html>
<html lang="en"><body>
    <div>Bla</div>
</body></html>

EXPECTED;

        $this->parser->setSource($content);

        $this->parser->pushHandler(function ($attr, $identifier, $params, $nodeValue) {
            return 'Bla';
        });

        $parsedContent = $this->parser->parse();

        $this->assertEquals($expected, $parsedContent);
    }

    public function testDetectsIdentifier()
    {
        $content = <<<CONTENT
<!DOCTYPE html>
<html lang="en"><body>
    <div hcms-program="text" hcms-text="id">Prev content</div>
</body></html>

CONTENT;

        $this->parser->setSource($content);

        $that = $this;

        $this->parser->pushHandler(function ($attr, $identifier, $params, $nodeValue) use ($that) {
            $that->assertEquals($identifier, 'id');
        });

        $parsedContent = $this->parser->parse();
    }

    public function testInjectingOfHTMLWorks()
    {
        $content = <<<CONTENT
<!DOCTYPE html>
<html lang="en"><body>
    <div hcms-program="text" hcms-text="id">Prev content<br></div>
    <div hcms-program="blog" hcms-blog="block2"><h1>Marked up content</h1></div>
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

        $this->parser->pushHandler(function ($attr, $identifier, $params, $nodeValue) {
            if ($attr == "text") {
                return '<h1>Bla</h1>';
            } else {
                return 'Bla';
            }
        });

        $parsedContent = $this->parser->parse();

        $this->assertEquals($expected, $parsedContent);
    }

    public function testHTMLLivesOnOnNull()
    {
        $content = <<<CONTENT
<!DOCTYPE html>
<html lang="en"><body>
    <div hcms-program="text" hcms-text="id">Prev content<br />ä ö ü ß &</div>
    <div hcms-program="blog" hcms-blog="block2"><h1>Marked up content</h1></div>
</body></html>

CONTENT;
        
        $this->parser->setSource($content);
        $this->parser->setRemoveAttributes(false);
        $this->parser->pushHandler(function ($attr, $identifier, $params, $nodeValue) {
            return null;
        });

        $this->assertEquals($content, $this->parser->parse());
    }

    public function testRemoveHandler()
    {
        $content = <<<CONTENT
<!DOCTYPE html>
<html lang="en"><body>
    <div hcms-program="text" hcms-text="id"></div>
</body></html>

CONTENT;

        $this->parser->setSource($content);

        $that = $this;
        $handler = function ($attr, $identifier, $params, $nodeValue) use ($that) {
            $that->fail('Handler was still called!');
        };

        $this->parser->pushHandler($handler);
        $this->parser->removeHandler($handler);

        $this->parser->parse();
    }

    public function testPostParseEvent()
    {
        $content = <<<CONTENT
<!DOCTYPE html>
<html lang="en"><body>
    <div hcms-program="text" hcms-text="id">Prev content<br />ä ö ü ß &</div>
    <div hcms-program="blog" hcms-blog="block2"><h1>Marked up content</h1></div>
    <hcms-scripts />
</body></html>

CONTENT;

        $expected = <<<CONTENT
<!DOCTYPE html>
<html lang="en"><body>
    <div><h1>Bla äöü</h1></div>
    <div>Bla</div>
    <div class="scripts"></div>
</body></html>

CONTENT;

        $this->event->listen('parkcms.parser.post', function($data) {
            $node = $data('hcms-scripts');
            $node[0]->setOuterText('<div class="scripts"></div>');
        });

        $this->parser->pushHandler(function ($attr, $identifier, $params, $nodeValue) {
            if ($attr == "text") {
                return '<h1>Bla äöü</h1>';
            } else {
                return 'Bla';
            }
        });

        $this->parser->setSource($content);

        $this->assertEquals($expected, $this->parser->parse());
    }
}