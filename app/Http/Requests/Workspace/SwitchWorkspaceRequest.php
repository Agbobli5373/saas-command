<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workspace_id' => [
                'required',
                'integer',
                Rule::exists('workspaces', 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'workspace_id.required' => 'Select a workspace before switching.',
            'workspace_id.integer' => 'Select a valid workspace before switching.',
            'workspace_id.exists' => 'Select an existing workspace before switching.',
        ];
    }
}
