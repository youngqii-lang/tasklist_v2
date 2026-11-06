<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskCommentRequest extends FormRequest
{
    /**
     * Only reviewers of the task and its current PIC may comment; everyone else reads only.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('comment', $this->route('task')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
            'parent_comment_id' => [
                'nullable',
                'integer',
                Rule::exists('task_comments', 'id')
                    ->where('task_id', $this->route('task')->id)
                    ->whereNull('deleted_at'),
            ],
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
            'content.required' => 'Komentar tidak boleh kosong.',
            'content.max' => 'Komentar maksimal :max karakter.',
            'parent_comment_id.integer' => 'Komentar yang dibalas tidak valid.',
            'parent_comment_id.exists' => 'Komentar yang dibalas tidak ditemukan pada tugas ini.',
        ];
    }
}
