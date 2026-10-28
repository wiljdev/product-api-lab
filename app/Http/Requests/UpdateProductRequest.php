<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * PUT replaces the whole product, so every field is required.
 * PATCH updates only the fields that are sent.
 */
class UpdateProductRequest extends StoreProductRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if (! $this->isMethod('PATCH')) {
            return $rules;
        }

        return array_map(fn (array $fieldRules) => ['sometimes', ...$fieldRules], $rules);
    }
}
