<?php

namespace Imponeer\Smarty\Extensions\Image\Exceptions;

use SmartyException;
use Throwable;

class RequiredArgumentException extends SmartyException
{

    public function __construct($attribute, $code = 0, Throwable $previous = null)
    {
        parent::__construct("resized_image requires \"$attribute\" argument", $code, $previous);
    }

}