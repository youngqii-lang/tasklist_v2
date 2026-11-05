<?php

namespace App\Http\Requests;

use App\Models\Division;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $task = $this->task();

        if (! $user?->can('update', $task)) {
            return false;
        }

        if (! $user->can('manage', $task)) {
            return true;
        }

        $division = $this->filled('division_id') ? Division::find($this->integer('division_id')) : null;

        return $division === null || $user->can('changeDivision', [$task, $division]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only the fields that are sent are changed; PIC and status have their own endpoints.
     * A PIC who does not manage the task may not change its division, category or QA requirement.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isPicOnly = ! $this->user()?->can('manage', $this->task());

        return [
            'version' => ['required', 'integer'],
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            'division_id' => [Rule::prohibitedIf($isPicOnly), 'sometimes', 'required', 'integer', Rule::exists('divisions', 'id')],
            'category_id' => [Rule::prohibitedIf($isPicOnly), 'sometimes', 'nullable', 'integer', Rule::exists('task_categories', 'id')],
            'platform_id' => ['sometimes', 'nullable', 'integer', Rule::exists('platforms', 'id')],
            'priority' => ['sometimes', 'required', 'string', Rule::in(Task::PRIORITIES)],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'requires_review' => [Rule::prohibitedIf($isPicOnly), 'sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * Check the dates against the values that stay unchanged on the task.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'deadline'])) {
                    return;
                }

                $task = $this->task();
                $startDate = $this->exists('start_date') ? $this->date('start_date') : $task->start_date;
                $deadline = $this->exists('deadline') ? $this->date('deadline') : $task->deadline;

                if ($deadline === null && $task->status !== Task::STATUS_BACKLOG) {
                    $validator->errors()->add('deadline', 'Deadline wajib diisi untuk tugas yang sudah berjalan.');

                    return;
                }

                if ($deadline === null) {
                    return;
                }

                $deadlineChanged = $this->exists('deadline') && ($task->deadline === null || ! $deadline->isSameDay($task->deadline));

                if ($deadlineChanged && $deadline->lt(today())) {
                    $validator->errors()->add('deadline', 'Deadline tidak boleh sebelum hari ini.');
                } elseif ($startDate !== null && $deadline->lt($startDate)) {
                    $validator->errors()->add('deadline', 'Deadline tidak boleh sebelum tanggal mulai.');
                }
            },
        ];
    }

    /**
     * Get the task being updated.
     */
    public function task(): Task
    {
        return $this->route('task');
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
            'max' => ':attribute maksimal :max karakter.',
            'integer' => ':attribute tidak valid.',
            'boolean' => ':attribute tidak valid.',
            'exists' => ':attribute yang dipilih tidak valid.',
            'in' => ':attribute yang dipilih tidak valid.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'prohibited' => 'Anda tidak berwenang mengubah :attribute.',
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
            'version' => 'Versi',
            'title' => 'Judul tugas',
            'description' => 'Deskripsi',
            'division_id' => 'Divisi',
            'category_id' => 'Kategori',
            'platform_id' => 'Platform',
            'priority' => 'Prioritas',
            'start_date' => 'Tanggal mulai',
            'deadline' => 'Deadline',
            'requires_review' => 'Kewajiban review',
        ];
    }
}
