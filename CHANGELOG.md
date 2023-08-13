# Changelog

### 0.20.1 (2023-08-11)

- use "phpstan/phpdoc-parser" for more phpdocs

### 0.20.0 (2023-07-31)

- add support for "readonly" properties
- replace deprecated "parts" property from nikic/PHP-Parser
- use "phpstan/phpdoc-parser" as fallback for parameters e.g. for `callable()` types
- use php types
- add more tests


### 0.19.6 (2022-09-23)

- try to fix autoload #11
- inheritDoc: use all types (not only from phpdoc) from parent classes & interfaces
- add more tests


### 0.19.5 (2022-08-30)

- support "::class" from PHP 8


### 0.19.4 (2022-08-30)

- fix fatal error for non existing classes


### 0.19.3 (2022-08-27)

- update "phpdocumentor/type-resolver" (with support for int-ranges)
- clean-up some errors reported by phpstan


### 0.19.2  (2022-02-15)

- quick-fix for missing namespaces in types


### 0.19.1  (2022-02-15)

- optimize NULL detecting from reflection for types
- "BasePHPClass" -> add more information from reflection
- "PHPConst" -> add "visibility"


### 0.19.0  (2022-02-02)

- use native php reflection, instead of (slower) better reflection
- remove "react/filesystem" (not working with phar files)

### 0.18.2  (2021-11-29)

- temporary fix for phpdoc like `int<0,1>`
- update dependencies

### 0.18.1  (2021-10-22)

- update dependencies

### 0.18.0  (2021-10-03)

- update dependencies

### 0.17.0  (2021-07-27)

- update dependencies

### 0.16.6  (2020-12-26)

- "PhpCodeParser" -> optimize exception handling of "amphp/parallel" for async code analyse per file

### 0.16.5  (2020-12-26)

- "PhpCodeParser" -> ignore exceptions from auto loaded external classes

### 0.16.4 (2020-11-18)

- "PhpCodeParser" -> allow different file extensions

### 0.16.3 (2020-10-31)

- fix php variable detection

### 0.16.2 (2020-10-30)

- save the raw phpdoc for "@return" and "@param" v2

### 0.16.1 (2020-10-30)

- save the raw phpdoc for "@return" and "@param"

### 0.16.0 (2020-10-30)

- support for modern phpdocs for "@param" and "@return"

### 0.15.3 (2020-10-06)

- update vendor libs
- PHP 7.2 as minimal requirement
- remove custom PseudoTypes for phpDocumentor (PseudoTypes are now build in into phpDocumentor itself)
- add more tests for "PhpCodeParser->getFunctionsInfo()"

### 0.15.2 (2020-09-04)

- save special phpdoc @tags and the content of these tags

### 0.15.1 (2020-09-04)

- optimize performance via static cache

### 0.15.0 (2020-08-31)

- move "PhpCodeChecker" in a separate repository
- move "PhpCodeDumpApi" in a separate repository
- clean-up vendor dependencies

### 0.14.0 (2020-08-23)

- "PhpCodeCheckerCommand" -> allow to exclude some files
- optimize parallel execution (cpu cores === max parallel executions)
- update vendor (phpdocumentor & better-reflection)

### 0.13.2 (2020-08-07)

- "PhpCodeParser" -> add "getFromClassName()"

### 0.13.1 (2020-07-21)

- "PhpCodeParser" -> fix for inheritdoc comments

### 0.13.0 (2020-07-16)

- "PHPTrait" -> add support for "Trait"-files

### 0.12.0 (2020-07-05)

- "PhpCodeParser" -> fix cache key
- "PhpCodeChecker" -> fix the autoloader parameter

### 0.11.0 (2020-06-18)

- "PHPInterface" -> fix recursion
- "PhpCodeParser" -> analyse only ".php" files in the given path

### 0.10.0 (2020-06-18)

- "PhpCodeParser" -> ignore errors outside the current file-path-scope
- "PhpCodeParser" -> use more generic autoloader logic
- "PhpCodeChecker" -> fix more inheritdoc errors

### 0.9.0 (2020-06-16) 

- "PhpCodeChecker" -> check wrong phpdocs from class properties
- "PhpCodeParser" -> allow to exclude some files

### 0.8.0 (2020-06-16)

- replace PhpReflection with BetterReflection v2
- fix bugs reported by PhpStan & PhpCodeCheck

### 0.7.0 (2020-06-02)

- replace PhpReflection with BetterReflection

### 0.6.0 (2020-05-25)

- "PhpCodeParser" -> fetch phpdoc-data for @inheritdoc

### 0.5.0 (2020-05-25)

- "ParserErrorHandler" -> show more parsing errors in the results
- "PHPInterface" -> fix PhpReflection usage
- "PHPDefineConstant" -> fix php warning

### 0.4.2 (2020-05-23)

- "PhpCodeChecker" -> fix "$skipMixedTypesAsError" usage 

### 0.4.1 (2020-05-23)

- "PHPFunction" -> fix phpdoc parsing error

### 0.4.0 (2020-05-23)

- add default values for parameter and properties
- add types from default values
- normalize types (e.g. double => float)

### 0.3.0 (2020-05-20)

- "PHPClass" fix phpdoc
- add "PhpCodeChecker"-class

### 0.2.1 (2020-05-20)

- fix code issues reported by psalm + phpstan

### 0.2.0 (2020-05-19)

- use "amphp/parallel" for async code analyse per file

### 0.1.0 (2020-05-14)

- init (forked from "JetBrains/phpstorm-stubs")
- add "PHPProperties"
- add "PHP Reflection" AND / OR "nikic/PHP-Parser"
- get phpdocs types via phpDocumentor (+ psalm)
