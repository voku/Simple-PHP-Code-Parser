<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp;

use PhpParser\Lexer\Emulative;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\ClassReflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\ConstantReflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\FunctionReflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Locator as AstLocator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Parser\MemoizingParser;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\SourceStubber\AggregateSourceStubber;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\SourceStubber\PhpStormStubsSourceStubber;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\SourceStubber\ReflectionSourceStubber;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\SourceStubber\SourceStubber;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\AggregateSourceLocator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\AutoloadSourceLocator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\EvaledCodeSourceLocator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\MemoizingSourceLocator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\PhpInternalSourceLocator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\SourceLocator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\FindReflectionOnLine;

final class BetterReflection
{
    /**
     * @var SourceLocator|null
     */
    private $sourceLocator;

    /**
     * @var ClassReflector|null
     */
    private $classReflector;

    /**
     * @var FunctionReflector|null
     */
    private $functionReflector;

    /**
     * @var ConstantReflector|null
     */
    private $constantReflector;

    /**
     * @var Parser|null
     */
    private $phpParser;

    /**
     * @var AstLocator|null
     */
    private $astLocator;

    /**
     * @var FindReflectionOnLine|null
     */
    private $findReflectionOnLine;

    /**
     * @var SourceStubber|null
     */
    private $sourceStubber;

    public function sourceLocator(): SourceLocator
    {
        $astLocator = $this->astLocator();
        $sourceStubber = $this->sourceStubber();

        return $this->sourceLocator
            ?? $this->sourceLocator = new MemoizingSourceLocator(new AggregateSourceLocator([
                new PhpInternalSourceLocator($astLocator, $sourceStubber),
                new EvaledCodeSourceLocator($astLocator, $sourceStubber),
                new AutoloadSourceLocator($astLocator, $this->phpParser()),
            ]));
    }

    public function classReflector(): ClassReflector
    {
        return $this->classReflector
            ?? $this->classReflector = new ClassReflector($this->sourceLocator());
    }

    public function functionReflector(): FunctionReflector
    {
        return $this->functionReflector
            ?? $this->functionReflector = new FunctionReflector($this->sourceLocator(), $this->classReflector());
    }

    public function constantReflector(): ConstantReflector
    {
        return $this->constantReflector
            ?? $this->constantReflector = new ConstantReflector($this->sourceLocator(), $this->classReflector());
    }

    public function phpParser(): Parser
    {
        return $this->phpParser
            ?? $this->phpParser = new MemoizingParser(
                (new ParserFactory())->create(ParserFactory::PREFER_PHP7, new Emulative([
                    'usedAttributes' => ['comments', 'startLine', 'endLine', 'startFilePos', 'endFilePos'],
                ]))
            );
    }

    public function astLocator(): AstLocator
    {
        return $this->astLocator
            ?? $this->astLocator = new AstLocator($this->phpParser(), function (): FunctionReflector {
                return $this->functionReflector();
            });
    }

    public function findReflectionsOnLine(): FindReflectionOnLine
    {
        return $this->findReflectionOnLine
            ?? $this->findReflectionOnLine = new FindReflectionOnLine($this->sourceLocator(), $this->astLocator());
    }

    public function sourceStubber(): SourceStubber
    {
        return $this->sourceStubber
            ?? $this->sourceStubber = new AggregateSourceStubber(
                new PhpStormStubsSourceStubber($this->phpParser()),
                new ReflectionSourceStubber()
            );
    }
}
