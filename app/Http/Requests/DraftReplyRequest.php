<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DraftReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Chapter 43 adds real authorization.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:4000'],
            'customer_name' => ['nullable', 'string', 'max:80'],
        ];
    }
}
