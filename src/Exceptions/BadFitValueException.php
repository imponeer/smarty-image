<?php

namespace Imponeer\Smarty\Extensions\Image\Exceptions;

use Smarty\Exception;

class BadFitValueException extends Exception
{
    /** @var string */
    protected $message = 'resized_image "fit" argument must have "inside", "outside" or "fill" value';
}
