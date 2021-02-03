<?php

namespace Imponeer\Smarty\Extensions\Image;

use Imponeer\Contracts\Smarty\Extension\SmartyFunctionInterface;
use Intervention\Image\ImageManagerStatic as Image;
use Psr\Cache\CacheItemPoolInterface;
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
     */
    public function execute($params, Smarty_Internal_Template &$template)
    {
        $otherParams = $this->getOtherParams($params);

        $this->validateParams($params, $otherParams, $template);

        if (!filter_var($params['file'], FILTER_VALIDATE_URL) && !file_exists($params['file'])) {
            $params['file'] = ($params['basedir'] ?? $_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR . $params['file'];
        }

        $encodedStr = json_encode($params);
        $cacheKey = 'imponeer-spri-' . md5($encodedStr) . '-' . strlen($encodedStr);
        $cachedItem = $this->cache->getItem($cacheKey);

        if (!$cachedItem->isHit()) {
            $cachedItem->set(
                $this->renderOutput(
                    isset($params['return']) ? strtolower($params['return']) : 'image',
                    $this->doResize(
                        isset($params['fit']) ? strtolower($params['fit']) : 'outside',
                        isset($params['basedir']) ? $params['basedir'] . DIRECTORY_SEPARATOR : $params['file'],
                        $params['width'] ?? null,
                        $params['height'] ?? null
                    ),
                    $otherParams
                )
            );
            $this->cache->save($cachedItem);
        }

        return $cachedItem->get();
    }

    /**
     * Renders output string
     *
     * @param string $return Return format
     * @param \Intervention\Image\Image $image Image for the output
     * @param array $otherParams Other params
     *
     * @return string
     */
    protected function renderOutput(string $return, \Intervention\Image\Image $image, array $otherParams) {
        if ($return === 'image') {
            return $this->renderImageTag($image, $otherParams);
        }

        if ($return === 'url') {
            return (string)$image->encode('data-url');
        }

        return '???';
    }

    /**
     * Renders HTML IMG tag for the image
     *
     * @param \Intervention\Image\Image $image Image to be returned for output
     * @param array $otherParams Some params
     *
     * @return string
     */
    protected function renderImageTag(\Intervention\Image\Image $image, array $otherParams): string {
        $ret = '';

        if (isset($otherParams['href'])) {
            $ret .= $this->buildHTMLTag('a', ['href' => $otherParams['href']]);
        } elseif (isset($otherParams['link'])) {
            $ret .= $this->buildHTMLTag('a', ['href' => $otherParams['link']]);
        }

        $ret .= $this->buildHTMLTag(
            'img',
            $otherParams + [
                'alt' => '',
                'src' => (string)$image->encode('data-url')
            ],
            true
        );

        if (isset($otherParams['href']) || isset($otherParams['link'])) {
            $ret .= '</a>';
        }

        return $ret;
    }

    /**
     * Builds HTML tag
     *
     * @param string $name Tag name
     * @param array $attributes Dictionary of tag attributes
     * @param bool $quickCloseTag Is tag should be quick close tag?
     *
     * @return string
     */
    private function buildHTMLTag(string $name, array $attributes, bool $quickCloseTag = false): string
    {
        $ret = '<' . $name;

        foreach ($attributes as $attrName => $attrValue) {
            $ret .= ' ' . $attrName . '=' . json_encode((string)$attrValue);
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
     * @return \Intervention\Image\Image
     */
    protected function doResize($method, $file, $width, $height) {
        $image = Image::make($file);
        switch ($method) {
            case 'fill':
                return $image->resize($width, $height);
            case 'inside':
                if ($image->width() > $image->height()) {
                    $width=null;
                } else {
                    $height=null;
                }
                return $image->resize($width, $height, function ($constraint) {
                    $constraint->aspectRatio();
                });
            case 'outside':
                return $image->fit($width, $height);
        }
    }

    /**
     * Validates params
     *
     * @param array $params Current function arguments (aka params) to be validated
     * @param array $otherParams Params with params that doesnt  have specific role
     * @param Smarty_Internal_Template $template Current smarty instance
     *
     * @throws SmartyCompilerException
     */
    protected function validateParams(array $params, array $otherParams, Smarty_Internal_Template $template)
    {
        if (!isset($params['file'])) {
            $template->compiler->trigger_template_error('resize_image requires "file" argument', null, true);
        } elseif (empty($params['file'])) {
            $template->compiler->trigger_template_error('resize_image requires "file" to be not empty', null, true);
        } elseif (!is_string($params['file'])) {
            $template->compiler->trigger_template_error('resize_image requires "file" must be a string', null, true);
        }

        if (isset($params['width']) && !is_numeric($params['width'])) {
            $template->compiler->trigger_template_error('resize_image "width" argument must be numeric', null, true);
        }

        if (isset($params['height']) && !is_numeric($params['height'])) {
            $template->compiler->trigger_template_error('resize_image "height" argument must be numeric', null, true);
        }

        if (isset($params['fit']) && in_array(strtolower($params['fit']), ['inside', 'outside', 'fill'], true)) {
            $template->compiler->trigger_template_error('resize_image "fill" argument must have "inside", "outside" or "fill" value', null, true);
        }

        if (isset($params['return']) && $params['return'] === 'image') {
            $this->validateImageOtherParams($otherParams, $template);
        }

        if (!isset($params['width']) && !isset($params['height'])) {
            $template->compiler->trigger_template_error('resized_image needs width or height param to be specified', null, true);
        }
    }

    /**
     * Validates other params for image return type
     *
     * @param array $otherParams Other params array
     * @param Smarty_Internal_Template $template Current smarty instance
     *
     * @throws SmartyCompilerException
     */
    protected function validateImageOtherParams(array $otherParams, Smarty_Internal_Template $template)
    {
        foreach ($otherParams as $key => $value) {
            if (!isset($value) || is_string($value)) {
                continue;
            }
            $template->compiler->trigger_template_error(
                'resize_image "' . $key . '" argument must be a string',
                null,
                true
            );
        }
    }

    /**
     * Gets array from params that doesn't have any specific role
     *
     * @param array $params Smarty function supplied arguments
     *
     * @return array
     */
    private function getOtherParams(array $params): array
    {
        $ret = [];
        foreach ($params as $key => $value) {
            $k = strtolower(trim($key));
            if (in_array($k, ['fit', 'width', 'height', 'return', 'file', 'src'])) {
                continue;
            }
            $ret[$k] = (string)$value;
        }

        return $ret;
    }
}