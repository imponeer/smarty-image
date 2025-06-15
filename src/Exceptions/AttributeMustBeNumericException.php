<?php

namespace Imponeer\Smarty\Extensions\Image\Exceptions;

use SmartyException;
use Throwable;

class AttributeMustBeNumericException extends SmartyException
{
    public function __construct(string $attribute, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("resized_image requires \"$attribute\" to be numeric", $code, $previous);
    }
}
