<?php

namespace App\Http\Requests;

class UpdateClassOfServiceRequest extends StoreClassOfServiceRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('class_of_service_edit');
    }
}
