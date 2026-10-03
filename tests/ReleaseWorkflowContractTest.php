<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;

/** @internal */
final class ReleaseWorkflowContractTest extends TestCase
{
    public function testReleaseWorkflowKeepsFailClosedAuthorityGuards(): void
    {
        $workflowPath = dirname(__DIR__) . '/.github/workflows/release-tag.yml';
        $workflow = file_get_contents($workflowPath);

        self::assertIsString($workflow);
        self::assertStringContainsString('expected_sha:', $workflow);
        self::assertStringContainsString("actions: read", $workflow);
        self::assertStringContainsString("contents: write", $workflow);
        self::assertStringContainsString(
            "uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1",
            $workflow,
        );
        self::assertDoesNotMatchRegularExpression('/uses:\s+actions\/checkout@v\d+/', $workflow);

        self::assertStringContainsString(
            'ref: ${{ inputs.expected_sha }}',
            $workflow,
        );
        self::assertStringContainsString(
            'head_sha=$EXPECTED_SHA&event=push&status=success&per_page=1',
            $workflow,
        );
        self::assertGreaterThanOrEqual(
            2,
            substr_count($workflow, 'git fetch origin master --tags --force'),
            'master must be checked during validation and immediately before tag creation.',
        );
        self::assertStringContainsString(
            'git tag "$VERSION" "$EXPECTED_SHA"',
            $workflow,
        );
        self::assertStringContainsString(
            'git push origin "refs/tags/$VERSION"',
            $workflow,
        );
        self::assertStringNotContainsString(
            'git tag "$VERSION"' . "\n",
            $workflow,
            'Release tags must never rely on implicit HEAD.',
        );
    }
}
