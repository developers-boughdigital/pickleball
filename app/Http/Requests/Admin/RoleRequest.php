<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique(config('permission.table_names.roles'))->where('guard_name', 'web')->ignore($role),
                // The super admin keeps its name; other code looks it up by it.
                Rule::when($role?->isProtected(), [Rule::in([Role::SUPER_ADMIN])]),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists(config('permission.table_names.permissions'), 'name')->where('guard_name', 'web')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.in' => 'The '.Role::SUPER_ADMIN.' role can’t be renamed.',
            'permissions.*.exists' => 'One of the selected permissions no longer exists. Reload the page and try again.',
        ];
    }
}
