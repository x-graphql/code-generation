<?php

declare(strict_types=1);

namespace XGraphQL\Codegen\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'x-graphql:codegen:init-config', description: 'Help to create config file')]
final class InitConfigCommand extends Command
{
    public const TEMPLATE_FILE_PATH = __DIR__ . '/../../resources/config.template.php';

    private readonly string $configFile;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        if (file_exists($this->configFile)) {
            $overwriteConfirm = $style->confirm(
                sprintf('Config file: %s already exist, do you want to overwrite it?', $this->configFile),
                false
            );

            if (!$overwriteConfirm) {
                return self::SUCCESS;
            }
        }

        copy(self::TEMPLATE_FILE_PATH, $this->configFile);

        $style->info(sprintf('Init config file: `%s` successful', $this->configFile));
        $style->info('Now you can generate code with command: `x-graphql:codegen:generate`');

        return self::SUCCESS;
    }

    public function setConfigFile(string $configFile): void
    {
        $this->configFile = $configFile;
    }
}
