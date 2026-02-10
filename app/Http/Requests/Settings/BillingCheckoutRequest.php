<?php

namespace App\Http\Requests\Settings;

use App\Services\Billing\PlanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingCheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->activeWorkspace() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::in(array_keys($this->plans()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.required' => __('Select a paid plan before continuing to checkout.'),
            'plan.in' => __('Select a valid paid plan before continuing to checkout.'),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function plans(): array
    {
        return app(PlanService::class)->checkoutPlans();
    }
}
