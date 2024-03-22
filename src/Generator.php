<?php

declare(strict_types=1);

namespace XGraphQL\Codegen;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use Nette\PhpGenerator\PsrPrinter;
use RegexIterator;
use XGraphQL\Codegen\Exception\RuntimeException;

use function Symfony\Component\String\u;

final readonly class Generator
{
    private Splitter $splitter;

    private PsrPrinter $psrPrinter;

    public function __construct(
        private Schema $schema,
        private string $namespace,
        private string $sourcePath,
        private string $destinationPath,
        private string $queryClassName = 'GraphQLQuery',
    ) {
        $this->splitter = new Splitter();
        $this->psrPrinter = new PsrPrinter();
    }

    public function generate()
    {
        $ast = $this->parseSource();

        $errors = DocumentValidator::validate($this->schema, $ast, DocumentValidator::defaultRules());

        if (0 < count($errors)) {
            throw $errors[0];
        }

        $traitsPath = sprintf('%s/%s', $this->destinationPath, $this->queryClassName);

        foreach ($this->splitter->split($ast) as $operationName => $subAst) {
            $this->generateOperationTrait($operationName, $subAst, $traitsPath);
        }
    }

    private function generateOperationTrait(string $name, DocumentNode $ast, array $traitsPath): void
    {
        $traitName = sprintf('%sTrait', u($name)->snake()->title()->toString());
    }

    private function parseSource(): DocumentNode
    {
        $src = '';
        $dir = rtrim($this->sourcePath, DIRECTORY_SEPARATOR);

        if (!is_dir($dir)) {
            throw new RuntimeException(
                sprintf('Directory `%s` does not exists', $dir)
            );
        }

        $dirIterator = new \RegexIterator(
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)),
            '/^.+\.(graphql|gql)$/i',
            RegexIterator::GET_MATCH
        );

        foreach ($dirIterator as $matches) {
            $src = file_get_contents($matches[0]) . PHP_EOL;
        }

        return Parser::parse($src, ['noLocation' => true]);
    }

    private function ensureDestinationPath(): void
    {
    }
}
