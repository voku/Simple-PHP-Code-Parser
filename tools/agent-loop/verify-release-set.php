<?php

declare(strict_types=1);

const AGENT_LOOP_PACKAGE = 'voku/agent-loop';
const OWNED_AGENT_PACKAGES = [
    'voku/agent-kanban',
    'voku/agent-learning',
    'voku/agent-map',
    'voku/agent-recall-compiler',
    'voku/agent-session',
];
const STALE_SIBLING_RELEASE_FIELDS = [
    'agent_kanban_release',
    'agent_learning_release',
    'agent_map_release',
    'agent_recall_compiler_release',
    'agent_session_release',
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

    $duplicatePackages = array_keys(array_intersect_key($require, $requireDev));
    if ($duplicatePackages !== []) {
        sort($duplicatePackages, SORT_STRING);
        throw new \RuntimeException(sprintf(
            '%s must not declare the same package in both require and require-dev: %s.',
            $argv[2],
            implode(', ', $duplicatePackages),
        ));
    }

    $rootRequirements = $require + $requireDev;

    $expectedAgentLoop = requireString($toolchain, 'agent_loop_release', $argv[1]);
    $actualAgentLoop = $rootRequirements[AGENT_LOOP_PACKAGE] ?? null;
    if ($actualAgentLoop !== $expectedAgentLoop) {
        throw new \RuntimeException(sprintf(
            '%s must require %s %s; got %s.',
            $argv[2],
            AGENT_LOOP_PACKAGE,
            $expectedAgentLoop,
            $actualAgentLoop ?? '<missing>',
        ));
    }

    foreach (OWNED_AGENT_PACKAGES as $package) {
        if (isset($rootRequirements[$package])) {
            throw new \RuntimeException(sprintf(
                '%s must not constrain %s directly; %s owns the first-party release set.',
                $argv[2],
                $package,
                AGENT_LOOP_PACKAGE,
            ));
        }
    }

    foreach (STALE_SIBLING_RELEASE_FIELDS as $field) {
        if (array_key_exists($field, $toolchain)) {
            throw new \RuntimeException(sprintf(
                '%s.toolchain.%s duplicates transitive release-set authority; record the resolved lock instead.',
                $argv[1],
                $field,
            ));
        }
    }

    if ($argc === 4) {
        $lock = readJsonObject($argv[3]);
        $resolved = resolvedVersions($lock, $argv[3]);
        $resolvedAgentLoop = $resolved[AGENT_LOOP_PACKAGE] ?? null;
        if ($resolvedAgentLoop !== $expectedAgentLoop) {
            throw new \RuntimeException(sprintf(
                'Resolved %s must be %s; got %s.',
                AGENT_LOOP_PACKAGE,
                $expectedAgentLoop,
                $resolvedAgentLoop ?? '<missing>',
            ));
        }

        $releaseSet = [AGENT_LOOP_PACKAGE => $resolvedAgentLoop];
        foreach (OWNED_AGENT_PACKAGES as $package) {
            $version = $resolved[$package] ?? null;
            if ($version === null) {
                throw new \RuntimeException('Resolved release set is missing ' . $package . '.');
            }
            $releaseSet[$package] = $version;
        }
        ksort($releaseSet, SORT_STRING);

        fwrite(STDOUT, json_encode(
            ['resolved_agent_release_set' => $releaseSet],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . PHP_EOL);
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
        throw new \RuntimeException('Cannot read ' . $path . '.');
    }

    $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new \RuntimeException($path . ' must contain a JSON object.');
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
        throw new \RuntimeException(sprintf('%s.%s must be an object.', $path, $key));
    }

    return $value;
}

/** @param array<string, mixed> $data */
function requireString(array $data, string $key, string $path): string
{
    $value = $data[$key] ?? null;
    if (!is_string($value) || $value === '') {
        throw new \RuntimeException(sprintf('%s.%s must be a non-empty string.', $path, $key));
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
        throw new \RuntimeException(sprintf('%s.%s must be an object.', $path, $section));
    }

    $result = [];
    foreach ($requirements as $package => $constraint) {
        if (!is_string($package) || !is_string($constraint)) {
            throw new \RuntimeException(sprintf('%s.%s must contain string package constraints.', $path, $section));
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
            throw new \RuntimeException(sprintf('%s.%s must be an array.', $path, $section));
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
