<?php

/** @noinspection TransitiveDependenciesUsageInspection */

declare(strict_types=1);

namespace voku\SimplePhpParser\CliCommand;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PhpCodeCheckerCommand extends Command
{
    public const COMMAND_NAME = 'php-code-checker';

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
            ->setDescription('Check PHP files or directories for missing types.')
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
        $formatter->setStyle('file', new OutputFormatterStyle('default', null, ['bold']));
        $formatter->setStyle('error', new OutputFormatterStyle('red', null, []));

        $banner = \sprintf('List of errors in : %s', $realPath);
        $output->writeln(\str_repeat('=', \strlen($banner)));
        $output->writeln($banner);
        $output->writeln(\str_repeat('=', \strlen($banner)));
        $output->writeln('');

        $access = ['public', 'protected', 'private'];
        $skipMixedTypesAsError = false;
        $skipDeprecatedMethods = false;
        $skipFunctionsWithLeadingUnderscore = false;

        $errors = \voku\SimplePhpParser\Parsers\PhpCodeChecker::checkPhpFiles(
            $realPath,
            $access,
            $skipMixedTypesAsError,
            $skipDeprecatedMethods,
            $skipFunctionsWithLeadingUnderscore,
            $this->composerAutoloaderProjectPaths
        );

        foreach ($errors as $file => $errorsInner) {
            $output->writeln('<file>' . $file . '</file>');

            foreach ($errorsInner as $errorInner) {
                $output->writeln('<error>' . $errorInner . '</error>');
            }

            /** @noinspection DisconnectedForeachInstructionInspection */
            $output->writeln('');
        }

        return 0;
    }
}
