<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTaskRequest extends StoreTaskRequest
{
    /**
     * Only the fields that are sent are validated and changed. version is the task version the edit
     * was based on; who may change which field, and the status workflow, are checked by TaskService.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_categories', 'id')],
            'division_id' => ['sometimes', 'required', 'integer', Rule::exists('divisions', 'id')],
            'collaborating_division_ids' => ['sometimes', 'nullable', 'array'],
            'collaborating_division_ids.*' => ['integer', 'distinct', Rule::exists('divisions', 'id')],
            'platform_id' => ['sometimes', 'nullable', 'integer', Rule::exists('platforms', 'id')],
            'priority' => ['sometimes', 'required', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'status' => ['sometimes', 'required', Rule::in(array_column(TaskStatus::cases(), 'value'))],
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'deadline' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
                // An existing deadline that has already passed may stay; only a new deadline must not be in the past.
                ...($this->deadlineChanged() ? ['after_or_equal:today'] : []),
            ],
            'deadline_time' => ['sometimes', 'nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'version.required' => 'Versi tugas wajib dikirim. Muat ulang detail tugas.',
            'version.integer' => 'Versi tugas tidak valid. Muat ulang detail tugas.',
            'version.min' => 'Versi tugas tidak valid. Muat ulang detail tugas.',
            'status.in' => ':attribute tidak valid.',
            'deadline.after_or_equal' => 'Deadline tidak boleh sebelum hari ini.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'status' => 'Status',
        ];
    }

    /**
     * Checks that combine sent values with the task's current ones.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $errors = $validator->errors();

                if (! $errors->hasAny(['start_date', 'deadline']) && $this->hasAny(['start_date', 'deadline'])) {
                    $startDate = $this->has('start_date') ? $this->input('start_date') : $this->task()->start_date?->toDateString();
                    $deadline = $this->has('deadline') ? $this->input('deadline') : $this->task()->deadline?->toDateString();

                    if ($startDate !== null && $deadline !== null && $deadline < $startDate) {
                        $errors->add('deadline', 'Deadline tidak boleh sebelum tanggal mulai.');
                    }
                }

                if ($errors->hasAny(['deadline', 'deadline_time']) || ! $this->hasAny(['deadline', 'deadline_time'])) {
                    return;
                }

                $deadline = $this->has('deadline') ? $this->input('deadline') : $this->task()->deadline?->toDateString();
                $time = $this->has('deadline_time') ? $this->input('deadline_time') : $this->task()->deadline_time;

                if ($deadline !== null && ($time === null || $time === '')) {
                    $errors->add('deadline_time', 'Jam deadline wajib diisi bersama tanggal deadline.');

                    return;
                }

                // An existing deadline that has already passed may stay; only a new date or time must not be in the past.
                if ($deadline !== null && ($this->deadlineChanged() || $this->deadlineTimeChanged())) {
                    $this->rejectPastDeadline($validator, $deadline, $time);
                }
            },
        ];
    }

    private function deadlineChanged(): bool
    {
        return $this->filled('deadline') && $this->input('deadline') !== $this->task()->deadline?->toDateString();
    }

    private function deadlineTimeChanged(): bool
    {
        return $this->filled('deadline_time') && $this->input('deadline_time') !== $this->task()->deadline_time;
    }

    private function task(): Task
    {
        return $this->route('task');
    }
}
