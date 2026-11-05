<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Task::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isReadyToWork = $this->input('status') === Task::STATUS_TODO;

        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'division_id' => ['required', 'integer', Rule::exists('divisions', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('task_categories', 'id')],
            'platform_id' => ['nullable', 'integer', Rule::exists('platforms', 'id')],
            'priority' => ['nullable', 'string', Rule::in(Task::PRIORITIES)],
            'status' => ['nullable', 'string', Rule::in(Task::INITIAL_STATUSES)],
            'start_date' => ['nullable', 'date'],
            'deadline' => [
                Rule::when($isReadyToWork, ['required'], ['nullable']),
                'date',
                'after_or_equal:today',
                Rule::when($this->filled('start_date'), ['after_or_equal:start_date']),
            ],
            'requires_review' => ['nullable', 'boolean'],
            'pic_ids' => [Rule::when($isReadyToWork, ['required'], ['nullable']), 'array'],
            'pic_ids.*' => ['integer', 'distinct', $this->assignablePicRule()],
        ];
    }

    /**
     * A PIC must be an active staff account. Admins may pick staff of any division; others only staff of the target division.
     */
    protected function assignablePicRule(): Exists
    {
        $rule = Rule::exists('accounts', 'id')
            ->where('is_active', true)
            ->whereIn('role_id', Role::whereIn('code_role', Role::STAFF_ROLES)->pluck('id')->all());

        return $this->user()?->isAdmin() ? $rule : $rule->where('division_id', $this->integer('division_id'));
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
            'exists' => ':attribute yang dipilih tidak valid.',
            'in' => ':attribute yang dipilih tidak valid.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'deadline.required' => 'Deadline wajib diisi untuk tugas berstatus To Do.',
            'deadline.after_or_equal' => 'Deadline tidak boleh sebelum hari ini atau sebelum tanggal mulai.',
            'pic_ids.required' => 'PIC wajib dipilih untuk tugas berstatus To Do.',
            'pic_ids.*.distinct' => 'PIC yang sama tidak boleh dipilih lebih dari sekali.',
            'pic_ids.*.exists' => 'PIC harus akun Staff aktif dari divisi yang dipilih.',
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
            'title' => 'Judul tugas',
            'description' => 'Deskripsi',
            'division_id' => 'Divisi',
            'category_id' => 'Kategori',
            'platform_id' => 'Platform',
            'priority' => 'Prioritas',
            'status' => 'Status',
            'start_date' => 'Tanggal mulai',
            'deadline' => 'Deadline',
            'requires_review' => 'Kewajiban QA',
            'pic_ids' => 'PIC',
            'pic_ids.*' => 'PIC',
        ];
    }
}
