<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

class UpdateTaskPicsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('assign', $this->task()) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer'],
            'pic_ids' => ['present', 'array'],
            'pic_ids.*' => ['integer', 'distinct', $this->assignablePicRule()],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * A task that is already being worked on must keep at least one PIC.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $isRunning = in_array($this->task()->status, [Task::STATUS_TODO, Task::STATUS_IN_PROGRESS], true);

                if ($isRunning && $this->input('pic_ids') === []) {
                    $validator->errors()->add('pic_ids', 'Tugas yang sedang berjalan wajib memiliki minimal satu PIC.');
                }
            },
        ];
    }

    /**
     * Admins may assign active staff of any division; others only staff of the task's division.
     */
    protected function assignablePicRule(): Exists
    {
        $rule = Rule::exists('accounts', 'id')
            ->where('is_active', true)
            ->whereIn('role_id', Role::whereIn('code_role', Role::STAFF_ROLES)->pluck('id')->all());

        return $this->user()?->isAdmin() ? $rule : $rule->where('division_id', $this->task()->division_id);
    }

    /**
     * Get the task whose PICs are changed.
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
            'present' => ':attribute wajib dikirim.',
            'array' => ':attribute tidak valid.',
            'integer' => ':attribute tidak valid.',
            'max' => ':attribute maksimal :max karakter.',
            'version.required' => 'Versi data tugas wajib dikirim.',
            'pic_ids.*.distinct' => 'PIC yang sama tidak boleh dipilih lebih dari sekali.',
            'pic_ids.*.exists' => 'PIC harus akun Staff aktif yang berada dalam kewenangan Anda.',
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
            'pic_ids' => 'PIC',
            'pic_ids.*' => 'PIC',
            'note' => 'Catatan',
        ];
    }
}
