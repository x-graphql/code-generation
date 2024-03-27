<?php

declare(strict_types=1);

namespace XGraphQL\Codegen\Test\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use XGraphQL\Codegen\Console\CodegenCommand;
use XGraphQL\Codegen\Generator;

class CodegenCommandTest extends TestCase
{
    private const DESTINATION_PATH = __DIR__ . '/../generated';

    private const SOURCE_PATH = __DIR__ . '/../fixtures/source_dir';

    protected function setUp(): void
    {
        $fileSystem = new Filesystem();

        $fileSystem->remove(self::DESTINATION_PATH);
        $fileSystem->mkdir(self::DESTINATION_PATH);

        parent::setUp();
    }

    public function testRunCommand()
    {
        $command = new CodegenCommand();
        $command->setGenerators(
            [
                'test' => new Generator(
                    '',
                    self::SOURCE_PATH,
                    self::DESTINATION_PATH,
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
