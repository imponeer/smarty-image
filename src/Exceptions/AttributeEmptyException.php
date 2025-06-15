<?php

namespace Imponeer\Smarty\Extensions\Image\Exceptions;

use Smarty\Exception;
use Throwable;

class AttributeEmptyException extends Exception
{
    public function __construct(string $attribute, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("resized_image requires \"$attribute\" to be not empty", $code, $previous);
    }
}
