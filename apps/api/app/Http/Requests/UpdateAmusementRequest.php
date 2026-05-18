<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAmusementRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('amusements', 'name')->ignore($this->route('id')),
            ],
            'description' => 'sometimes|nullable|string|max:300',
            'url' => 'sometimes|url|max:300',
            'image_url' => 'sometimes|nullable|url|max:300',
            'price' => 'sometimes|nullable|numeric|min:0|max:999.99',
            'player_payout' => 'sometimes|nullable|numeric|min:0|max:999.99',
            'type' => 'sometimes|in:game,attraction',
        ];
    }
}
