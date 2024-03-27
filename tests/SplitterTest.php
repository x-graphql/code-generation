<?php

declare(strict_types=1);

namespace XGraphQL\Codegen\Test;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\Parser;
use PHPUnit\Framework\TestCase;
use XGraphQL\Codegen\Exception\RuntimeException;
use XGraphQL\Codegen\Splitter;

class SplitterTest extends TestCase
{
    public function testSplitTypeSystemWillThrowException(): void
    {
        $ast = Parser::parse(
            <<<'GQL'
type Query {
  dummy: String!
}
GQL
        );
        $splitter = new Splitter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('~^Not support generate PHP code from~');

        iterator_to_array($splitter->split($ast));
    }

    public function testSplitUnnamedOperation(): void
    {
        $ast = Parser::parse(
            <<<'GQL'
query {
  test
}

query A {
  b
}
GQL
        );
        $splitter = new Splitter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('~should have name$~');

        iterator_to_array($splitter->split($ast));
    }

    public function testDuplicateOperation(): void
    {
        $ast = Parser::parse(
            <<<'GQL'
query duplicated {
  test
}

query duplicated {
  b
}
GQL
        );
        $splitter = new Splitter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('~^Duplicate operation~');

        iterator_to_array($splitter->split($ast));
    }

    public function testMissingFragment(): void
    {
        $ast = Parser::parse(
            <<<'GQL'
query A {
  test
}

query B {
  ...MissingFragment
}
GQL
        );
        $splitter = new Splitter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('~^Missing fragment~');

        iterator_to_array($splitter->split($ast));
    }

    public function testSplit(): void
    {
        $fragmentUserInfo = Parser::fragmentDefinition(
            'fragment UserInfo on User { name country { ...CountryInfo } }'
        );
        $fragmentCountryInfo = Parser::fragmentDefinition('fragment CountryInfo on Country { name flag }');
        $operationGetUser = Parser::operationDefinition(
            'query getUser { user { email ...UserInfo } }'
        );
        $operationGetCountry = Parser::operationDefinition(
            'query getCountry { ...CountryInfo }'
        );
        $ast = new DocumentNode(
            [
                'definitions' => new NodeList(
                    [
                        $fragmentUserInfo,
                        $fragmentCountryInfo,
                        $operationGetUser,
                        $operationGetCountry
                    ]
                )
            ]
        );
        $splitter = new Splitter();
        $items = iterator_to_array($splitter->split($ast));
        $expects = [
            [$operationGetUser, ['UserInfo' => $fragmentUserInfo, 'CountryInfo' => $fragmentCountryInfo]],
            [$operationGetCountry, ['CountryInfo' => $fragmentCountryInfo]],
        ];

        $this->assertCount(count($expects), $items);

        foreach ($expects as $pos => [$expectOperation, $expectFragments]) {
            [$actualOperation, $actualFragments] = $items[$pos];

            $this->assertSame($expectOperation, $actualOperation);
            $this->assertSame($expectFragments, $actualFragments);
        }
    }
}
