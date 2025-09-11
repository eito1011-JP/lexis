<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_uuid' => ['required', 'string', 'max:64', 'regex:/^[a-z-]+$/'],
            'organization_name' => ['required', 'string', 'max:255'],
            'token' => ['required', 'string', 'max:64'],
        ];
    }
}
