<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Throwable;

class AtmWithdrawTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we have a test key
        if (empty(config('app.key'))) {
            config(['app.key' => 'base64:testkey1234567890testkey1234567890=']);
        }
    }

    public function test_withdrawal_updates_balance_and_creates_transaction(): void
    {
        $user = User::factory()->create([
            'pin' => Hash::make('1234'),
            'balance' => 1000.00,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/atm/withdraw', [
            'amount' => 100,
            'pin' => '1234',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Withdrawal successful',
                'amount' => 100,
                'fee' => 1.0,
                'balance_after' => 899.0,
                'currency' => 'USD',
            ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'withdraw',
            'amount' => 100.00,
            'fee_amount' => 1.00,
            'balance_after' => 899.00,
        ]);

        $this->assertEquals(899.00, $user->fresh()->balance);
    }

    public function test_insufficient_balance_returns_error(): void
    {
        $user = User::factory()->create([
            'pin' => Hash::make('1234'),
            'balance' => 150.00,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($user);

        // First withdrawal should succeed
        $response = $this->postJson('/api/atm/withdraw', [
            'amount' => 140,
            'pin' => '1234',
        ]);
        $response->assertStatus(200);

        // Second withdrawal should fail
        $overspendResponse = $this->postJson('/api/atm/withdraw', [
            'amount' => 20,
            'pin' => '1234',
        ]);

        $overspendResponse->assertStatus(409)
            ->assertJson([
                'message' => 'Insufficient balance',
            ]);

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_insufficient_balance_check_prevents_overdraft(): void
    {
        $user = User::factory()->create([
            'pin' => Hash::make('1234'),
            'balance' => 100.00,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/atm/withdraw', [
            'amount' => 100,
            'pin' => '1234',
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Insufficient balance',
            ]);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertEquals(100.00, $user->fresh()->balance);
    }

    public function test_race_condition_with_sequential_requests(): void
    {
        $user = User::factory()->create([
            'pin' => Hash::make('1234'),
            'balance' => 500.00,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($user);

        // Simulate rapid withdrawal requests
        $amounts = [100, 100, 100, 100, 100];
        $responses = [];

        foreach ($amounts as $index => $amount) {
            $responses[] = $this->postJson('/api/atm/withdraw', [
                'amount' => $amount,
                'pin' => '1234',
                'idempotency_key' => 'seq_' . $index . '_' . uniqid()
            ]);
        }

        $successful = collect($responses)->filter(fn($r) => $r->status() === 200)->count();

        // Maximum possible: floor(500 / 101) = 4
        $this->assertLessThanOrEqual(4, $successful);

        $finalBalance = $user->fresh()->balance;
        $this->assertGreaterThanOrEqual(0, $finalBalance);
        $this->assertEquals(500 - ($successful * 101), $finalBalance);
    }

    public function test_idempotency_key_prevents_duplicate_requests(): void
    {
        $user = User::factory()->create([
            'pin' => Hash::make('1234'),
            'balance' => 1000.00,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($user);

        $idempotencyKey = 'unique_key_' . uniqid();

        // First request should succeed
        $response1 = $this->postJson('/api/atm/withdraw', [
            'amount' => 100,
            'pin' => '1234',
            'idempotency_key' => $idempotencyKey,
        ]);

        $response1->assertStatus(200);
        $transactionId = $response1->json('transaction_id');

        // Second request with same key should be rejected
        $response2 = $this->postJson('/api/atm/withdraw', [
            'amount' => 100,
            'pin' => '1234',
            'idempotency_key' => $idempotencyKey,
        ]);

        $response2->assertStatus(429)
            ->assertJson([
                'message' => 'Duplicate request detected',
                'transaction_id' => $transactionId
            ]);

        // Balance should only be deducted once
        $this->assertEquals(899.00, $user->fresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_stress_test_with_many_sequential_requests(): void
    {
        $user = User::factory()->create([
            'pin' => Hash::make('1234'),
            'balance' => 10000.00,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($user);

        $responses = [];

        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->postJson('/api/atm/withdraw', [
                'amount' => 100,
                'pin' => '1234',
                'idempotency_key' => 'stress_' . $i . '_' . uniqid()
            ]);
        }

        $successful = collect($responses)->filter(fn($r) => $r->status() === 200)->count();

        // All 10 should succeed (10 * 101 = 1010, balance is 10000)
        $this->assertEquals(10, $successful);

        $expectedBalance = 10000 - (10 * 101);
        $this->assertEquals($expectedBalance, $user->fresh()->balance);
        $this->assertEquals(10, Transaction::where('user_id', $user->id)->count());
    }

    public function test_wrong_pin_returns_401(): void
    {
        $user = User::factory()->create([
            'pin' => Hash::make('1234'),
            'balance' => 1000.00,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/atm/withdraw', [
            'amount' => 100,
            'pin' => '9999',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid PIN',
            ]);

        $this->assertEquals(1000.00, $user->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_fee_is_calculated_correctly(): void
    {
        $testCases = [
            ['amount' => 100, 'expectedFee' => 1.00],
            ['amount' => 250.50, 'expectedFee' => 2.51],
            ['amount' => 0.99, 'expectedFee' => 0.01],
            ['amount' => 1000, 'expectedFee' => 10.00],
        ];

        foreach ($testCases as $testCase) {
            $user = User::factory()->create([
                'pin' => Hash::make('1234'),
                'balance' => 10000.00,
                'currency' => 'USD',
            ]);

            Sanctum::actingAs($user);

            $response = $this->postJson('/api/atm/withdraw', [
                'amount' => $testCase['amount'],
                'pin' => '1234',
            ]);

            $response->assertStatus(200);
            $response->assertJson([
                'fee' => $testCase['expectedFee']
            ]);

            $this->assertDatabaseHas('transactions', [
                'user_id' => $user->id,
                'amount' => $testCase['amount'],
                'fee_amount' => $testCase['expectedFee'],
            ]);
        }
    }

    public function test_balance_endpoint(): void
    {
        $user = User::factory()->create([
            'balance' => 5000.00,
            'currency' => 'EUR',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/atm/balance');

        $response->assertStatus(200)
            ->assertJson([
                'balance' => 5000.00,
                'currency' => 'EUR',
            ]);
    }

    public function test_transactions_endpoint(): void
    {
        $user = User::factory()->create();

        // Create test transactions
        for ($i = 0; $i < 15; $i++) {
            Transaction::create([
                'user_id' => $user->id,
                'type' => 'withdraw',
                'amount' => 100.00,
                'fee_amount' => 1.00,
                'balance_after' => 1000.00 - ($i * 101),
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/atm/transactions');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(10, $data);
    }
}
