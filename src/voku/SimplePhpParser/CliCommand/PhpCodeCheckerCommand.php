<?php

/** @noinspection TransitiveDependenciesUsageInspection */

declare(strict_types=1);

namespace voku\SimplePhpParser\CliCommand;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            )
            ->addOption(
                'access',
                null,
                InputOption::VALUE_OPTIONAL,
                'Check for "public|protected|private" methods.',
                'public|protected|private'
            )
            ->addOption(
                'skip-mixed-types-as-error',
                null,
                InputOption::VALUE_OPTIONAL,
                'Skip check for mixed types. (false or true)',
                'false'
            )
            ->addOption(
                'skip-deprecated-functions',
                null,
                InputOption::VALUE_OPTIONAL,
                'Skip check for deprecated functions / methods. (false or true)',
                'false'
            )
            ->addOption(
                'skip-functions-with-leading-underscore',
                null,
                InputOption::VALUE_OPTIONAL,
                'Skip check for functions / methods with leading underscore. (false or true)',
                'false'
            )
            ->addOption(
                'skip-parse-errors',
                null,
                InputOption::VALUE_OPTIONAL,
                'Skip parse errors in the output. (false or true)',
                'true'
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

        $access = $input->getOption('access');
        \assert(\is_string($access));
        $access = (array) \explode('|', $access);

        $skipMixedTypesAsError = (bool) $input->getOption('skip-mixed-types-as-error');
        $skipDeprecatedFunctions = (bool) $input->getOption('skip-deprecated-functions');
        $skipFunctionsWithLeadingUnderscore = (bool) $input->getOption('skip-functions-with-leading-underscore');
        $skipParseErrorsAsError = (bool) $input->getOption('skip-parse-errors');

        $formatter = $output->getFormatter();
        $formatter->setStyle('file', new OutputFormatterStyle('default', null, ['bold']));
        $formatter->setStyle('error', new OutputFormatterStyle('red', null, []));

        $banner = \sprintf('List of errors in : %s', $realPath);
        $output->writeln(\str_repeat('=', \strlen($banner)));
        $output->writeln($banner);
        $output->writeln(\str_repeat('=', \strlen($banner)));
        $output->writeln('');

        $errors = \voku\SimplePhpParser\Parsers\PhpCodeChecker::checkPhpFiles(
            $realPath,
            $access,
            $skipMixedTypesAsError,
            $skipDeprecatedFunctions,
            $skipFunctionsWithLeadingUnderscore,
            $skipParseErrorsAsError,
            $this->composerAutoloaderProjectPaths
        );

        $errorCount = 0;
        foreach ($errors as $file => $errorsInner) {
            $errorCountFile = \count($errorsInner);
            $errorCount += $errorCountFile;

            $output->writeln('<file>' . $file . '</file>' . ' (' . $errorCountFile . ' errors)');

            foreach ($errorsInner as $errorInner) {
                $output->writeln('<error>' . $errorInner . '</error>');
            }

            /** @noinspection DisconnectedForeachInstructionInspection */
            $output->writeln('');
        }

        $output->writeln('-------------------------------');
        $output->writeln($errorCount . ' errors in ' . \count($errors) . ' files.');
        $output->writeln('-------------------------------');

        return 0;
    }
}
