<?php

namespace App\Http\Requests\Onboarding;

use App\Services\Billing\PlanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteOnboardingRequest extends FormRequest
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
            'workspace_name' => ['required', 'string', 'min:3', 'max:80'],
            'plan' => ['required', 'string', Rule::in(array_keys($this->plans()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'workspace_name.required' => 'Enter a workspace name to continue onboarding.',
            'workspace_name.min' => 'Workspace name must be at least 3 characters.',
            'plan.required' => 'Choose a plan to continue onboarding.',
            'plan.in' => 'Choose a valid plan to continue onboarding.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function plans(): array
    {
        return app(PlanService::class)->all();
    }
}
