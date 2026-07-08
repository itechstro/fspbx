<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('call_permission_add');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'toll_allow' => ['nullable', 'string', 'max:1000'],
            'default_action' => ['required', Rule::in(['allow', 'deny'])],
            'enabled' => ['required', Rule::in(['true', 'false'])],
            'destinations' => ['nullable', 'array'],
            'destinations.*.call_permission_destination_uuid' => ['nullable', 'uuid'],
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
