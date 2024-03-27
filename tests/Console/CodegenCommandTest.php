<?php

declare(strict_types=1);

namespace XGraphQL\Codegen\Test\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use XGraphQL\Codegen\Console\CodegenCommand;
use XGraphQL\Codegen\Generator;

class CodegenCommandTest extends TestCase
{
    public function testRunCommand()
    {
        $command = new CodegenCommand();
        $command->setGenerators(
            [
                'test' => new Generator(
                    '',
                    __DIR__ . '/../fixtures/source_dir',
                    __DIR__ . '/../generated',
                )
            ]
        );
        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertMatchesRegularExpression('~Generated PHP code for `test` successful~', $tester->getDisplay());
    }

    public function testRunCommandWithEmptyGenerators()
    {
        $command = new CodegenCommand();
        $command->setGenerators([]);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertEmpty($tester->getDisplay());
    }
}
