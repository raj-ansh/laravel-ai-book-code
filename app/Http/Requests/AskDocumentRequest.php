<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required', 'file', 'max:5120',
                'mimes:txt,md,csv,pdf,png,jpg,jpeg,webp',
            ],
            'question' => ['required', 'string', 'max:500'],
        ];
    }
}
