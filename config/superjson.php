<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SuperJSON Transformers
    |--------------------------------------------------------------------------
    |
    | List of transformer classes that will be registered automatically.
    | You can add your own custom transformers here.
    |
    */
    'transformers' => [
        \SulimanBenhalim\LaravelSuperJson\Transformers\DateTransformer::class,
        \SulimanBenhalim\LaravelSuperJson\Transformers\BigIntTransformer::class,
        \SulimanBenhalim\LaravelSuperJson\Transformers\CollectionTransformer::class,
        \SulimanBenhalim\LaravelSuperJson\Transformers\MapTransformer::class,
        \SulimanBenhalim\LaravelSuperJson\Transformers\RegexTransformer::class,
        \SulimanBenhalim\LaravelSuperJson\Transformers\UrlTransformer::class,
        \SulimanBenhalim\LaravelSuperJson\Transformers\ErrorTransformer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Serialization Options
    |--------------------------------------------------------------------------
    |
    | Configure how SuperJSON handles serialization and deserialization.
    | These options control JSON encoding behavior.
    |
    */
    'options' => [
        'preserve_zero_fraction' => true,
        'unescaped_unicode' => true,
        'throw_on_error' => true,
        'max_depth' => 512, // JSON encoding depth limit
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Transform Routes
    |--------------------------------------------------------------------------
    |
    | Define route patterns that should automatically use SuperJSON
    | transformation via middleware.
    |
    */
    'auto_routes' => [
        'api/*',
        'superjson/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Type Mappings
    |--------------------------------------------------------------------------
    |
    | Map PHP classes to SuperJSON types for custom handling.
    |
    */
    'type_mappings' => [
        \DateTime::class => 'Date',
        \DateTimeImmutable::class => 'Date',
        \Carbon\Carbon::class => 'Date',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | CRITICAL: These security settings protect against various attacks.
    | Only modify if you understand the security implications.
    |
    */
    'security' => [
        'allow_class_restoration' => false, // SECURITY: Disable arbitrary class instantiation
        'allowed_classes' => [], // Whitelist of classes allowed for restoration
        'max_array_size' => 1000, // Maximum number of array elements
        'max_depth' => 10, // Maximum nesting depth to prevent stack overflow
        'sanitize_logged_content' => true, // Prevent sensitive data in logs
        'validate_input' => true, // Enable input validation checks
    ],
];
