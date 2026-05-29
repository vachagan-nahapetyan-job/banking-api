<?php

namespace Tests\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use App\Models\User;

trait ConcurrentTestTrait
{
    protected function makeConcurrentRequests(User $user, int $count, float $amount, string $endpoint = '/api/atm/withdraw'): array
    {
        $token = $user->createToken('test-token')->plainTextToken;
        $client = new Client([
            'base_uri' => 'http://localhost',
            'timeout' => 10,
            'http_errors' => false
        ]);

        $promises = [];
        for ($i = 0; $i < $count; $i++) {
            $promises[] = $client->postAsync($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'amount' => $amount,
                    'pin' => '1234',
                    'idempotency_key' => uniqid('concurrent_', true)
                ]
            ]);
        }

        return Utils::settle($promises)->wait();
    }

    protected function countSuccessfulRequests(array $results): int
    {
        $successful = 0;
        foreach ($results as $result) {
            if ($result['state'] === 'fulfilled' && $result['value']->getStatusCode() === 200) {
                $successful++;
            }
        }
        return $successful;
    }
}
