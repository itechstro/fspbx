<?php

namespace App\Http\Requests\ContactCenter;

use Illuminate\Foundation\Http\FormRequest;

class SaveCallbackSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('contact_center_settings_edit');
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'introduction' => ['nullable', 'string', 'max:255'],
            'instruction' => ['nullable', 'string', 'max:255'],
            'offer_after_seconds' => ['required', 'integer', 'min:10', 'max:3600'],
            'offer_key' => ['required', 'regex:/^[0-9]$/'],
            'timezone' => ['required', 'timezone'],
            'closing_time' => ['required', 'date_format:H:i'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:5'],
            'retry_seconds' => ['required', 'integer', 'min:60', 'max:3600'],
        ];
    }
}
