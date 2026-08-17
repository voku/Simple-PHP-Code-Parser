<?php

declare(strict_types=1);

use RuntimeException;

const AGENT_LOOP_PACKAGE = 'voku/agent-loop';
const OWNED_AGENT_PACKAGES = [
    'agent_kanban_release' => 'voku/agent-kanban',
    'agent_learning_release' => 'voku/agent-learning',
    'agent_map_release' => 'voku/agent-map',
    'agent_recall_compiler_release' => 'voku/agent-recall-compiler',
    'agent_session_release' => 'voku/agent-session',
];

if ($argc < 3 || $argc > 4) {
    fwrite(STDERR, "Usage: php tools/agent-loop/verify-release-set.php <issue.json> <composer.json> [composer.lock]\n");
    exit(2);
}

try {
    $issue = readJsonObject($argv[1]);
    $composer = readJsonObject($argv[2]);
    $toolchain = requireArray($issue, 'toolchain', $argv[1]);
    $require = stringRequirements($composer['require'] ?? [], 'require', $argv[2]);
    $requireDev = stringRequirements($composer['require-dev'] ?? [], 'require-dev', $argv[2]);
    $rootRequirements = $require + $requireDev;

    $expectedAgentLoop = requireString($toolchain, 'agent_loop_release', $argv[1]);
    $actualAgentLoop = $rootRequirements[AGENT_LOOP_PACKAGE] ?? null;
    if ($actualAgentLoop !== $expectedAgentLoop) {
        throw new RuntimeException(sprintf(
            '%s must require %s %s; got %s.',
            $argv[2],
            AGENT_LOOP_PACKAGE,
            $expectedAgentLoop,
            $actualAgentLoop ?? '<missing>',
        ));
    }

    foreach (OWNED_AGENT_PACKAGES as $package) {
        if (isset($rootRequirements[$package])) {
            throw new RuntimeException(sprintf(
                '%s must not constrain %s directly; %s owns the first-party release set.',
                $argv[2],
                $package,
                AGENT_LOOP_PACKAGE,
            ));
        }
    }

    if ($argc === 4) {
        $lock = readJsonObject($argv[3]);
        $resolved = resolvedVersions($lock, $argv[3]);
        foreach (OWNED_AGENT_PACKAGES as $field => $package) {
            $expected = requireString($toolchain, $field, $argv[1]);
            $actual = $resolved[$package] ?? null;
            if ($actual !== $expected) {
                throw new RuntimeException(sprintf(
                    'Resolved %s must be %s; got %s.',
                    $package,
                    $expected,
                    $actual ?? '<missing>',
                ));
            }
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

/** @return array<string, mixed> */
function readJsonObject(string $path): array
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException('Cannot read ' . $path . '.');
    }

    $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException($path . ' must contain a JSON object.');
    }

    return $decoded;
}

/**
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function requireArray(array $data, string $key, string $path): array
{
    $value = $data[$key] ?? null;
    if (!is_array($value)) {
        throw new RuntimeException(sprintf('%s.%s must be an object.', $path, $key));
    }

    return $value;
}

/** @param array<string, mixed> $data */
function requireString(array $data, string $key, string $path): string
{
    $value = $data[$key] ?? null;
    if (!is_string($value) || $value === '') {
        throw new RuntimeException(sprintf('%s.%s must be a non-empty string.', $path, $key));
    }

    return $value;
}

/**
 * @param mixed $requirements
 * @return array<string, string>
 */
function stringRequirements(mixed $requirements, string $section, string $path): array
{
    if (!is_array($requirements)) {
        throw new RuntimeException(sprintf('%s.%s must be an object.', $path, $section));
    }

    $result = [];
    foreach ($requirements as $package => $constraint) {
        if (!is_string($package) || !is_string($constraint)) {
            throw new RuntimeException(sprintf('%s.%s must contain string package constraints.', $path, $section));
        }
        $result[$package] = $constraint;
    }

    return $result;
}

/**
 * @param array<string, mixed> $lock
 * @return array<string, string>
 */
function resolvedVersions(array $lock, string $path): array
{
    $resolved = [];
    foreach (['packages', 'packages-dev'] as $section) {
        $packages = $lock[$section] ?? [];
        if (!is_array($packages)) {
            throw new RuntimeException(sprintf('%s.%s must be an array.', $path, $section));
        }
        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }
            $name = $package['name'] ?? null;
            $version = $package['version'] ?? null;
            if (is_string($name) && is_string($version)) {
                $resolved[$name] = $version;
            }
        }
    }

    return $resolved;
}
