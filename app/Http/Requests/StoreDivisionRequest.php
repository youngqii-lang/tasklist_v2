<?php

namespace App\Http\Requests;

use App\Models\Division;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreDivisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Division::class) ?? false;
    }

    /**
     * Normalize the division code to uppercase before validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code_division'))) {
            $this->merge(['code_division' => strtoupper(trim($this->input('code_division')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code_division' => ['required', 'string', 'alpha_dash:ascii', 'max:50', $this->uniqueRule('code_division')],
            'name' => ['required', 'string', 'max:25', $this->uniqueRule('name')],
        ];
    }

    /**
     * Build the unique rule for a division column.
     */
    protected function uniqueRule(string $column): Unique
    {
        return Rule::unique('divisions', $column);
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
            'code_division.alpha_dash' => 'Kode divisi hanya boleh berisi huruf, angka, tanda hubung (-), atau garis bawah (_).',
            'unique' => ':attribute sudah terdaftar, gunakan yang lain.',
            'max' => ':attribute maksimal :max karakter.',
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
            'code_division' => 'Kode divisi',
            'name' => 'Nama divisi',
        ];
    }
}
