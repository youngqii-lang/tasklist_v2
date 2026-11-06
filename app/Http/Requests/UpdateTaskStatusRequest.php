<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    /**
     * The workflow decides who may make each transition; here the user only needs to see the task.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('task')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_keys(Task::STATUS_LABELS))],
            'version' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
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
            'integer' => ':attribute tidak valid.',
            'in' => ':attribute yang dipilih tidak valid.',
            'max' => ':attribute maksimal :max karakter.',
            'version.required' => 'Versi data tugas wajib dikirim.',
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
            'status' => 'Status',
            'version' => 'Versi',
            'note' => 'Catatan',
        ];
    }
}
