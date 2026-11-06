<?php

namespace App\Http\Requests;

use App\Models\TaskEvidence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskEvidenceRequest extends FormRequest
{
    /**
     * Only a current PIC may submit evidence for the task.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('addEvidence', $this->route('task')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $extensions = implode(',', config('tasklist.evidence.extensions'));

        return [
            'type' => ['required', 'string', Rule::in([TaskEvidence::TYPE_FILE, TaskEvidence::TYPE_LINK])],
            'file' => [
                'exclude_unless:type,'.TaskEvidence::TYPE_FILE,
                'required',
                'file',
                'max:'.config('tasklist.evidence.max_size_kb'),
                'extensions:'.$extensions,
                'mimes:'.$extensions,
            ],
            'url' => ['exclude_unless:type,'.TaskEvidence::TYPE_LINK, 'required', 'string', 'max:2048', 'url:http,https'],
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
            'in' => ':attribute yang dipilih tidak valid.',
            'file' => ':attribute harus berupa file.',
            'max' => ':attribute melebihi batas maksimal.',
            'file.max' => 'Ukuran file maksimal :max KB.',
            'file.extensions' => 'Format file tidak diizinkan.',
            'file.mimes' => 'Format file tidak diizinkan.',
            'url.url' => 'Tautan harus berupa URL yang valid (http atau https).',
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
            'type' => 'Jenis evidence',
            'file' => 'File',
            'url' => 'Tautan',
            'note' => 'Catatan',
        ];
    }
}
