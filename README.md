[![License](https://img.shields.io/github/license/imponeer/smarty-image.svg)](LICENSE) [![GitHub release](https://img.shields.io/github/release/imponeer/smarty-image.svg)](https://github.com/imponeer/smarty-image/releases) [![PHP](https://img.shields.io/packagist/php-v/imponeer/smarty-image.svg)](http://php.net) [![Packagist](https://img.shields.io/packagist/dm/imponeer/smarty-image.svg)](https://packagist.org/packages/imponeer/smarty-image) [![Smarty version requirement](https://img.shields.io/packagist/dependency-v/imponeer/smarty-image/smarty%2Fsmarty)](https://smarty-php.github.io)

# Smarty Image

A modern [Smarty](https://smarty.net) extension that provides image resizing capabilities with built-in caching. This extension allows you to resize images directly from your Smarty templates using the `resized_image` function.

## Installation

To install and use this package, we recommend to use [Composer](https://getcomposer.org):

```bash
composer require imponeer/smarty-image
```

Otherwise, you need to include manually files from `src/` directory.

## Setup

### Basic Setup

For Smarty v5 and newer, use the new extension system:

```php
$smarty = new \Smarty\Smarty();
// For $psrCacheAdapter value use PSR-6 cache adapter, for example Symfony\Component\Cache\Adapter\ArrayAdapter
$smarty->addExtension(
    new \Imponeer\Smarty\Extensions\Image\SmartyImageExtension($psrCacheAdapter)
);
```

For older Smarty use [v2.0 version of this plugin](https://github.com/imponeer/smarty-image/tree/v2.0.2).

### Using with Symfony Container

When using Symfony's dependency injection container, you can register the extension as a service:

```yaml
# config/services.yaml
services:
    Imponeer\Smarty\Extensions\Image\SmartyImageExtension:
        arguments:
            $cache: '@cache.app'
        tags:
            - { name: 'smarty.extension' }
```

Then inject it into your Smarty instance:

```php
// In your controller or service
public function __construct(
    private Smarty $smarty,
    private SmartyImageExtension $imageExtension
) {
    $this->smarty->addExtension($this->imageExtension);
}
```

### Using with PHP-DI

With PHP-DI container, configure the extension in your container definitions:

```php
use Psr\Cache\CacheItemPoolInterface;
use Imponeer\Smarty\Extensions\Image\SmartyImageExtension;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

return [
    CacheItemPoolInterface::class => \DI\create(FilesystemAdapter::class),
    SmartyImageExtension::class => \DI\create()
        ->constructor(\DI\get(CacheItemPoolInterface::class)),

    Smarty::class => \DI\factory(function (SmartyImageExtension $imageExtension) {
        $smarty = new Smarty();
        $smarty->addExtension($imageExtension);
        return $smarty;
    })
];
```

### Using with League Container

With League Container, register the services like this:

```php
use League\Container\Container;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Imponeer\Smarty\Extensions\Image\SmartyImageExtension;

$container = new Container();

$container->add(CacheItemPoolInterface::class, ArrayAdapter::class);
$container->add(SmartyImageExtension::class)
    ->addArgument(CacheItemPoolInterface::class);

$container->add(Smarty::class)
    ->addMethodCall('addExtension', [SmartyImageExtension::class]);
```

## Usage

To resize images from Smarty templates, you can use the `resized_image` function:
```smarty
  {resized_image file="/images/image.jpg" height=70}
```

This function supports such arguments:

| Argument | Required | Default value | Description |
|----------|----------|---------------|-------------|
| `file`     | yes      |               | Image file to resize |
| `width`    | if `height` is not specified | | Resized image width |
| `height`   | if `width` is not specified | | Resized image height |
| `fit`     | no | `outside` | Method used for resize. Supported `fill`, `inside`, `outside` |
| `href` or `link`    | no | | if specified and `return` is set to `image`, will output generated HTML as image with link to this specific location |
| `basedir` | no | $_SERVER['DOCUMENT_ROOT'] | Base dir where to look for image files |
| `return` | no | `image` | Returns result as HTML tag if value is `image`, or as resized image URI if value is `url`.  |

All extra arguments will be rendered into image tag, if return mode is `image`.

## Development

This project uses modern PHP development tools and practices:

### Running Tests
```bash
composer test
```

### Code Style
The project follows PSR-12 coding standards. Check code style with:
```bash
composer phpcs
```

Fix code style issues automatically:
```bash
composer phpcbf
```

### Static Analysis
Run PHPStan for static code analysis:
```bash
composer phpstan
```

## Documentation

API documentation is automatically generated and available in the [project's wiki](https://github.com/imponeer/smarty-image/wiki). For more detailed information about the classes and methods, please refer to the [project wiki](https://github.com/imponeer/smarty-image/wiki).

## How to contribute?

We welcome contributions! If you want to add functionality or fix bugs:

1. Fork the repository
2. Create a feature branch from `main`
3. Make your changes following the coding standards
4. Add or update tests as needed
5. Run the test suite to ensure everything works
6. Submit a pull request with a clear description of your changes

For bug reports or feature requests, please use the [issues tab](https://github.com/imponeer/smarty-image/issues) and provide as much detail as possible.
