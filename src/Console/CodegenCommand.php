<?php

declare(strict_types=1);

namespace XGraphQL\Codegen\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XGraphQL\Codegen\Generator;

#[AsCommand(name: 'x-graphql:codegen', description: 'Generate PHP code for executing GraphQL')]
final class CodegenCommand extends Command
{
    /**
     * @var Generator[]
     */
    private readonly iterable $generators;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        foreach ($this->generators as $name => $generator) {
            $generator->generate();

            $style->info(sprintf('Generated PHP code for `%s` successful', $name));
        }

        return self::SUCCESS;
    }

    public function setGenerators(iterable $generators): void
    {
        $this->generators = $generators;
    }
}
