<?php

namespace App\Http\Requests;

class UpdateVContactRequest extends StoreVContactRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('contact_edit');
    }
}
