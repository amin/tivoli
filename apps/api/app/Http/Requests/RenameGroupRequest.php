<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenameGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in controller (member check)
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
        ];
    }
}
