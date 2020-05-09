[![Build Status](https://travis-ci.org/voku/Simple-PHP-Code-Parser.svg?branch=master)](https://travis-ci.org/voku/Simple-PHP-Code-Parser)
[![Coverage Status](https://coveralls.io/repos/github/voku/Simple-PHP-Code-Parser/badge.svg?branch=master)](https://coveralls.io/github/voku/Simple-PHP-Code-Parser?branch=master)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/2feaf2a179a24a5fac99cbf67e72df2f)](https://www.codacy.com/manual/voku/Simple-PHP-Code-Parser?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=voku/Simple-PHP-Code-Parser&amp;utm_campaign=Badge_Grade)
[![Latest Stable Version](https://poser.pugx.org/voku/Simple-PHP-Code-Parser/v/stable)](https://packagist.org/packages/voku/simple-php-code-parser) 
[![Total Downloads](https://poser.pugx.org/voku/simple-php-code-parser/downloads)](https://packagist.org/packages/voku/simple-php-code-parser) 
[![License](https://poser.pugx.org/voku/simple-php-code-parser/license)](https://packagist.org/packages/voku/simple-php-code-parser)
[![Donate to this project using Paypal](https://img.shields.io/badge/paypal-donate-yellow.svg)](https://www.paypal.me/moelleken)
[![Donate to this project using Patreon](https://img.shields.io/badge/patreon-donate-yellow.svg)](https://www.patreon.com/voku)

# ❤ Simple PHP Code Parser

This code is mostly copy&pasted from [JetBrains/phpstorm-stubs](https://github.com/JetBrains/phpstorm-stubs/tree/master/tests) ;)

You can simple scan a string, a file or a full directory.

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