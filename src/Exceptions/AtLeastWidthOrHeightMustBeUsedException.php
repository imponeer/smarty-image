<?php

namespace Imponeer\Smarty\Extensions\Image\Exceptions;

use SmartyException;

class AtLeastWidthOrHeightMustBeUsedException extends SmartyException
{

    protected $message = 'resized_image needs width or height param to be specified (can be specified both)';

}