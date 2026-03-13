<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHighlightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'book' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'chapter' => ['required', 'integer', 'min:1', 'max:150'],
            'verse' => ['required', 'integer', 'min:1', 'max:200'],
            'color' => ['required', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
        ];
    }
}
