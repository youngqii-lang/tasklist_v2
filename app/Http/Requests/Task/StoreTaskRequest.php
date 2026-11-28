<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Account;
use App\Models\Division;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    public const MAX_PICS = 20;

    /**
     * Authorization is handled by TaskPolicy on the route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', Rule::exists('task_categories', 'id')],
            'division_id' => ['required', 'integer', Rule::exists('divisions', 'id'), $this->creatableDivision()],
            'collaborating_division_ids' => ['nullable', 'array'],
            'collaborating_division_ids.*' => ['integer', 'distinct', Rule::exists('divisions', 'id')],
            'platform_id' => ['nullable', 'integer', Rule::exists('platforms', 'id')],
            'pic_ids' => [
                'nullable',
                Rule::requiredIf($this->input('status') === TaskStatus::Todo->value),
                'array',
                'max:'.self::MAX_PICS,
            ],
            'pic_ids.*' => ['integer', 'distinct', $this->assignablePic()],
            'priority' => ['nullable', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'status' => ['nullable', Rule::in(array_column(TaskStatus::initial(), 'value'))],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'deadline' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:today',
                ...($this->filled('start_date') ? ['after_or_equal:start_date'] : []),
            ],
            'deadline_time' => ['nullable', 'required_with:deadline', 'date_format:H:i'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'pic_ids.required' => 'PIC wajib diisi untuk tugas berstatus To Do.',
            'pic_ids.array' => 'PIC tidak valid.',
            'pic_ids.max' => 'PIC maksimal :max orang.',
            'pic_ids.*.distinct' => 'PIC yang sama dipilih lebih dari sekali.',
            'string' => ':attribute harus berupa teks.',
            'max' => ':attribute maksimal :max karakter.',
            'integer' => ':attribute tidak valid.',
            'boolean' => ':attribute tidak valid.',
            'exists' => ':attribute tidak ditemukan.',
            'priority.in' => ':attribute harus Low, Medium, High, atau Urgent.',
            'status.in' => ':attribute harus Back Log atau To Do.',
            'date_format' => ':attribute harus berformat YYYY-MM-DD.',
            'deadline.after_or_equal' => 'Deadline tidak boleh sebelum hari ini atau sebelum tanggal mulai.',
            'deadline_time.required_with' => 'Jam deadline wajib diisi bersama tanggal deadline.',
            'deadline_time.date_format' => 'Jam deadline harus berformat JJ:MM.',
            'collaborating_division_ids.array' => 'Divisi kolaborator tidak valid.',
            'collaborating_division_ids.*.distinct' => 'Divisi kolaborator yang sama dipilih lebih dari sekali.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'Judul tugas',
            'description' => 'Deskripsi',
            'category_id' => 'Kategori',
            'division_id' => 'Divisi',
            'platform_id' => 'Platform',
            'pic_ids' => 'PIC',
            'pic_ids.*' => 'PIC',
            'priority' => 'Prioritas',
            'status' => 'Status awal',
            'start_date' => 'Tanggal mulai',
            'deadline' => 'Deadline',
            'deadline_time' => 'Jam deadline',
            'collaborating_division_ids' => 'Divisi kolaborator',
            'collaborating_division_ids.*' => 'Divisi kolaborator',
        ];
    }

    /**
     * A deadline set for today must not end at a time that has already passed.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $validator->errors()->hasAny(['deadline', 'deadline_time']) && $this->filled(['deadline', 'deadline_time'])) {
                    $this->rejectPastDeadline($validator, $this->input('deadline'), $this->input('deadline_time'));
                }
            },
        ];
    }

    /**
     * Add an error when the deadline's date and time lie in the past.
     */
    protected function rejectPastDeadline(Validator $validator, string $date, string $time): void
    {
        if (Carbon::createFromFormat('!Y-m-d H:i', "{$date} {$time}")->isPast()) {
            $validator->errors()->add('deadline_time', 'Jam deadline sudah lewat. Pilih jam yang belum terlewati.');
        }
    }

    /**
     * A Supervisor may only create tasks for the division they manage; Admin and Managers for every division.
     */
    protected function creatableDivision(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_numeric($value) || ! Division::whereKey($value)->exists()) {
                return;
            }

            if (! Division::query()->taskCreatableBy($this->user())->whereKey($value)->exists()) {
                $fail('Anda hanya dapat membuat tugas untuk divisi yang Anda kelola.');
            }
        };
    }

    /**
     * A PIC must be an active Staff, Freelance, or Supervisor account. Admin and Managers may pick PICs from every
     * division; a Supervisor only adds PICs from the division they manage, but may keep a current PIC of another
     * division.
     */
    protected function assignablePic(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_numeric($value)) {
                return;
            }

            if (! Account::query()->assignableAsPic()->whereKey($value)->exists()) {
                $fail('PIC harus akun aktif dengan role Staff, Freelance, atau Supervisor.');

                return;
            }

            if (in_array((int) $value, $this->currentPicIds(), true)) {
                return;
            }

            if (! Account::query()->assignableAsPicBy($this->user())->whereKey($value)->exists()) {
                $fail('Supervisor hanya dapat menugaskan PIC dari divisinya sendiri.');
            }
        };
    }

    /**
     * Active PICs the task already has; a new task has none.
     *
     * @return array<int, int>
     */
    protected function currentPicIds(): array
    {
        return [];
    }
}
