<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassOfServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('class_of_service_add');
    }

    public function rules(): array
    {
        return [
            'cos_name' => ['required', 'string', 'max:255'],
            'cos_description' => ['nullable', 'string', 'max:1000'],
            'toll_allow' => ['nullable', 'string', 'max:1000'],
            'default_action' => ['required', Rule::in(['allow', 'deny'])],
            'enabled' => ['required', Rule::in(['true', 'false'])],
            'destinations' => ['nullable', 'array'],
            'destinations.*.class_of_service_destination_uuid' => ['nullable', 'uuid'],
            'destinations.*.destination_prefix' => ['nullable', 'string', 'max:255'],
            'destinations.*.destination_action' => ['nullable', Rule::in(['allow', 'deny'])],
            'destinations.*.destination_order' => ['nullable', 'integer', 'min:0'],
            'destinations.*.enabled' => ['nullable', Rule::in(['true', 'false'])],
            'destinations.*.destination_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->input('enabled', 'true'),
            'default_action' => $this->input('default_action', 'allow'),
            'destinations' => $this->input('destinations', []),
        ]);
    }
}
