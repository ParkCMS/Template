<?php
namespace Parkcms\Template;

use Illuminate\Support\Facades\Facade;

class Parser extends Facade {

    protected static function getFacadeAccessor() { return 'parkcms.parser'; }

}