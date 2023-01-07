<?php

namespace Imponeer\Smarty\Extensions\Image\Exceptions;

use SmartyException;

class BadFitValueException extends SmartyException
{

    protected $message = 'resized_image "fit" argument must have "inside", "outside" or "fill" value';

}