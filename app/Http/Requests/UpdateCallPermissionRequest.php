<?php

namespace App\Http\Requests;

class UpdateCallPermissionRequest extends StoreCallPermissionRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('call_permission_edit');
    }
}
