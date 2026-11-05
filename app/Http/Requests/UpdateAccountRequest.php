<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends StoreAccountRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('account')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The password is optional: leaving it empty keeps the current password.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fullname' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'alpha_num:ascii',
                'max:255',
                Rule::unique('accounts', 'username')->ignore($this->route('account')),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'division_id' => ['required', 'integer', Rule::exists('divisions', 'id')],
        ];
    }
}
