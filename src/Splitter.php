<?php

declare(strict_types=1);

namespace XGraphQL\Codegen;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Printer;
use XGraphQL\Codegen\Exception\RuntimeException;


final readonly class Splitter
{
    public function split(DocumentNode $ast): iterable
    {
        /**
         * @var array<string, OperationDefinitionNode> $operations
         * @var array<string, FragmentDefinitionNode> $fragments
         */
        $operations = $fragments = [];

        foreach ($ast->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $fragments[$definition->name->value] = $definition;
            }

            if ($definition instanceof OperationDefinitionNode) {
                $operationName = $definition->name?->value;

                if (null === $operationName) {
                    throw new RuntimeException(
                        sprintf('Operation `%s` should have name', Printer::doPrint($definition))
                    );
                }

                if (isset($operations[$operationName])) {
                    throw new RuntimeException(sprintf('Duplicated operation name: `%`', $operationName));
                }

                $operations[$operationName] = $definition;
            }

            throw new RuntimeException(
                sprintf('Not support generate PHP code from `%s`', Printer::doPrint($definition))
            );
        }

        foreach ($operations as $name => $definition) {
            $operationFragments = $this->collectOperationFragments($definition->selectionSet, $fragments);

            yield $name => new DocumentNode(
                [
                    'definitions' => new NodeList(
                        [$definition, ...array_values($operationFragments)]
                    )
                ]
            );
        }
    }

    /**
     * @param SelectionSetNode $selectionSet
     * @param array<string, FragmentDefinitionNode> $fragments
     * @param string[] $visitedFragments
     * @return array<string, FragmentDefinitionNode>
     */
    private function collectOperationFragments(
        SelectionSetNode $selectionSet,
        array $fragments,
        array &$visitedFragments = []
    ): array {
        $result = [];

        foreach ($selectionSet->selections as $selection) {
            $subSelectionSet = null;

            if ($selection instanceof FieldNode) {
                $subSelectionSet = $selection->selectionSet;
            }

            if ($selection instanceof FragmentSpreadNode) {
                $name = $selection->name->value;

                if (isset($visitedFragments[$name])) {
                    continue;
                }

                if (!isset($fragments[$name])) {
                    throw new RuntimeException(
                        sprintf('Missing fragment %s do you forgot to define it?', $name)
                    );
                }

                $fragment = $result[$name] = $fragments[$name];
                $subSelectionSet = $fragment->selectionSet;
                $visitedFragments[$name] = true;
            }

            if ($selection instanceof InlineFragmentNode) {
                $subSelectionSet = $selection->selectionSet;
            }

            if (null !== $subSelectionSet) {
                $result += $this->collectFragments($subSelectionSet, $fragments, $visitedFragments);
            }
        }

        return $result;
    }

}
