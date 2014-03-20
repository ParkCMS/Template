<?php

namespace Parkcms\Template;

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

    public function __construct(ArgumentConverter $converter)
    {
        $this->_converter = $converter;
    }

    /**
     * Sets the source, which has to be parsed
     * @param string $source the (already loaded) Template source
     */
    public function setSource($source)
    {
        if (function_exists('mb_convert_encoding') 
            && in_array(
                strtolower($this->_charset),
                array_map('strtolower', mb_list_encodings())
                )
            ) {
            $source = mb_convert_encoding($source, 'HTML-ENTITIES', $this->_charset);
        }
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
        $this->initializeDOM();

        $domxpath = new \DOMXPath($this->_tree);

        $selector = '//*[@*[starts-with(name(), "' . $this->_prefix . '")]]';

        $result = $domxpath->query($selector);

        // Look at each node in tree that has at least one attribute with prefix
        foreach ($result as $i => $node) {
            $this->parseNode($node);
        }

        return $this->_tree->saveHTML();
    }

    public function parseArgumentValue($arg)
    {
        return $this->_converter->convert($arg);
    }

    private function initializeDOM()
    {
        $current = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);

        $this->_tree = new \DOMDocument('1.0', $this->_charset);
        $this->_tree->validateOnParse = true;

        @$this->_tree->loadHTML($this->_source);

        libxml_use_internal_errors($current);
        libxml_disable_entity_loader($disableEntities);
    }

    private function parseNode(&$node)
    {
        $prefixLength = $this->_prefixLength;
        $attr = false;
        $params = array();

        $attrsToRemove = array();

        // Query through attributes and parse them
        foreach($node->attributes as $attribute) {
            // is prefix attribute or HTML-Attribute?
            if (strpos($attribute->nodeName, $this->_prefix) === 0) {
                $nodeName = $this->subtractPrefix($attribute->nodeName);
                if (!$attr) {
                    $attr = $nodeName;
                } else {
                    $components = explode("-", $nodeName);
                    if ($components[0] === $attr) {
                        unset($components[0]);
                        $index = implode("-", $components);
                        $params[$index] = $this->parseArgumentValue($attribute->nodeValue);
                    }
                }
                // This attribute should be removed
                $attrsToRemove[] = $attribute->nodeName;
            }
        }
        // Remove marked attributes
        if ($this->_removeAttributes) {
            foreach ($attrsToRemove as $rem) {
                $node->removeAttribute($rem);
            }
        }

        if ($attr) {
            $docfrag = $this->_tree->createDocumentFragment();
            $result = $this->runHandler($attr, $params, $node->nodeValue);
            $node->nodeValue = "";
            $docfrag->appendXML($result);
            if ($docfrag->hasChildNodes()) {
                $node->appendChild($docfrag);
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
    private function runHandler($type, $params, $content)
    {
        $res = $content;

        foreach ($this->_handlers as $handler) {
            $handlerResult = $handler($type, $params, $res);
            if ($handlerResult !== null) {
                $res = $handlerResult;
            }
        }

        return $res;
    }
}