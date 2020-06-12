<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\CliCommand;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PhpCodeDumpApi extends Command
{
    public const COMMAND_NAME = 'php-code-dump-api';

    /**
     * @var string[]
     */
    private $composerAutoloaderProjectPaths = [];

    /**
     * @param string[] $composerAutoloaderProjectPaths
     */
    public function __construct(array $composerAutoloaderProjectPaths)
    {
        parent::__construct();

        $this->composerAutoloaderProjectPaths = $composerAutoloaderProjectPaths;
    }

    public function configure(): void
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Dump a summary of the class API of PHP classes in a directory')
            ->setDefinition(
                new InputDefinition(
                    [
                        new InputArgument('path', InputArgument::REQUIRED, 'The path to analyse'),
                        new InputArgument('autoload-file', InputArgument::OPTIONAL, 'The path to your autoloader'),
                    ]
                )
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        \assert(\is_string($path));
        $realPath = \realpath($path);
        \assert(\is_string($realPath));

        $autoloadPath = $input->getArgument('autoload-file');
        \assert(\is_string($autoloadPath) || $autoloadPath === null);
        if ($autoloadPath) {
            $autoloadRealPath = \realpath($autoloadPath);
            \assert(\is_string($autoloadRealPath));

            $this->composerAutoloaderProjectPaths[] = $autoloadRealPath;
        }

        $formatter = $output->getFormatter();
        $formatter->setStyle('class', new OutputFormatterStyle('green', null, ['bold']));
        $formatter->setStyle('method', new OutputFormatterStyle('red', null, []));
        $formatter->setStyle('type', new OutputFormatterStyle('cyan', null, []));
        $formatter->setStyle('parameter', new OutputFormatterStyle('yellow', null, []));

        $phpParser = \voku\SimplePhpParser\Parsers\PhpCodeParser::getPhpFiles(
            $realPath,
            $this->composerAutoloaderProjectPaths
        );

        $banner = \sprintf('List of classes and their public API in : %s', $realPath);
        $output->writeln(\str_repeat('=', \strlen($banner)));
        $output->writeln($banner);
        $output->writeln(\str_repeat('=', \strlen($banner)));
        $output->writeln('');

        foreach ($phpParser->getClasses() as $class) {
            $output->writeln('');
            $output->writeln(\sprintf('<class>%s</class>', $class->name));

            foreach ($class->methods as $method) {
                if ($method->access !== 'public') {
                    continue;
                }

                $output->writeln(\sprintf(
                    '  %s<method>%s</method>(%s) : <type>%s</type>',
                    $method->is_static ? '::' : '->',
                    $method->name,
                    \implode(
                        ', ',
                        \array_map(
                            static function (\voku\SimplePhpParser\Model\PHPParameter $methodParameter): string {
                                return \trim(\sprintf(
                                    '<type>%s</type> <parameter>$%s</parameter>',
                                    (string) $methodParameter->getType(),
                                    $methodParameter->name
                                ));
                            },
                            $method->parameters
                        )
                    ),
                    $method->getReturnType() ?? 'void'
                ));
            }
        }

        $output->writeln('');

        return 0;
    }
}
