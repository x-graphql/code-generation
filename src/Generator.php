<?php

declare(strict_types=1);

namespace XGraphQL\Codegen;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use XGraphQL\Codegen\Exception\RuntimeException;
use XGraphQL\Delegate\SchemaDelegator;
use XGraphQL\Delegate\SchemaDelegatorInterface;

use function Symfony\Component\String\u;

final readonly class Generator
{
    private Splitter $splitter;

    private PsrPrinter $psrPrinter;

    private Dumper $dumper;

    public function __construct(
        private string $namespace,
        private string $sourcePath,
        private string $destinationPath,
        private string $queryClassName = 'GraphQLQuery',
    ) {
        $this->splitter = new Splitter();
        $this->psrPrinter = new PsrPrinter();
        $this->dumper = new Dumper();
    }


    /**
     * @throws \Exception
     */
    public function generate(): void
    {
        $ast = $this->parseSource();
        $traits = [];

        foreach ($this->splitter->split($ast) as [$operation, $fragments]) {
            /**
             * @var OperationDefinitionNode $operation
             * @var array<string, FragmentDefinitionNode> $fragments
             */
            $traits[] = $this->generateOperationTrait($operation, $fragments);
        }

        $this->generateQuery($traits);
    }

    private function parseSource(): DocumentNode
    {
        $src = '';
        $dir = rtrim($this->sourcePath, DIRECTORY_SEPARATOR);

        if (!is_dir($dir) || !is_readable($dir)) {
            throw new RuntimeException(
                sprintf('Directory `%s` does not exists or not have permission to read files', $dir)
            );
        }

        $dirIterator = new \RegexIterator(
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)),
            '/^.+\.(graphql|gql)$/i',
            \RegexIterator::GET_MATCH
        );

        foreach ($dirIterator as $matches) {
            $src = file_get_contents($matches[0]) . PHP_EOL;
        }

        if ('' === $src) {
            throw new RuntimeException(sprintf('Not found any query in `%s` source path', $this->sourcePath));
        }

        return Parser::parse($src, ['noLocation' => true]);
    }


    /**
     * @param OperationDefinitionNode $operation
     * @param array<string, FragmentDefinitionNode> $fragments
     * @return string
     */
    private function generateOperationTrait(OperationDefinitionNode $operation, array $fragments): string
    {
        $operationNormalized = AST::toArray($operation);
        $fragmentsNormalized = array_map(fn (FragmentDefinitionNode $fragment) => AST::toArray($fragment), $fragments);
        $operationName = $operation->name->value;
        $hasVariables = 0 < $operation->variableDefinitions->count();
        $file = $this->generatePhpFile();

        $namespaceName = sprintf('%s\\%s', $this->namespace, $this->queryClassName);
        $namespace = $file->addNamespace($namespaceName);

        $namespace->addUse(Promise::class);
        $namespace->addUse(AST::class);
        $namespace->addUse(ExecutionResult::class);
        $namespace->addUse(SyncPromiseAdapter::class);
        $namespace->addUse(OperationDefinitionNode::class);
        $namespace->addUse(FragmentDefinitionNode::class);
        $namespace->addUse($namespaceName);

        $traitName = u(sprintf('%sTrait', $operationName))->camel()->title(true)->toString();
        $trait = $namespace->addTrait($traitName);

        $trait->addComment(sprintf('@mixin %s', $this->queryClassName));

        $asyncMethodName = u($operationName . 'Async')->camel()->toString();
        $syncMethodName = u($operationName)->camel()->toString();
        $asyncMethod = $trait->addMethod($asyncMethodName);
        $syncMethod = $trait->addMethod($syncMethodName);

        foreach ([$syncMethod, $asyncMethod] as $method) {
            if ($hasVariables) {
                $method->addParameter('variables', [])->setType('array');
            }

            $method->addComment('@throws \Exception');
            $method->addComment('@throws \JsonException');
        }

        $asyncMethod->setBody(
            sprintf(
                <<<'PHP'
/** @var OperationDefinitionNode $operation */
$operation = AST::fromArray(%s);

/** @var array<string, FragmentDefinitionNode> $fragments */
$fragments = array_map(
    fn (array $astNormalized) => AST::fromArray($astNormalized),
    %s,
);

$schema = $this->delegator->getSchema();

return $this->delegator->delegateToExecute($schema, $operation, $fragments%s);
PHP,
                $this->dumper->dump($operationNormalized),
                $this->dumper->dump($fragmentsNormalized),
                $hasVariables ? ', $variables' : ''
            )
        );
        $asyncMethod->setReturnType(Promise::class);

        $syncMethod->setBody(
            sprintf(
                <<<'PHP'
$promiseAdapter = $this->delegator->getPromiseAdapter();

if (!$promiseAdapter instanceof SyncPromiseAdapter) {
  throw new \RuntimeException(sprintf('Expect promise adapter should be sync adapter but received %%s', $promiseAdapter::class));
}

$promise = $this->%s(%s);

return $promiseAdapter->wait($promise);
PHP,
                $asyncMethodName,
                $hasVariables ? '$variables' : '',
            )
        );
        $syncMethod->setReturnType(ExecutionResult::class);

        $this->writePhpFile($file);

        return array_key_first($file->getClasses());
    }

    private function generateQuery(array $traits): void
    {
        $file = $this->generatePhpFile();
        $namespace = $file->addNamespace($this->namespace);
        $class = $namespace->addClass($this->queryClassName)->setFinal();

        $namespace->addUse(Schema::class);
        $namespace->addUse(SchemaDelegatorInterface::class);
        $namespace->addUse(SchemaDelegator::class);

        if (method_exists($class, 'setReadOnly')) {
            $class->setReadOnly();
        }

        $class->addProperty('delegator')->setPrivate()->setType(SchemaDelegatorInterface::class);

        $constructor = $class->addMethod('__construct');

        $constructor->addParameter('schemaOrDelegator')->setType(
            sprintf('%s|%s', SchemaDelegatorInterface::class, Schema::class)
        );

        $constructor->setBody(
            <<<'PHP'
if ($schemaOrDelegator instanceof Schema) {
    $schemaOrDelegator = new SchemaDelegator($schemaOrDelegator);
}

$this->delegator = $schemaOrDelegator;
PHP
        );

        foreach ($traits as $trait) {
            $namespace->addUse($trait);
            $class->addTrait($trait);
        }

        $this->writePhpFile($file);
    }

    private function generatePhpFile(): PhpFile
    {
        $file = new PhpFile();
        $file->setStrictTypes();
        $file->addComment('Generated file, please don\'t edit by hand');

        return $file;
    }

    private function writePhpFile(PhpFile $file): void
    {
        $destPath = rtrim($this->destinationPath, DIRECTORY_SEPARATOR);

        if (!is_dir($destPath) || !is_writable($destPath)) {
            throw new \RuntimeException(
                sprintf(
                    'Please make sure destination path: `%s` exists and have write permission before generate code',
                    $destPath
                )
            );
        }

        $class = array_key_first($file->getClasses());
        $classPath = str_replace([$this->namespace, '\\'], ['', DIRECTORY_SEPARATOR], $class);
        $path = sprintf(
            '%s/%s.php',
            $destPath,
            ltrim($classPath, DIRECTORY_SEPARATOR)
        );
        $info = pathinfo($path);

        @mkdir($info['dirname']);

        file_put_contents($path, $this->psrPrinter->printFile($file));
    }
}
