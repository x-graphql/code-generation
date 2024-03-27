<?php

declare(strict_types=1);

namespace XGraphQL\Codegen\Test\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use XGraphQL\Codegen\Console\InitConfigCommand;

class InitConfigCommandTest extends TestCase
{
    private const CONFIG_FILE = __DIR__ . '/../generated/config.php';

    protected function setUp(): void
    {
        $fileSystem = new Filesystem();

        $fileSystem->remove(self::CONFIG_FILE);
        $fileSystem->mkdir(dirname(self::CONFIG_FILE));

        parent::setUp();
    }

    public function testInitConfig(): void
    {
        $this->assertFileDoesNotExist(self::CONFIG_FILE);

        $command = new InitConfigCommand();
        $command->setConfigFile(self::CONFIG_FILE);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists(self::CONFIG_FILE);
        $this->assertFileEquals(__DIR__ . '/../../resources/config.template.php', self::CONFIG_FILE);
    }

    public function testInitConfigExisted(): void
    {
        touch(self::CONFIG_FILE);
        $this->assertFileExists(self::CONFIG_FILE);

        $command = new InitConfigCommand();
        $command->setConfigFile(self::CONFIG_FILE);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertEmpty(file_get_contents(self::CONFIG_FILE));

        $tester->setInputs(['yes']);
        $tester->execute([]);

        $this->assertFileEquals(__DIR__ . '/../../resources/config.template.php', self::CONFIG_FILE);
    }
}
