<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\Helper\AstDeclarationFinder;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/** @internal */
final class AstDeclarationFinderTest extends TestCase
{
    private const SOURCE = <<<'PHP'
<?php

namespace App\Demo;

final class Alpha
{
    public function run(): void
    {
    }

    public function Other(): object
    {
        return new class() {
            public function run(): void
            {
            }
        };
    }
}

interface Beta
{
}
PHP;

    public function testFindsClassLikesByFullyQualifiedName(): void
    {
        $ast = PhpCodeParser::getAstFromString(self::SOURCE);

        static::assertCount(1, AstDeclarationFinder::classLikes($ast, '\App\Demo\alpha'));
        static::assertCount(1, AstDeclarationFinder::classLikes($ast, 'App\Demo\Beta'));
        static::assertSame([], AstDeclarationFinder::classLikes($ast, 'Alpha'));
        static::assertSame('App\Demo\Alpha', AstDeclarationFinder::classLikeFqn(AstDeclarationFinder::classLikes($ast, 'App\Demo\Alpha')[0]));
    }

    public function testFindsMethodsByNameAndOptionalLineSpan(): void
    {
        $ast = PhpCodeParser::getAstFromString(self::SOURCE);

        static::assertCount(2, AstDeclarationFinder::methods($ast, 'RUN'));
        static::assertCount(1, AstDeclarationFinder::methods($ast, 'run', 7, 9));
        static::assertSame([], AstDeclarationFinder::methods($ast, 'run', 7, 10));
        static::assertSame([], AstDeclarationFinder::methods($ast, 'missing'));
    }

    public function testAnonymousClassHasNoFullyQualifiedName(): void
    {
        $classes = (new \PhpParser\NodeFinder())->findInstanceOf(PhpCodeParser::getAstFromString(self::SOURCE), \PhpParser\Node\Stmt\Class_::class);
        $anonymous = array_values(array_filter($classes, static fn ($class): bool => $class->name === null));

        static::assertCount(1, $anonymous);
        static::assertNull(AstDeclarationFinder::classLikeFqn($anonymous[0]));
    }
}
