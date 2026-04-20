<?php

return [
    'api' => [
        /*
        |--------------------------------------------------------------------------
        | Edit to set the api's title
        |--------------------------------------------------------------------------
        */

        'title' => 'BSERP API',
        'description' => 'BSERP (Business Server ERP) - Complete Business Management System API',

        /*
        |--------------------------------------------------------------------------
        | Edit to set the api's version number
        |--------------------------------------------------------------------------
        */

        'version' => '1.0.0',

        /*
        |--------------------------------------------------------------------------
        | Edit to set the api's host
        |--------------------------------------------------------------------------
        */

        'host' => env('API_HOST', 'localhost:8000'),
        'host_path' => '/api',
        'schemes' => env('API_SCHEMES', ['http']),
        'consumes' => ['application/json'],
        'produces' => ['application/json'],
    ],

    'routes' => [
        /*
        |--------------------------------------------------------------------------
        | Specify a route that will be registered by L5/Swagger itself
        |--------------------------------------------------------------------------
        */

        'api' => 'api/documentation',
        'docs' => 'api/docs',
        'oauth2_callback' => 'api/oauth2-callback',
    ],

    'paths' => [
        /*
        |--------------------------------------------------------------------------
        | Specify output path of generated swagger json documentation
        |--------------------------------------------------------------------------
        */

        'docs_json' => 'api-docs.json',
        'docs_yaml' => 'api-docs.yaml',
        'use_absolute_path' => env('SWAGGER_USE_ABSOLUTE_PATH', true),
        'swagger_ui_path' => '/swagger-ui',
        'oauth2_redirect_path' => '/api/oauth2-callback',

        /*
        |--------------------------------------------------------------------------
        | Paths where the generated docs will be stored
        |--------------------------------------------------------------------------
        */

        'docs' => storage_path('api-docs'),
        'views' => base_path('resources/views/vendor/swagger'),
        'base' => env('SWAGGER_BASE_PATH', '/'),
    ],

    'scanOptions' => [
        'default_processors_enabled' => true,
        'processors' => [
            // Used to parse the output of the paths
            \OpenApi\Processors\CleanUnreferencedSchemas::class,
        ],
        /*
        |--------------------------------------------------------------------------
        | Attribute classes to scan
        |--------------------------------------------------------------------------
        */

        'analyser' => null,

        /*
        |--------------------------------------------------------------------------
        | Scan these directories for OpenAPI annotations
        |--------------------------------------------------------------------------
        */

        'paths' => [
            base_path('app/Http/Controllers'),
            base_path('routes'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Set this to `true` in development mode so that docs would be regenerated
    | on each request. Set this to `false` to disable it so that docs would only
    | be regenerated when the command `l5:swagger` is executed.
    |--------------------------------------------------------------------------
    */

    'generate_always' => env('SWAGGER_GENERATE_ALWAYS', false),

    /*
    |--------------------------------------------------------------------------
    | Set this to `true` to generate a copy of swagger json in the given
    | docs path to use external UI files
    |--------------------------------------------------------------------------
    */

    'generate_yaml_copy' => env('SWAGGER_GENERATE_YAML_COPY', false),

    /*
    |--------------------------------------------------------------------------
    | Edit to set the swagger.json's temporary token value
    |--------------------------------------------------------------------------
    */

    'temp_swagger_json_token' => 'temp-swagger-json-token',

    'operations' => [
        /*
        |--------------------------------------------------------------------------
        | The default `operationId` format will be `{controller_name}_{method}`.
        | Change it with the following config to e.g. `{method}_{controller_name}`
        | Available placeholders: {controller_name}, {method}
        |--------------------------------------------------------------------------
        */

        'operationIdFormat' => '{method}{controller_name}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Modify the list of servers that is used in the exported docs
    |--------------------------------------------------------------------------
    */

    'servers' => [
        [
            'url' => env('APP_URL', 'http://localhost:8000'),
            'description' => 'Development Server',
        ],
        [
            'url' => env('SWAGGER_PRODUCTION_URL', 'https://api.example.com'),
            'description' => 'Production Server',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | List of allowed domains for CORS requests
    |--------------------------------------------------------------------------
    */

    'cors' => [
        'allowed_origins' => [
            env('APP_URL', 'http://localhost:8000'),
            env('FRONTEND_URL', 'http://localhost:5173'),
        ],
        'allowed_origins_patterns' => [],
        'allowed_methods' => ['*'],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 0,
        'supports_credentials' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | League\OpenAPI Guards for L5/Swagger
    |--------------------------------------------------------------------------
    */

    'guards' => [
        'sanctum' => [
            'type' => 'apiKey',
            'description' => 'Auth token obtained at login',
            'name' => 'Authorization',
            'in' => 'header',
        ],
    ],
];
