<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookmarkRequest extends FormRequest
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
            'label' => ['nullable', 'string', 'max:255'],
            'folder_id' => ['nullable', 'integer', 'exists:bookmark_folders,id'],
        ];
    }
}
