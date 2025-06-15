<?php

namespace Imponeer\Smarty\Extensions\Image\Exceptions;

use Smarty\Exception;

class AtLeastWidthOrHeightMustBeUsedException extends Exception
{
    /** @var string */
    protected $message = 'resized_image needs width or height param to be specified (can be specified both)';
}
