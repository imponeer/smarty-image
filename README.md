[![License](https://img.shields.io/github/license/imponeer/smarty-image.svg)](LICENSE)
[![GitHub release](https://img.shields.io/github/release/imponeer/smarty-image.svg)](https://github.com/imponeer/smarty-image/releases) [![Maintainability](https://api.codeclimate.com/v1/badges/a231cf43fba22907f5a3/maintainability)](https://codeclimate.com/github/imponeer/smarty-image/maintainability) [![PHP](https://img.shields.io/packagist/php-v/imponeer/smarty-image.svg)](http://php.net) 
[![Packagist](https://img.shields.io/packagist/dm/imponeer/smarty-image.svg)](https://packagist.org/packages/imponeer/smarty-image)

# Smarty Image

Some [Smarty](https://smarty.net) syntax plugins for operations with images.

## Installation

To install and use this package, we recommend to use [Composer](https://getcomposer.org):

```bash
composer require imponeer/smarty-image
```

Otherwise, you need to include manually files from `src/` directory. 

## Registering in Smarty

If you want to use these extensions from this package in your project you need register them with [`registerPlugin` function](https://www.smarty.net/docs/en/api.register.plugin.tpl) from [Smarty](https://www.smarty.net). For example:
```php
$smarty = new \Smarty();
$resizeImagePlugin = new \Imponeer\Smarty\Extensions\Image\$resizeImagePlugin($psrCacheAdapter);
$smarty->registerPlugin('function', $resizeImagePlugin->getName(), [$resizeImagePlugin, 'execute']);
```

## Using from templates

To resize images from smarty You can use resized_image function:
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

## How to contribute?

If you want to add some functionality or fix bugs, you can fork, change and create pull request. If you not sure how this works, try [interactive GitHub tutorial](https://try.github.io).

If you found any bug or have some questions, use [issues tab](https://github.com/imponeer/smarty-image/issues) and write there your questions.