<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawRequest;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;


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
     *             @OA\Property(property="pin", type="string", example="1234", description="4-6 digit PIN")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Withdrawal successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Withdrawal successful"),
     *             @OA\Property(property="amount", type="number", example=10000),
     *             @OA\Property(property="fee", type="number", example=100),
     *             @OA\Property(property="balance_after", type="number", example=49000),
     *             @OA\Property(property="currency", type="string", example="USD")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated or wrong PIN"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=409, description="Insufficient balance"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function withdraw(WithdrawRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->pin, $user->pin)) {
            return response()->json(['message' => 'Invalid PIN'], 401);
        }

        $amount = (float) $request->amount;
        $fee    = round($amount * 0.01, 2);
        $total  = $amount + $fee;

        if ($user->balance < $total) {
            return response()->json([
                'message' => 'Insufficient balance',
                'required' => $total,
                'available_balance' => $user->balance,
            ], 409);
        }

        DB::transaction(function () use ($user, $amount, $fee, $total) {
            $user->balance -= $total;
            $user->save();

            Transaction::create([
                'user_id'       => $user->id,
                'type'          => 'withdraw',
                'amount'        => $amount,
                'fee_amount'    => $fee,
                'balance_after' => $user->balance,
            ]);
        });

        return response()->json([
            'message'       => 'Withdrawal successful',
            'amount'        => $amount,
            'fee'           => $fee,
            'balance_after' => $user->balance,
            'currency'      => $user->currency,
        ]);
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
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             )
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
        ]);

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

        $paginated = $query->paginate(10);

        return response()->json([
            'data' => $paginated->map(fn($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => $t->amount,
                'fee_amount' => $t->fee_amount,
                'balance_after' => $t->balance_after,
                'created_at' => $t->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }
}
