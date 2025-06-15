<?php

namespace Imponeer\Smarty\Extensions\Image;

use Imponeer\Contracts\Smarty\Extension\SmartyFunctionInterface;
use Imponeer\Smarty\Extensions\Image\Exceptions\AttributeEmptyException;
use Imponeer\Smarty\Extensions\Image\Exceptions\AttributeMustBeNumericException;
use Imponeer\Smarty\Extensions\Image\Exceptions\AttributeMustBeStringException;
use Imponeer\Smarty\Extensions\Image\Exceptions\BadFitValueException;
use Imponeer\Smarty\Extensions\Image\Exceptions\AtLeastWidthOrHeightMustBeUsedException;
use Imponeer\Smarty\Extensions\Image\Exceptions\RequiredArgumentException;
use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\Image as SingleImage;
use Intervention\Image\ImageManagerStatic as Image;
use JsonException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Smarty_Internal_Template;
use SmartyCompilerException;

/**
 * Describes {resize_image} smarty function
 *
 * @package Imponeer\Smarty\Extensions\Image
 */
class ResizeImageFunction implements SmartyFunctionInterface
{
    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * ResizeImageFunction constructor.
     *
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'resized_image';
    }

    /**
     * @inheritDoc
     *
     * @param array<string, mixed> $params Parameters used to call function
     * @param Smarty_Internal_Template $template Current smarty object instance
     *
     * @throws AttributeEmptyException
     * @throws AttributeMustBeNumericException
     * @throws AttributeMustBeStringException
     * @throws BadFitValueException
     * @throws InvalidArgumentException
     * @throws NotReadableException
     * @throws AtLeastWidthOrHeightMustBeUsedException
     * @throws RequiredArgumentException
     */
    public function execute($params, Smarty_Internal_Template $template)
    {
        $otherParams = $this->getOtherParams($params);

        $this->validateParams($params, $otherParams, $template);

        $this->fixParams($params);
        $this->fixOtherParams($otherParams);

        $encodedStr = serialize($params);
        /** @noinspection SpellCheckingInspection */
        $cacheKey = 'imponeer-spri-' . md5($encodedStr) . '-' . strlen($encodedStr);
        $cachedItem = $this->cache->getItem($cacheKey);

        if (!$cachedItem->isHit()) {
            $cachedItem->set(
                $this->renderOutput(
                    $params['return'],
                    $this->doResize($params['fit'], $params['file'], $params['width'], $params['height']),
                    $otherParams
                )
            );
            $this->cache->save($cachedItem);
        }

        return $cachedItem->get();
    }

    /**
     * Applies some fixes to params values
     *
     * @param array<string, mixed> $params Params to fix
     *
     * @return void
     */
    protected function fixParams(array &$params): void
    {
        $params['width'] = isset($params['width']) ? (int) $params['width'] : null;
        $params['height'] = isset($params['height']) ? (int) $params['height'] : null;
        $params['fit'] = isset($params['fit']) ? strtolower($params['fit']) : 'outside';
        $params['return'] = isset($params['return']) ? strtolower($params['return']) : 'image';

        if (
            (strpos($params['file'], 'data:') !== 0) &&
            !filter_var($params['file'], FILTER_VALIDATE_URL) &&
            !file_exists($params['file'])
        ) {
            $params['file'] = ($params['basedir'] ?? $_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR . $params['file'];
        }
    }

    /**
     * Applies some fixes to other-params values
     *
     * @param array<string, mixed> $params Other-params to fix
     *
     * @return void
     */
    protected function fixOtherParams(array &$params): void
    {
        if (isset($params['link'])) {
            $params['href'] = $params['link'];
            unset($params['link']);
        }
    }

    /**
     * Renders output string
     *
     * @param string $return Return format
     * @param SingleImage $image Image for the output
     * @param array<string, mixed> $otherParams Other params
     *
     * @return string
     */
    protected function renderOutput(string $return, SingleImage $image, array $otherParams): string
    {
        if ($return === 'image') {
            return $this->renderImageTag($image, $otherParams);
        }

        if ($return === 'url') {
            return (string) $image->encode('data-url');
        }

        return '???';
    }

    /**
     * Renders HTML IMG tag for the image
     *
     * @param SingleImage $image Image to be returned for output
     * @param array<string, mixed> $otherParams Some params
     *
     * @return string
     */
    protected function renderImageTag(SingleImage $image, array $otherParams): string
    {
        $ret = '';

        if (isset($otherParams['href'])) {
            $ret .= $this->buildHTMLTag('a', ['href' => $otherParams['href']]);
        }

        $allAttributes = $otherParams + [
                'alt' => '',
                'src' => (string) $image->encode('data-url')
            ];

        if (isset($allAttributes['link'])) {
            unset($allAttributes['link']);
        }

        if (isset($allAttributes['href'])) {
            unset($allAttributes['href']);
        }

        if (isset($allAttributes['basedir'])) {
            unset($allAttributes['basedir']);
        }

        $ret .= $this->buildHTMLTag('img', $allAttributes, true);

        if (isset($otherParams['href'])) {
            $ret .= '</a>';
        }

        return $ret;
    }

    /**
     * Builds HTML tag
     *
     * @param string $name Tag name
     * @param array<string, mixed> $attributes Dictionary of tag attributes
     * @param bool $quickCloseTag Is tag should be quick close tag?
     *
     * @return string
     */
    private function buildHTMLTag(string $name, array $attributes, bool $quickCloseTag = false): string
    {
        $ret = '<' . $name;

        foreach ($attributes as $attrName => $attrValue) {
            $ret .= ' ' . $attrName . '="' . htmlentities($attrValue) . '"';
        }

        if ($quickCloseTag) {
            return $ret . '/>';
        }

        return $ret . '>';
    }

    /**
     * Reads image and do resize
     *
     * @param string $method Resize method to use
     * @param string $file File to resize
     * @param int|null $width New image width
     * @param int|null $height New image height
     *
     * @return SingleImage
     */
    protected function doResize(string $method, string $file, ?int $width, ?int $height): SingleImage
    {
        $image = Image::make($file);

        switch ($method) {
            case 'fill':
                return $image->resize($width, $height);
            case 'inside':
                if ($width && $height) {
                    if ($image->width() > $image->height()) {
                        $width = null;
                    } else {
                        $height = null;
                    }
                }
                return $image->resize($width, $height, function ($constraint) {
                    $constraint->aspectRatio();
                });
            case 'outside':
                // For 'outside' fit, we need both width and height
                // If one is missing, use the image's original dimensions
                $finalWidth = $width ?? $image->width();
                $finalHeight = $height ?? $image->height();
                return $image->fit($finalWidth, $finalHeight);
        }

        return $image;
    }

    /**
     * Validates params
     *
     * @param array<string, mixed> $params Current function arguments (aka params) to be validated
     * @param array<string, mixed> $otherParams Params with params that doesnt  have specific role
     * @param Smarty_Internal_Template $template Current smarty instance
     *
     * @throws RequiredArgumentException
     * @throws AttributeEmptyException
     * @throws AttributeMustBeStringException
     * @throws AttributeMustBeNumericException
     * @throws BadFitValueException
     * @throws AtLeastWidthOrHeightMustBeUsedException
     */
    protected function validateParams(array $params, array $otherParams, Smarty_Internal_Template $template): void
    {
        if (!isset($params['file'])) {
            throw new RequiredArgumentException('file');
        }

        if (empty($params['file'])) {
            throw new AttributeEmptyException('file');
        }

        if (!is_string($params['file'])) {
            throw new AttributeMustBeStringException('file');
        }

        if (isset($params['width']) && !is_numeric($params['width'])) {
            throw new AttributeMustBeNumericException('width');
        }

        if (isset($params['height']) && !is_numeric($params['height'])) {
            throw new AttributeMustBeNumericException('height');
        }

        if (isset($params['fit']) && !in_array(strtolower($params['fit']), ['inside', 'outside', 'fill'], true)) {
            throw new BadFitValueException();
        }

        if (isset($params['return']) && $params['return'] === 'image') {
            $this->validateImageOtherParams($otherParams, $template);
        }

        if (!isset($params['width']) && !isset($params['height'])) {
            throw new AtLeastWidthOrHeightMustBeUsedException();
        }
    }

    /**
     * Validates other params for image return type
     *
     * @param array<string, mixed> $otherParams Other params array
     * @param Smarty_Internal_Template $template Current smarty instance
     *
     * @throws AttributeMustBeStringException
     */
    protected function validateImageOtherParams(array $otherParams, Smarty_Internal_Template $template): void
    {
        foreach ($otherParams as $key => $value) {
            if (!isset($value) || is_string($value)) {
                continue;
            }

            throw new AttributeMustBeStringException($key);
        }
    }

    /**
     * Gets array from params that doesn't have any specific role
     *
     * @param array<string, mixed> $params Smarty function supplied arguments
     *
     * @return array<string, string>
     */
    private function getOtherParams(array $params): array
    {
        $ret = [];
        foreach ($params as $key => $value) {
            $k = strtolower(trim($key));
            if (in_array($k, ['fit', 'width', 'height', 'return', 'file', 'src'])) {
                continue;
            }
            $ret[$k] = (string) $value;
        }

        return $ret;
    }
}
