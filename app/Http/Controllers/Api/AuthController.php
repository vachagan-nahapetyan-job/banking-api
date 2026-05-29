<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     summary="Register a new user",
     *     description="Creates a new user account with initial balance of $999,999.00 USD",
     *     tags={"Auth"},
     *     operationId="registerUser",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password","pin"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", minLength=6, example="secret123"),
     *             @OA\Property(property="pin", type="string", minLength=4, maxLength=6, example="1234", description="4-6 digit PIN for ATM operations")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="1|abc123..."),
     *             @OA\Property(property="balance", type="number", format="float", example=999999.00),
     *             @OA\Property(property="currency", type="string", example="USD")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'pin'      => Hash::make($request->pin),
            'balance'  => 999999.00,
            'currency' => 'USD',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token'    => $token,
            'balance'  => $user->balance,
            'currency' => $user->currency,
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Login and get authentication token",
     *     description="Authenticates user and returns a Bearer token for API access",
     *     tags={"Auth"},
     *     operationId="loginUser",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="secret123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="1|abc123...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Revoke all existing tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['token' => $token]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Logout user",
     *     description="Revokes the current user's token",
     *     tags={"Auth"},
     *     operationId="logoutUser",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logged out successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
