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
        $cache = new class extends Cache {
            public bool $writeAttempted = false;

            public function __construct()
            {
            }

            public function getCacheIsReady(): bool
            {
                return false;
            }

            public function setItem(string $key, $value, $ttl = 0): bool
            {
                $this->writeAttempted = true;

                return false;
            }
        };

        self::writeCache($cache);

        static::assertFalse($cache->writeAttempted);
    }

    public function testFalseCacheWriteStatusIsNonFatal(): void
    {
        $cache = new class extends Cache {
            public bool $writeAttempted = false;

            public function __construct()
            {
            }

            public function getCacheIsReady(): bool
            {
                return true;
            }

            public function setItem(string $key, $value, $ttl = 0): bool
            {
                $this->writeAttempted = true;

                return false;
            }
        };

        self::writeCache($cache);

        static::assertTrue($cache->writeAttempted);
    }

    public function testCacheWriteWarningIsNotSuppressed(): void
    {
        $cache = new class extends Cache {
            public function __construct()
            {
            }

            public function getCacheIsReady(): bool
            {
                return true;
            }

            public function setItem(string $key, $value, $ttl = 0): bool
            {
                \trigger_error('cache write failed', \E_USER_WARNING);

                return false;
            }
        };

        \set_error_handler(
            static function (int $severity, string $message, string $file, int $line): never {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
        );

        try {
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessage('cache write failed');

            self::writeCache($cache);
        } finally {
            \restore_error_handler();
        }
    }

    private static function writeCache(Cache $cache): void
    {
        $method = new \ReflectionMethod(PhpCodeParser::class, 'writeCache');
        $method->setAccessible(true);
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
