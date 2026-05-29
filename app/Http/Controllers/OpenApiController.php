<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Banking ATM API",
 *     version="1.1.0",
 *     description="REST API for banking ATM operations with race condition protection",
 *     @OA\Contact(
 *         email="support@banking-api.com",
 *         name="API Support"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local development server"
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="Authentication and registration endpoints"
 * )
 *
 * @OA\Tag(
 *     name="ATM",
 *     description="ATM banking operations with race condition protection"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Bearer",
 *     description="Enter token in format: Bearer {token}"
 * )
 *
 * @OA\Components(
 *     @OA\Schema(
 *         schema="ErrorResponse",
 *         @OA\Property(property="message", type="string", example="Invalid credentials")
 *     ),
 *     @OA\Schema(
 *         schema="ValidationError",
 *         @OA\Property(property="message", type="string", example="The given data was invalid."),
 *         @OA\Property(
 *             property="errors",
 *             type="object",
 *             example={"field": {"The field is required."}}
 *         )
 *     ),
 *     @OA\Schema(
 *         schema="WithdrawalRequest",
 *         required={"amount", "pin"},
 *         @OA\Property(property="amount", type="number", format="float", example=100.00),
 *         @OA\Property(property="pin", type="string", example="1234"),
 *         @OA\Property(property="idempotency_key", type="string", example="req_123456")
 *     ),
 *     @OA\Schema(
 *         schema="WithdrawalResponse",
 *         @OA\Property(property="message", type="string"),
 *         @OA\Property(property="amount", type="number"),
 *         @OA\Property(property="fee", type="number"),
 *         @OA\Property(property="balance_after", type="number"),
 *         @OA\Property(property="currency", type="string"),
 *         @OA\Property(property="transaction_id", type="integer")
 *     ),
 *     @OA\Schema(
 *         schema="DuplicateRequestError",
 *         @OA\Property(property="message", type="string"),
 *         @OA\Property(property="transaction_id", type="integer"),
 *         @OA\Property(property="amount", type="number"),
 *         @OA\Property(property="fee", type="number"),
 *         @OA\Property(property="balance_after", type="number"),
 *         @OA\Property(property="currency", type="string")
 *     )
 * )
 */
class OpenApiController
{
    // This file exists to hold OpenAPI base annotations
}
