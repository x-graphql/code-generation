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
