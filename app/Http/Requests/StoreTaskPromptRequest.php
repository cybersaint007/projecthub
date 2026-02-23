<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskPromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_type' => ['required', 'string', 'max:32', Rule::in(config('task_prompts.agent_types', \App\Models\TaskPrompt::AGENT_TYPES))],
            'format_type' => ['required', 'string', 'max:32', Rule::in(config('task_prompts.format_types', \App\Models\TaskPrompt::FORMAT_TYPES))],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ];
    }
}
