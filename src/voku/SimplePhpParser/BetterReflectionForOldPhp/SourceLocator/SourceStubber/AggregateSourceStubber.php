<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\SourceStubber;

class AggregateSourceStubber implements SourceStubber
{
    /**
     * @var SourceStubber[]
     */
    private $sourceStubbers;

    /**
     * @param SourceStubber $sourceStubber
     * @param SourceStubber ...$otherSourceStubbers
     */
    public function __construct(SourceStubber $sourceStubber, SourceStubber ...$otherSourceStubbers)
    {
        $this->sourceStubbers = \array_merge([$sourceStubber], $otherSourceStubbers);
    }

    /**
     * @param string $className
     *
     * @return StubData|null
     */
    public function generateClassStub(string $className): ?StubData
    {
        foreach ($this->sourceStubbers as $sourceStubber) {
            $stubData = $sourceStubber->generateClassStub($className);

            if ($stubData !== null) {
                return $stubData;
            }
        }

        return null;
    }

    /**
     * @param string $functionName
     *
     * @return StubData|null
     */
    public function generateFunctionStub(string $functionName): ?StubData
    {
        foreach ($this->sourceStubbers as $sourceStubber) {
            $stubData = $sourceStubber->generateFunctionStub($functionName);

            if ($stubData !== null) {
                return $stubData;
            }
        }

        return null;
    }

    /**
     * @param string $constantName
     *
     * @return StubData|null
     */
    public function generateConstantStub(string $constantName): ?StubData
    {
        return \array_reduce($this->sourceStubbers, static function (?StubData $stubData, SourceStubber $sourceStubber) use ($constantName): ?StubData {
            return $stubData ?? $sourceStubber->generateConstantStub($constantName);
        }, null);
    }
}
