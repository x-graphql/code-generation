Codegen
=======

Generating PHP code for executing GraphQL

![unit tests](https://github.com/x-graphql/codegen/actions/workflows/unit_tests.yml/badge.svg)
[![codecov](https://codecov.io/gh/x-graphql/codegen/graph/badge.svg?token=IYPjngBdMK)](https://codecov.io/gh/x-graphql/codegen)

Getting Started
---------------

Install this package via [Composer](https://getcomposer.org)

```shell
composer require x-graphql/codegen
```

Usages
------

After install, you need to generate config file with command bellow:

```shell
./vendor/bin/x-graphql-codegen x-graphql:codegen:init-config
```

Your config file `x-graphql-codegen.php` initialized look like:

```php 
<?php

return [
    'default' => [
        /// PSR 4 namespace, classes and traits generated will use.
        'namespace' => 'App\GraphQL\Codegen',
        /// Where storing GraphQL queries files.
        'sourcePath' => __DIR__ . '/',
        /// Where storing PHP generated code with namespace above.
        'destinationPath' => __DIR__ . '/',
        /// Generated query class name.
        'queryClassName' => 'GraphQLQuery',
    ]
];
```

Edit it for suitable with your environment and create some graphql query files in `sourcePath`
then generate PHP code with command:

```shell
./vendor/bin/x-graphql-codegen x-graphql:codegen:generate
```

Credits
-------

Created by [Minh Vuong](https://github.com/vuongxuongminh)
