<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'TEVARA API Documentation',
    description: 'Dokumentasi REST API TEVARA - Project Based Learning',
    contact: new OA\Contact(name: 'TEVARA Dev Team')
)]
#[OA\Server(
    url: '/api/v1',
    description: 'API Base URL'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    name: 'Authorization',
    in: 'header',
    bearerFormat: 'Sanctum',
    scheme: 'bearer',
    description: 'Masukkan token Sanctum dengan format: Bearer <token>'
)]
abstract class Controller
{
    //
}
