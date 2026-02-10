<?php

namespace App\Http\Requests\Workspace;

use App\Services\Webhooks\WorkspaceWebhookService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceWebhookEndpointRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $workspace = $user?->activeWorkspace();

        return $user !== null
            && $workspace !== null
            && $user->can('manageWebhooks', $workspace);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
            'signing_secret' => ['required', 'string', 'min:16', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in(array_keys(WorkspaceWebhookService::supportedEvents()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'events.*.in' => __('Select a valid webhook event type.'),
        ];
    }
}
