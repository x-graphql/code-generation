<?php

declare(strict_types=1);

use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcher;
use XGraphQL\Codegen\Console\GenerateCommand;
use XGraphQL\Codegen\Console\InitConfigCommand;
use XGraphQL\Codegen\Generator;

use function Symfony\Component\String\u;

(new class {

    private Application $app;

    public function __construct()
    {
        $autoloadFiles = [
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/../../../autoload.php',
        ];

        $autoloaderFound = false;

        foreach ($autoloadFiles as $autoloadFile) {
            if (!file_exists($autoloadFile)) {
                continue;
            }

            require_once $autoloadFile;

            $autoloaderFound = true;
        }

        if (!$autoloaderFound) {
            fwrite(STDERR, 'vendor/autoload.php could not be found. Did you run `composer install`?' . PHP_EOL);

            exit(1);
        }

        $this->prepareApp();
    }

    private function prepareApp(): void
    {
        $app = $this->app = new Application('XGraphQL Codegen');
        $dispatcher = new EventDispatcher();

        $app->getDefinition()->addOption(
            new InputOption(
                'config-file',
                '-f',
                InputOption::VALUE_OPTIONAL,
                'XGraphQL x-graphql-codegen config file path',
                sprintf('%s/x-graphql-codegen.php', getcwd()),
            )
        );
        $app->addCommands([new GenerateCommand(), new InitConfigCommand()]);
        $app->setDispatcher($dispatcher);

        $dispatcher->addListener(ConsoleEvents::COMMAND, $this->prepareConfigFileForInitCommand(...));
        $dispatcher->addListener(ConsoleEvents::COMMAND, $this->prepareGeneratorsForCodegenCommand(...));
    }

    public function run(): void
    {
        exit($this->app->run());
    }

    private function prepareConfigFileForInitCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        if (!$command instanceof InitConfigCommand) {
            return;
        }

        $configFile = $event->getInput()->getOption('config-file');

        $command->setConfigFile($configFile);
    }

    private function prepareGeneratorsForCodegenCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        if (!$command instanceof GenerateCommand) {
            return;
        }

        $configFile = $event->getInput()->getOption('config-file');

        if (!file_exists($configFile)) {
            throw new InvalidOptionException(
                sprintf('Not found config file: `%s`, use `x-graphql:codegen:init-config` to generate it', $configFile)
            );
        }

        $config = require $configFile;
        $generators = [];

        foreach ($config as $name => $item) {
            $generators[$name] = $this->createGenerator($name, $item);
        }

        $command->setGenerators($generators);
    }

    private function createGenerator(string $name, array $config): Generator
    {
        if (!isset($config['namespace'], $config['destinationPath'], $config['sourcePath'])) {
            throw new \InvalidArgumentException('Mandatory fields is missing');
        }

        $defaultQueryClassName = u($name . 'Query')->camel()->title(true)->toString();

        return new Generator(
            $config['namespace'],
            $config['sourcePath'],
            $config['destinationPath'],
            $config['queryClassName'] ?? $defaultQueryClassName,
        );
    }
})->run();
