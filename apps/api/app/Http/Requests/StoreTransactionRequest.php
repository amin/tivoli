<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "identity_token" => ["required", "string", "uuid"],
            "amount" => [
                "sometimes",
                "numeric",
                "decimal:0,2",
                "min:0.1",
                "max:9999.99",
            ],
            "api_key" => ["required", "string", "uuid"],
        ];
    }
}
