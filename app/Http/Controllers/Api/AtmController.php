<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawRequest;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Throwable;

class AtmController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/atm/withdraw",
     *     summary="Withdraw money from account",
     *     tags={"ATM"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount","pin"},
     *             @OA\Property(property="amount", type="number", example=10000, description="Amount in USD, max 300000"),
     *             @OA\Property(property="pin", type="string", example="1234", description="4-6 digit PIN"),
     *             @OA\Property(property="idempotency_key", type="string", example="req_123456", description="Unique key to prevent duplicate processing")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Withdrawal successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Withdrawal successful"),
     *             @OA\Property(property="amount", type="number", example=10000),
     *             @OA\Property(property="fee", type="number", example=100),
     *             @OA\Property(property="balance_after", type="number", example=489899),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="transaction_id", type="integer", example=12345)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated or wrong PIN"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=409, description="Insufficient balance"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=429, description="Duplicate request detected"),
     *     @OA\Response(response=503, description="Service temporarily unavailable")
     * )
     */
    public function withdraw(WithdrawRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify PIN
        if (!Hash::check($request->pin, $user->pin)) {
            Log::warning('Invalid PIN attempt', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);
            return response()->json(['message' => 'Invalid PIN'], 401);
        }

        $config = config('atm');
        $amount = (float) $request->amount;
        $fee = round($amount * $config['fee_percentage'], 2);
        $total = $amount + $fee;
        $idempotencyKey = $request->input('idempotency_key');

        $maxRetries = $config['max_retries'];
        $retryDelay = $config['retry_delay'];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $result = DB::transaction(function () use ($user, $amount, $fee, $total, $idempotencyKey, $attempt) {
                    $lockedUser = User::where('id', $user->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$lockedUser) {
                        throw new \Exception('User not found', 404);
                    }

                    // Check for duplicate request using idempotency key within transaction
                    if ($idempotencyKey) {
                        $existingTransaction = Transaction::where('idempotency_key', $idempotencyKey)
                            ->where('user_id', $lockedUser->id)
                            ->first();

                        if ($existingTransaction) {
                            Log::info('Duplicate request prevented', [
                                'user_id' => $lockedUser->id,
                                'idempotency_key' => $idempotencyKey,
                                'transaction_id' => $existingTransaction->id
                            ]);

                            // Throw a custom exception that we can catch below to return 429
                            throw new \Exception('Duplicate request detected', 429);
                        }
                    }

                    if ($lockedUser->balance < $total) {
                        throw new \Exception('Insufficient balance', 409);
                    }

                    $lockedUser->balance -= $total;
                    $lockedUser->save();

                    $transaction = Transaction::create([
                        'user_id' => $lockedUser->id,
                        'type' => 'withdraw',
                        'amount' => $amount,
                        'fee_amount' => $fee,
                        'balance_after' => $lockedUser->balance,
                        'idempotency_key' => $idempotencyKey,
                        'metadata' => json_encode([
                            'ip' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'attempt' => $attempt
                        ])
                    ]);

                    Log::info('Withdrawal successful', [
                        'user_id' => $lockedUser->id,
                        'email' => $lockedUser->email,
                        'amount' => $amount,
                        'fee' => $fee,
                        'total_deducted' => $total,
                        'balance_after' => $lockedUser->balance,
                        'transaction_id' => $transaction->id
                    ]);

                    return [
                        'transaction' => $transaction,
                        'balance' => $lockedUser->balance
                    ];
                });

                return response()->json([
                    'message' => 'Withdrawal successful',
                    'amount' => $amount,
                    'fee' => $fee,
                    'balance_after' => $result['balance'],
                    'currency' => $user->currency,
                    'transaction_id' => $result['transaction']->id,
                ]);

            } catch (Throwable $e) {
                // Handle duplicate request detected (idempotency key)
                if ($e->getMessage() === 'Duplicate request detected') {
                    // Find the existing transaction to return its details
                    $existingTransaction = Transaction::where('idempotency_key', $idempotencyKey)
                        ->where('user_id', $user->id)
                        ->first();

                    if ($existingTransaction) {
                        Log::info('Duplicate request prevented', [
                            'user_id' => $user->id,
                            'idempotency_key' => $idempotencyKey,
                            'transaction_id' => $existingTransaction->id
                        ]);

                        return response()->json([
                            'message' => 'Duplicate request detected',
                            'transaction_id' => $existingTransaction->id,
                            'amount' => $existingTransaction->amount,
                            'fee' => $existingTransaction->fee_amount,
                            'balance_after' => $existingTransaction->balance_after,
                            'currency' => $user->currency,
                        ], 429);
                    }
                }

                if (str_contains($e->getMessage(), 'Deadlock') || str_contains($e->getMessage(), 'Lock wait timeout')) {
                    if ($attempt < $maxRetries) {
                        Log::warning('Deadlock detected, retrying', [
                            'user_id' => $user->id,
                            'attempt' => $attempt,
                            'error' => $e->getMessage()
                        ]);
                        usleep($retryDelay * 1000 * $attempt);
                        continue;
                    }

                    Log::error('Deadlock persisted after retries', [
                        'user_id' => $user->id,
                        'attempts' => $maxRetries
                    ]);

                    return response()->json([
                        'message' => 'Transaction temporarily unavailable. Please try again.',
                    ], 503);
                }

                if ($e->getMessage() === 'Insufficient balance') {
                    return response()->json([
                        'message' => 'Insufficient balance',
                        'required' => $total,
                        'available_balance' => $user->fresh()->balance,
                        'currency' => $user->currency,
                    ], 409);
                }

                if ($e->getCode() === 404) {
                    return response()->json(['message' => 'User not found'], 404);
                }

                Log::error('Withdrawal failed unexpectedly', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'message' => 'Transaction failed due to an internal error.',
                ], 500);
            }
        }

        return response()->json([
            'message' => 'Transaction failed after multiple retries',
        ], 503);
    }

    /**
     * @OA\Get(
     *     path="/api/atm/balance",
     *     summary="Get current account balance",
     *     tags={"ATM"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Balance retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="balance", type="number", example=123456.78),
     *             @OA\Property(property="currency", type="string", example="USD")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'balance' => $user->balance,
            'currency' => $user->currency,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/atm/transactions",
     *     summary="Get transaction history (withdrawals only)",
     *     tags={"ATM"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (max 50)",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter from date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter to date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transactions list",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="type", type="string", example="withdraw"),
     *                     @OA\Property(property="amount", type="number"),
     *                     @OA\Property(property="fee_amount", type="number"),
     *                     @OA\Property(property="balance_after", type="number"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function transactions(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = $request->input('per_page', 10);

        $query = $request->user()
            ->transactions()
            ->where('type', 'withdraw')
            ->orderByDesc('created_at');

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $transactions = $query->paginate($perPage);

        return response()->json($transactions);
    }
}
