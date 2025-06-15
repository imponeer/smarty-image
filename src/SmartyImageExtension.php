<?php

namespace Imponeer\Smarty\Extensions\Image;

use Imponeer\Smarty\Extensions\Image\Functions\ResizeImageFunction;
use Psr\Cache\CacheItemPoolInterface;
use Smarty\Extension\Base;
use Smarty\FunctionHandler\FunctionHandlerInterface;

class SmartyImageExtension extends Base
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache
    ) {
    }

    public function getFunctionHandler(string $functionName): ?FunctionHandlerInterface
    {
        return match ($functionName) {
            'resized_image' => new ResizeImageFunction($this->cache),
            default => null,
        };
    }
}
