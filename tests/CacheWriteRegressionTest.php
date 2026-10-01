<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\cache\Cache;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

final class CacheWriteRegressionTest extends TestCase
{
    public function testUnavailableCacheDoesNotAttemptWrite(): void
    {
        $cache = new CacheWriteProbe(false);

        self::writeCache($cache);

        static::assertSame(0, $cache->writes);
    }

    public function testFalseCacheWriteStatusIsNonFatal(): void
    {
        $cache = new CacheWriteProbe(true);

        self::writeCache($cache);

        static::assertSame(1, $cache->writes);
    }

    public function testCacheWriteWarningIsNotSuppressed(): void
    {
        $cache = new CacheWriteProbe(true, true);

        $previousErrorReporting = \error_reporting();
        \error_reporting($previousErrorReporting | \E_USER_WARNING);

        \set_error_handler(
            static function (int $severity, string $message, string $file, int $line): bool {
                if ((\error_reporting() & $severity) === 0) {
                    return false;
                }

                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
        );

        try {
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessage('cache write failed');

            self::writeCache($cache);
        } finally {
            \restore_error_handler();
            \error_reporting($previousErrorReporting);
        }
    }

    private static function writeCache(Cache $cache): void
    {
        $method = new \ReflectionMethod(PhpCodeParser::class, 'writeCache');
        $method->invoke(
            null,
            $cache,
            'cache-key',
            [
                'content'  => '<?php',
                'fileName' => '/tmp/example.php',
                'cacheKey' => 'cache-key',
            ]
        );
    }
}

final class CacheWriteProbe extends Cache
{
    public int $writes = 0;

    public function __construct(
        private readonly bool $ready,
        private readonly bool $warn = false
    ) {
    }

    public function getCacheIsReady(): bool
    {
        return $this->ready;
    }

    public function setItem(string $key, $value, $ttl = 0): bool
    {
        ++$this->writes;

        if ($this->warn) {
            \trigger_error('cache write failed', \E_USER_WARNING);
        }

        return false;
    }
}
