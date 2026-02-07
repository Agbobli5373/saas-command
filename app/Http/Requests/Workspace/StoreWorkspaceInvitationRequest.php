<?php

namespace App\Http\Requests\Workspace;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceInvitationRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in([
                WorkspaceRole::Admin->value,
                WorkspaceRole::Member->value,
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Enter an email address to send the invitation.',
            'email.email' => 'Enter a valid email address.',
            'role.required' => 'Choose a role for the invite.',
            'role.in' => 'Choose a valid role for the invite.',
        ];
    }
}
