<?php

declare(strict_types=1);

namespace XGraphQL\Codegen\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use XGraphQL\Codegen\Exception\RuntimeException;
use XGraphQL\Codegen\Generator;

class GeneratorTest extends TestCase
{
    private const GENERATED_PATH = __DIR__ . '/generated';

    protected function setUp(): void
    {
        $fileSystem = new Filesystem();

        $fileSystem->remove(self::GENERATED_PATH);
        $fileSystem->mkdir(self::GENERATED_PATH);

        parent::setUp();
    }

    public function testUseInvalidSourcePathWillThrowException(): void
    {
        $generator = new Generator('', '', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('~does not exists or not have read permission$~');

        $generator->generate();
    }

    public function testEmptyQueryWillThrowException(): void
    {
        $generator = new Generator('', __DIR__ . '/fixtures/empty_source_dir', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('~^Not found any query~');

        $generator->generate();
    }

    public function testInvalidDestinationPathWillThrowException(): void
    {
        $generator = new Generator('', __DIR__ . '/fixtures/source_dir', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('~exists and have write permission$~');

        $generator->generate();
    }

    public function testCanGenerate(): void
    {
        $queryName = uniqid('Q');
        $generator = new Generator(
            '',
            __DIR__ . '/fixtures/source_dir',
            self::GENERATED_PATH,
            $queryName,
        );
        $generator->generate();

        $this->assertFileExists(sprintf('%s/%s.php', self::GENERATED_PATH, $queryName));
        $this->assertFileExists(sprintf('%s/%s/GetUsersTrait.php', self::GENERATED_PATH, $queryName));
        $this->assertFileExists(sprintf('%s/%s/GetCountryTrait.php', self::GENERATED_PATH, $queryName));
    }
}
