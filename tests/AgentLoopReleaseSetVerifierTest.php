<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @internal */
final class AgentLoopReleaseSetVerifierTest extends TestCase
{
    public function testRejectsPackageDeclaredInRequireAndRequireDev(): void
    {
        $directory = sys_get_temp_dir() . '/simple-php-parser-release-set-' . bin2hex(random_bytes(4));
        if (!mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create test directory: ' . $directory);
        }

        $issuePath = $directory . '/issue.json';
        $composerPath = $directory . '/composer.json';

        try {
            self::writeJson($issuePath, [
                'toolchain' => [
                    'agent_loop_release' => '0.16.5',
                ],
            ]);
            self::writeJson($composerPath, [
                'require' => [
                    'voku/agent-loop' => '0.16.5',
                ],
                'require-dev' => [
                    'voku/agent-loop' => '0.16.4',
                ],
            ]);

            [$exitCode, $stdout, $stderr] = $this->execute([
                PHP_BINARY,
                dirname(__DIR__) . '/tools/agent-loop/verify-release-set.php',
                $issuePath,
                $composerPath,
            ]);

            self::assertSame(1, $exitCode, $stdout . $stderr);
            self::assertSame('', $stdout);
            self::assertStringContainsString(
                'must not declare the same package in both require and require-dev: voku/agent-loop',
                $stderr,
            );
        } finally {
            foreach ([$issuePath, $composerPath] as $path) {
                if (is_file($path) && !unlink($path)) {
                    throw new RuntimeException('Unable to remove test file: ' . $path);
                }
            }
            if (is_dir($directory) && !rmdir($directory)) {
                throw new RuntimeException('Unable to remove test directory: ' . $directory);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json . "\n") === false) {
            throw new RuntimeException('Unable to write test file: ' . $path);
        }
    }

    /**
     * @param list<string> $command
     *
     * @return array{0: int, 1: string, 2: string}
     */
    private function execute(array $command): array
    {
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start verifier process.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            $exitCode,
            is_string($stdout) ? $stdout : '',
            is_string($stderr) ? $stderr : '',
        ];
    }
}
