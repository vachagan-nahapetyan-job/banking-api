<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Banking ATM API",
 *     version="1.0.0",
 *     description="REST API for banking ATM operations"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local development server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Bearer",
 *     description="Enter token in format: Bearer {token}"
 * )
 */
class OpenApiController extends Controller
{
    // This file exists only to hold OpenAPI base annotations
}
