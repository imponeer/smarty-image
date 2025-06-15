<?php

namespace Imponeer\Smarty\Extensions\Image\Tests;

use Imponeer\Smarty\Extensions\Image\Exceptions\AtLeastWidthOrHeightMustBeUsedException;
use Imponeer\Smarty\Extensions\Image\Exceptions\AttributeMustBeNumericException;
use Imponeer\Smarty\Extensions\Image\Exceptions\AttributeMustBeStringException;
use Imponeer\Smarty\Extensions\Image\Exceptions\BadFitValueException;
use Imponeer\Smarty\Extensions\Image\Exceptions\RequiredArgumentException;
use Imponeer\Smarty\Extensions\Image\SmartyImageExtension;
use Intervention\Image\Exception\NotReadableException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\FileDoesNotExistException;
use Smarty\Exception;
use Smarty\Smarty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DomCrawler\Crawler;

use function BenTools\CartesianProduct\cartesian_product;

class ResizeImageFunctionTest extends TestCase
{
    private Smarty $smarty;

    protected function setUp(): void
    {
        $this->smarty = new Smarty();
        $this->smarty->caching = Smarty::CACHING_OFF;
        $this->smarty->addExtension(
            new SmartyImageExtension(new ArrayAdapter())
        );

        parent::setUp();
    }

    /**
     * @return array<string, mixed>
     */
    public static function getInvokeData(): array
    {
        $content = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'test.jpg');

        if ($content === false) {
            throw new FileDoesNotExistException(
                __DIR__ . DIRECTORY_SEPARATOR . 'test.jpg'
            );
        }

        $combinator = cartesian_product([
            'width' => [
                null,
                '150',
                'bad-value'
            ],
            'height' => [
                null,
                '150',
                'bad-value',
            ],
            'fit' => [
                null,
                'inside',
                'outside',
                'fill',
                'bad-value',
            ],
            'return' => [
                null,
                'image',
                'url',
                'bad-value',
            ],
            'basedir' => [
                null,
                __DIR__
            ],
            'href' => [
                null,
                'http://localhost.local/',
            ],
            'link' => [
                null,
                'http://localhost.local/',
            ],
            'file' => [
                null,
                'test.jpg',
                realpath(__DIR__ . DIRECTORY_SEPARATOR . 'test.jpg'),
                //'https://upload.wikimedia.org/wikipedia/commons/8/85/Impresscms_admin_screenshot.jpg',
                'data:image/jpeg;base64,' . base64_encode($content),
                99
            ],
        ]);

        $ret = [];
        foreach ($combinator as $combination) {
            $attrs = array_filter($combination);

            $label = [];
            foreach ($attrs as $k => $v) {
                if (str_starts_with($v, 'https://') || str_starts_with($v, 'http://')) {
                    $label[] = $k . '=URL';
                } elseif (str_starts_with($v, 'data:')) {
                    $label[] = $k . '=DATA_URL';
                } elseif (is_dir($v)) {
                    $label[] = $k . '=DIR';
                } elseif (is_file($v)) {
                    $label[] = $k . '=FILE1';
                } elseif (is_file(__DIR__ . DIRECTORY_SEPARATOR . $v)) {
                    $label[] = $k . '=FILE2';
                } else {
                    $label[] = $k . '=' . $v;
                }
            }

            if (empty($label)) {
                $label[] = 'empty params';
            } else {
                ksort($label);
            }

            $ret[implode('; ', $label)] = [$attrs];
        }

        return $ret;
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function renderTag(array $attrs): string
    {
        $ret = '{resized_image';
        foreach ($attrs as $k => $v) {
            $ret .= ' ' . $k . '=' . (is_string($v) ? '"' : '') . $v . (is_string($v) ? '"' : '');
        }
        $ret .= '}';

        return $ret;
    }

    /**
     * @param array<string, mixed> $attrs
     *
     * @throws Exception
     */
    #[DataProvider('getInvokeData')]
    public function testInvoke(array $attrs): void
    {
        $src = urlencode(
            $this->renderTag($attrs)
        );

        if (!isset($attrs['file'])) {
            $this->expectException(RequiredArgumentException::class);
        } elseif (!is_string($attrs['file'])) {
            $this->expectException(AttributeMustBeStringException::class);
        } elseif (
            (isset($attrs['width']) && !is_numeric($attrs['width'])) ||
            (isset($attrs['height']) && !is_numeric($attrs['height']))
        ) {
            $this->expectException(AttributeMustBeNumericException::class);
        } elseif (isset($attrs['fit']) && !in_array($attrs['fit'], ['inside', 'outside', 'fill'], true)) {
            $this->expectException(BadFitValueException::class);
        } elseif (!isset($attrs['height']) && !isset($attrs['width'])) {
            $this->expectException(AtLeastWidthOrHeightMustBeUsedException::class);
        } elseif (
            !str_starts_with($attrs['file'], 'data:') &&
            !filter_var($attrs['file'], FILTER_VALIDATE_URL) &&
            !file_exists($attrs['file']) &&
            !file_exists(
                ($attrs['basedir'] ?? $_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR . $attrs['file']
            )
        ) {
            $this->expectException(NotReadableException::class);
        }

        $ret = $this->smarty->fetch('eval:urlencode:' . $src);

        if (!isset($attrs['return']) || ($attrs['return'] === 'image')) {
            $crawler = new Crawler($ret);
            $imgs = $crawler->filterXPath(
                (isset($attrs['href']) || isset($attrs['link'])) ? '//body/a/img' : '//body/img'
            );
            $this->assertSame(1, $imgs->count(), 'Response should return <img /> tag, but returned something else.');
            $this->assertEmpty($imgs->attr('alt'), "<img /> tag should be returned with empty alt");
            $this->assertNotEmpty($imgs->attr('src'), "<img /> tag should be returned with non-empty src");
            $this->assertStringStartsWith(
                'data:',
                $imgs->attr('src') ?? '',
                "<img /> should return data: type src"
            );
            $this->assertNull($imgs->attr('link'), '<img /> should not have "link" attribute');
            $this->assertNull($imgs->attr('href'), '<img /> should not have "href" attribute');
            $this->assertNull($imgs->attr('basedir'), '<img /> should not have "basedir" attribute');
            if (isset($attrs['href'])) {
                $links = $crawler->filterXPath('//body/a');
                $this->assertSame(
                    1,
                    $links->count(),
                    "Because 'href' attribute specified, <a /> tag should also returned"
                );
                $this->assertSame(
                    $attrs['href'],
                    $links->attr('href'),
                    "Returned link should have same 'href' as specified 'href'"
                );
                $this->assertSame(1, $links->children()->count(), 'The link should have one children (1)');
            }
            if (isset($attrs['link'])) {
                $links = $crawler->filterXPath('//body/a');
                $this->assertSame(
                    1,
                    $links->count(),
                    "Because 'link' attribute specified, <a /> tag should also returned"
                );
                $this->assertSame(
                    $attrs['link'],
                    $links->attr('href'),
                    "Returned link should have same 'href' as specified 'link'"
                );
                $this->assertSame(1, $links->children()->count(), 'The link should have one children (2)');
            }
        } elseif ($attrs['return'] === 'url') {
            $this->assertStringStartsWith('data:', $ret, "Link should be returned in data: URI format");
        } else {
            $this->assertSame('???', $ret, "If unknown return is specified, result should be '???'");
        }
    }
}
