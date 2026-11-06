<?php

namespace App\Http\Requests;

use App\Models\TaskReview;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskReviewRequest extends FormRequest
{
    /**
     * Only admins and the manager of the task's division may review it.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('task')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(array_keys(TaskReview::DECISION_LABELS))],
            'note' => [
                Rule::requiredIf($this->input('decision') === TaskReview::DECISION_REVISION_REQUESTED),
                'nullable',
                'string',
                'max:1000',
            ],
            'version' => ['required', 'integer'],
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
            'integer' => ':attribute tidak valid.',
            'max' => ':attribute maksimal :max karakter.',
            'note.required' => 'Catatan revisi wajib diisi.',
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
            'decision' => 'Keputusan',
            'note' => 'Catatan',
            'version' => 'Versi',
        ];
    }
}
