[![Build Status](https://travis-ci.com/voku/Simple-PHP-Code-Parser.svg?branch=master)](https://travis-ci.com/voku/Simple-PHP-Code-Parser)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/2feaf2a179a24a5fac99cbf67e72df2f)](https://www.codacy.com/manual/voku/Simple-PHP-Code-Parser?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=voku/Simple-PHP-Code-Parser&amp;utm_campaign=Badge_Grade)
[![Latest Stable Version](https://poser.pugx.org/voku/Simple-PHP-Code-Parser/v/stable)](https://packagist.org/packages/voku/simple-php-code-parser) 
[![Total Downloads](https://poser.pugx.org/voku/simple-php-code-parser/downloads)](https://packagist.org/packages/voku/simple-php-code-parser) 
[![License](https://poser.pugx.org/voku/simple-php-code-parser/license)](https://packagist.org/packages/voku/simple-php-code-parser)
[![Donate to this project using Paypal](https://img.shields.io/badge/paypal-donate-yellow.svg)](https://www.paypal.me/moelleken)
[![Donate to this project using Patreon](https://img.shields.io/badge/patreon-donate-yellow.svg)](https://www.patreon.com/voku)

# ❤ Simple PHP Code Parser

You can simply scan a string, a file or a full directory and you can see a simple data structure from your php code.
- Classes (**PHPClass**)
- Properties (**PHPProperties**)
- Constants (**PHPConst**)
- Methods (**PHPMethod**)
- Interfaces (**PHPInterface**)
- Traits (**PHPTrait**)
- Functions (**PHPFunction**)
- Parameter (**PHPParameter**)

This code is forked from [JetBrains/phpstorm-stubs](https://github.com/JetBrains/phpstorm-stubs/tree/master/tests) but you can't use the classes from "phpstorm-stubs" directly, 
because they are in a test namespace and the autoloader is "autoload-dev", so here is a extended version.

We will use:
- "PHP-Parser"
- "BetterReflection"
- "phpDocumentor"
- "PHPStan/phpdoc-parser"

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

Parse one class:
```php
$phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getFromClassName(Dummy::class);
$phpClasses = $phpCode->getClasses();

var_dump($phpClasses[Dummy::class]); // "PHPClass"-object

var_dump($phpClasses[Dummy::class]->methods); // "PHPMethod[]"-objects

var_dump($phpClasses[Dummy::class]->methods['withoutPhpDocParam']); // "PHPMethod"-object

var_dump($phpClasses[Dummy::class]->methods['withoutPhpDocParam']->parameters); // "PHPParameter[]"-objects

var_dump($phpClasses[Dummy::class]->methods['withoutPhpDocParam']->parameters['useRandInt']); // "PHPParameter"-object

var_dump($phpClasses[Dummy::class]->methods['withoutPhpDocParam']->parameters['useRandInt']->type); // "bool"
````

Parse one file:
```php
$phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy.php');
$phpClasses = $phpCode->getClasses();

var_dump($phpClasses[Dummy::class]); // "PHPClass"-object

var_dump($phpClasses[Dummy::class]->methods); // "PHPMethod[]"-objects

var_dump($phpClasses[Dummy::class]->methods['withoutPhpDocParam']); // "PHPMethod"-object

var_dump($phpClasses[Dummy::class]->methods['withoutPhpDocParam']->parameters); // "PHPParameter[]"-objects

var_dump($phpClasses[Dummy::class]->methods['withoutPhpDocParam']->parameters['useRandInt']); // "PHPParameter"-object

var_dump($phpClasses[Dummy::class]->methods['withoutPhpDocParam']->parameters['useRandInt']->type); // "bool"
````

Parse many files:
```php
$phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getPhpFiles(__DIR__ . '/src');
$phpClasses = $phpCode->getClasses();

var_dump($phpClasses[Dummy::class]); // "PHPClass"-object
````

Check types:
```php
$code = '<?php
namespace voku\tests;
class SimpleClass {
    public $foo;
    public int $foo1;
    private $foo2;
}';

$phpCodeErrors = \voku\SimplePhpParser\Parsers\PhpCodeChecker::checkFromString($code, ['public']);

var_dump($phpCodeErrors); // ['missing property type for voku\tests\SimpleClass->$foo']
```

### "phar" build

```php ~/Downloads/box.phar compile --debug```

### Support

For support and donations please visit [Github](https://github.com/voku/simple_html_dom/) | [Issues](https://github.com/voku/simple_html_dom/issues) | [PayPal](https://paypal.me/moelleken) | [Patreon](https://www.patreon.com/voku).

For status updates and release announcements please visit [Releases](https://github.com/voku/simple_html_dom/releases) | [Twitter](https://twitter.com/suckup_de) | [Patreon](https://www.patreon.com/voku/posts).

For professional support please contact [me](https://about.me/voku).

### Thanks

- Thanks to [GitHub](https://github.com) (Microsoft) for hosting the code and a good infrastructure including Issues-Managment, etc.
- Thanks to [IntelliJ](https://www.jetbrains.com) as they make the best IDEs for PHP and they gave me an open source license for PhpStorm!
- Thanks to [Travis CI](https://travis-ci.com/) for being the most awesome, easiest continous integration tool out there!
- Thanks to [StyleCI](https://styleci.io/) for the simple but powerfull code style check.
- Thanks to [PHPStan](https://github.com/phpstan/phpstan) && [Psalm](https://github.com/vimeo/psalm) for really great Static analysis tools and for discover bugs in the code!
