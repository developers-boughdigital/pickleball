<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'module' => ['required', 'string', 'max:60'],
            'name' => [
                'required', 'string', 'max:100',
                'regex:/^[a-z][a-z0-9-]*\.[a-z][a-z0-9-]*$/',
                Rule::unique(config('permission.table_names.permissions'))->where('guard_name', 'web')->ignore($this->route('permission')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Use the form module.action in lowercase, e.g. users.view or orders.approve.',
            'name.unique' => 'That permission already exists.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'module' => trim((string) $this->input('module')),
            'name' => strtolower(trim((string) $this->input('name'))),
        ]);
    }
}
