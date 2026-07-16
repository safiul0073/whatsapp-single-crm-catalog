<?php

namespace App\Modules\AuthApi\Support;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Admin Panel API',
    description: 'Versioned API documentation for the modular application.'
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'Primary API server'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'apiKey',
    in: 'header',
    name: 'Authorization',
    description: 'Use Bearer token authentication.'
)]
class OpenApiSpec {}
