<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class WithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:300000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'pin'    => ['required', 'digits_between:4,6'],
            'idempotency_key' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_-]+$/']
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'The withdrawal amount is required.',
            'amount.numeric' => 'The amount must be a valid number.',
            'amount.min' => 'The amount must be at least $0.01.',
            'amount.max' => 'The maximum withdrawal amount is $300,000.',
            'amount.regex' => 'The amount can have up to 2 decimal places.',
            'pin.required' => 'PIN is required.',
            'pin.digits_between' => 'PIN must be between 4 and 6 digits.',
            'idempotency_key.regex' => 'Idempotency key can only contain letters, numbers, underscores, and hyphens.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation error',
            'errors' => $validator->errors()
        ], 422));
    }
}
