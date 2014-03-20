<?php

namespace Parkcms\Template;

/**
 * This class handles conversion of arguments
 * By now just a wrapper for separation of concerns...
 */
class ArgumentConverter
{
    public function convert($arg)
    {
		$try = json_decode($arg);
		
		if($try === null) {
			return $arg;
		}
		
        return $try;
    }
}