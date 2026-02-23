<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskPromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format_type' => ['sometimes', 'required', 'string', 'max:32', Rule::in(config('task_prompts.format_types', \App\Models\TaskPrompt::FORMAT_TYPES))],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
        ];
    }
}
