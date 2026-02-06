<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingCheckoutRequest extends FormRequest
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
            'plan' => ['required', 'string', Rule::in(array_keys($this->plans()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.required' => 'Select a plan before continuing to checkout.',
            'plan.in' => 'Select a valid plan before continuing to checkout.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function plans(): array
    {
        /** @var array<string, string> $plans */
        $plans = array_filter(
            config('services.stripe.prices', []),
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        );

        return $plans;
    }
}
