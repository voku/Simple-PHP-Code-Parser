[![Build Status](https://travis-ci.com/voku/Simple-PHP-Code-Parser.svg?branch=master)](https://travis-ci.com/voku/Simple-PHP-Code-Parser)
[![Coverage Status](https://coveralls.io/repos/github/voku/Simple-PHP-Code-Parser/badge.svg?branch=master)](https://coveralls.io/github/voku/Simple-PHP-Code-Parser?branch=master)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/2feaf2a179a24a5fac99cbf67e72df2f)](https://www.codacy.com/manual/voku/Simple-PHP-Code-Parser?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=voku/Simple-PHP-Code-Parser&amp;utm_campaign=Badge_Grade)
[![Latest Stable Version](https://poser.pugx.org/voku/Simple-PHP-Code-Parser/v/stable)](https://packagist.org/packages/voku/simple-php-code-parser) 
[![Total Downloads](https://poser.pugx.org/voku/simple-php-code-parser/downloads)](https://packagist.org/packages/voku/simple-php-code-parser) 
[![License](https://poser.pugx.org/voku/simple-php-code-parser/license)](https://packagist.org/packages/voku/simple-php-code-parser)
[![Donate to this project using Paypal](https://img.shields.io/badge/paypal-donate-yellow.svg)](https://www.paypal.me/moelleken)
[![Donate to this project using Patreon](https://img.shields.io/badge/patreon-donate-yellow.svg)](https://www.patreon.com/voku)

# ❤ Simple PHP Code Parser

You can simple scan a string, a file or a full directory. This code is mostly copy&pasted from [JetBrains/phpstorm-stubs](https://github.com/JetBrains/phpstorm-stubs/tree/master/tests) ;)

### Install via "composer require"

```shell
composer require voku/simple-php-code-parser
```

### Quick Start

Parse a string:
```php
$code = '
<?php
namespace voku\tests;
class SimpleClass {}
$obja = new class() {};
$objb = new class {};
class AnotherClass {}
';
$phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getFromString($code);
$phpClasses = $phpCode->getClasses();

var_dump($phpClasses['voku\tests\SimpleClass']); // "PHPClass"-object
```

Parse one file:
```php
$phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy.php');
$phpClasses = $phpCode->getClasses();

var_dump($phpClasses[Dummy::class]); // "PHPClass"-object
````

Parse many files:
```php
$phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getPhpFiles(__DIR__ . '/src');
$phpClasses = $phpCode->getClasses();

var_dump($phpClasses[Dummy::class]); // "PHPClass"-object
````

### Support

For support and donations please visit [Github](https://github.com/voku/simple_html_dom/) | [Issues](https://github.com/voku/simple_html_dom/issues) | [PayPal](https://paypal.me/moelleken) | [Patreon](https://www.patreon.com/voku).

For status updates and release announcements please visit [Releases](https://github.com/voku/simple_html_dom/releases) | [Twitter](https://twitter.com/suckup_de) | [Patreon](https://www.patreon.com/voku/posts).

For professional support please contact [me](https://about.me/voku).

### Thanks

- Thanks to [GitHub](https://github.com) (Microsoft) for hosting the code and a good infrastructure including Issues-Managment, etc.
- Thanks to [IntelliJ](https://www.jetbrains.com) as they make the best IDEs for PHP and they gave me an open source license for PhpStorm!
- Thanks to [Travis CI](https://travis-ci.com/) for being the most awesome, easiest continous integration tool out there!
- Thanks to [StyleCI](https://styleci.io/) for the simple but powerfull code style check.
- Thanks to [PHPStan](https://github.com/phpstan/phpstan) && [Psalm](https://github.com/vimeo/psalm) for relly great Static analysis tools and for discover bugs in the code!
w
