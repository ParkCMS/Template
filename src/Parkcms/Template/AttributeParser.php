<?php

namespace Parkcms\Template;

use Illuminate\Events\Dispatcher as Event;
use HTML_Parser_HTML5;

class AttributeParser
{
    protected $_prefix = 'hcms-';

    protected $_prefixLength = 5;

    protected $_source;

    protected $_charset = 'UTF-8';

    protected $_tree = null;

    protected $_handlers = array();

    protected $_removeAttributes = true;

    protected $_converter = null;
    protected $_event = null;

    public function __construct(ArgumentConverter $converter, Event $event)
    {
        $this->_converter = $converter;
        $this->_event = $event;
    }

    /**
     * Sets the source, which has to be parsed
     * @param string $source the (already loaded) Template source
     */
    public function setSource($source)
    {
        $this->_source = $source;
    }

    /**
     * Sets the charset of the template
     * @param string $charset charset, e.g. UTF-8
     */
    public function setCharset($charset)
    {
        $this->_charset = $charset;
    }

    /**
     * Set the attribute prefix used to query
     * This must not be an empty string!
     * @param string $prefix new attribute prefix
     */
    public function setPrefix($prefix)
    {
        if (empty($prefix)) {
            throw new \InvalidArgumentException("Prefix must not be empty!");
        }
        $this->_prefix = $prefix;
        $this->_prefixLength = strlen($prefix);
    }

    /**
     * By setting this property to true, all parsed attributes will be removed
     * from the document after parsing, otherwise not.
     * @param boolean $removeAttributes true: attributes removed false: not
     */
    public function setRemoveAttributes($removeAttributes)
    {
        $this->_removeAttributes = $removeAttributes;
    }

    /**
     * Push a new attribute handler on the stack
     * @param  Closure $handler The handler function
     */
    public function pushHandler(\Closure $handler)
    {
        $this->_handlers[] = $handler;
    }

    /**
     * Removes the given handler from the Stack
     * @param  Closure $handler handler to delete
     */
    public function removeHandler(\Closure $handler)
    {
        unset($this->_handlers[array_search($handler, $this->_handlers)]);
    }

    /**
     * Parses the loaded template
     * @param  string $source Can be used to set the source
     * @return string         Parsed content
     */
    public function parse($source=null)
    {
        if ($source !== null) {
            $this->setSource($source);
        }

        if ($this->_source === null) {
            throw new \BadMethodCallException('Missing source!');
        }

        $mainAttrib = $this->_prefix . 'program';

        $this->_tree = $html = new HTML_Parser_HTML5($this->_source);

        $programs = $html('['.$mainAttrib.']');

        foreach ($programs as $program) {
            $this->parseNode($program);
        }

        // Fire post parsing event
        $this->_event->fire('parkcms.parser.post', array(&$this->_tree));

        return $this->_tree->__toString();
    }

    public function parseArgumentValue($arg)
    {
        return $this->_converter->convert($arg);
    }

    private function parseNode(\HTML_Node &$node)
    {
        $prefixLength = $this->_prefixLength;
        $attr = $node->attributes[$this->_prefix . 'program'];
        $identifier = $node->attributes[$this->_prefix . $attr];
        $params = array();

        $attrsToRemove = array();

        // Query through attributes and parse them
        foreach($node->attributes as $attributeName => $attribute) {

            // is prefix attribute or HTML-Attribute?
            if (strpos($attributeName, $this->_prefix) === 0) {
                if ($attributeName === $this->_prefix . 'program' || $attributeName === $this->_prefix . $attr) {
                    $attrsToRemove[] = $attributeName;
                    continue;
                }
                $nodeName = $this->subtractPrefix($attributeName);
                $components = explode("-", $nodeName);
                if (array_shift($components) === $attr) {
                    $index = implode("-", $components);
                    $params[$index] = $this->parseArgumentValue($attribute);
                }
                // This attribute should be removed
                $attrsToRemove[] = $attributeName;
            }
        }
        // Remove marked attributes
        if ($this->_removeAttributes) {
            foreach ($attrsToRemove as $rem) {
                $node->deleteAttribute($rem);
            }
        }

        if ($attr && $identifier) {
            $currentContent = $node->getInnerText();
            $result = $this->runHandler($attr, $identifier, $params, $currentContent);
            if ($result !== null) {
                $node->setInnerText($result);
            }
        }
    }

    private function subtractPrefix($string)
    {
        return substr($string, $this->_prefixLength);
    }

    /**
     * Executes the handlers for a parsed block
     * @param  string $type    Block type
     * @param  array  $params  The parameters given as attributes
     * @param  string $content The previous content of the block node
     * @return string          The new block node content
     */
    private function runHandler($type, $identifier, $params, $content)
    {
        //$res = $content;
        $res = null;

        foreach ($this->_handlers as $handler) {
            $handlerResult = $handler($type, $identifier, $params, $content);
            if ($handlerResult !== null) {
                $content = $handlerResult;
                $res = $handlerResult;
            }
        }

        return $res;
    }
}