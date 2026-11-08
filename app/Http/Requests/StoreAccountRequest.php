<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Account::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fullname' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_num:ascii', 'max:255', Rule::unique('accounts', 'username')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role_id' => $this->user()->can('assignRoleOnCreate', Account::class)
                ? ['required', 'integer', Rule::exists('roles', 'id')]
                : ['exclude'],
            'division_id' => ['required', 'integer', Rule::exists('divisions', 'id')],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'username.alpha_num' => 'Username hanya boleh berisi huruf dan angka, tanpa spasi atau simbol.',
            'username.unique' => 'Username sudah terdaftar, gunakan username lain.',
            'password.min' => 'Password minimal :min karakter.',
            'exists' => ':attribute yang dipilih tidak valid.',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fullname' => 'Nama lengkap',
            'username' => 'Username',
            'password' => 'Password',
            'role_id' => 'Role',
            'division_id' => 'Divisi',
        ];
    }
}
