<?php

namespace App\Http\Requests\ContactCenter;

use Illuminate\Foundation\Http\FormRequest;

class BulkMakeAgentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('contact_center_agent_create');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
