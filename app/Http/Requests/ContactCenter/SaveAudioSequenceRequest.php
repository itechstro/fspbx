<?php

namespace App\Http\Requests\ContactCenter;

use Illuminate\Foundation\Http\FormRequest;

class SaveAudioSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('contact_center_settings_edit');
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:continuous,sequence'],
            'steps' => ['array', 'max:40'],
            'steps.*.type' => ['required', 'in:clip,callback,position'],
            'steps.*.recording' => ['nullable', 'string', 'max:255'],
            'steps.*.duration_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
        ];
    }
}
