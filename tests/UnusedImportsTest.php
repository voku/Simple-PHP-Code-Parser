<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\Helper\UnusedImports;

/** @internal */
final class UnusedImportsTest extends TestCase
{
    public function testReportsOnlyImportsNothingReferences(): void
    {
        $unused = UnusedImports::find(<<<'PHP'
<?php

namespace App;

use Lib\Used;
use Lib\Gone;
use Lib\Other as Renamed;
use Lib\Doc;
use Lib\Attr;
use Lib\Ns;
use Lib\Member;

/** @return Doc */
#[Attr]
final class Demo
{
    public function run(): Used
    {
        return new Used(Ns\Part::make(), $this->Member(), self::Gone);
    }
}
PHP);

        static::assertSame(['Gone', 'Renamed', 'Member'], array_column($unused, 'alias'));
        static::assertSame(['Lib\Gone', 'Lib\Other', 'Lib\Member'], array_column($unused, 'import'));
        static::assertSame([6, 7, 11], array_column($unused, 'line'));
    }

    public function testGroupedFunctionAndConstImportsAreNotJudged(): void
    {
        static::assertSame([], UnusedImports::find("<?php\n\nuse Lib\\{A, B};\nuse function Lib\\fn1;\nuse const Lib\\C;\n\nfinal class Demo {}\n"));
    }
}
