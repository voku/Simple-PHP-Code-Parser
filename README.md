
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