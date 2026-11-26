<?php

namespace App\Http\Requests\Agenda;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The fields of an agenda, for both adding and changing one. Authorization is handled by CalendarEventPolicy on the
 * route; the divisions an account may name are checked here.
 */
class SaveCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_shared' => ['required', 'boolean'],
            'division_id' => ['nullable', 'integer', Rule::exists('divisions', 'id')],
            'target_division_ids' => ['exclude_if:is_shared,true', 'exclude_if:is_shared,1', 'required', 'array', 'min:1'],
            'target_division_ids.*' => ['integer', 'distinct', Rule::exists('divisions', 'id')],
        ];
    }

    /**
     * A Supervisor organises and targets only their own division. A Manager or Supervisor sees internal agendas of
     * their own division only, so an internal agenda they make must include it or they would lose sight of it.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Account $user */
            $user = $this->user();

            if ($user->isAdmin()) {
                return;
            }

            $ownDivision = $user->division_id;
            $targets = array_map('intval', $this->input('target_division_ids', []));
            $isShared = $this->boolean('is_shared');

            if ($user->isSupervisor() && $this->filled('division_id') && (int) $this->input('division_id') !== $ownDivision) {
                $validator->errors()->add('division_id', 'Supervisor hanya dapat membuat agenda untuk divisinya sendiri.');
            }

            if (! $isShared && $user->isSupervisor() && array_diff($targets, [$ownDivision]) !== []) {
                $validator->errors()->add('target_division_ids', 'Supervisor hanya dapat menujukan agenda internal ke divisinya sendiri.');
            }

            if (! $isShared && ! in_array($ownDivision, $targets, true)) {
                $validator->errors()->add('target_division_ids', 'Agenda internal harus menyertakan divisi Anda agar tetap dapat Anda lihat.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'max' => ':attribute maksimal :max karakter.',
            'integer' => ':attribute tidak valid.',
            'boolean' => ':attribute tidak valid.',
            'exists' => ':attribute tidak ditemukan.',
            'date_format' => ':attribute harus berformat YYYY-MM-DD.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'target_division_ids.required' => 'Pilih minimal satu divisi untuk agenda internal.',
            'target_division_ids.min' => 'Pilih minimal satu divisi untuk agenda internal.',
            'target_division_ids.array' => 'Divisi tujuan tidak valid.',
            'target_division_ids.*.distinct' => 'Divisi yang sama dipilih lebih dari sekali.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'Judul agenda',
            'description' => 'Deskripsi',
            'start_date' => 'Tanggal mulai',
            'end_date' => 'Tanggal selesai',
            'is_shared' => 'Jenis agenda',
            'division_id' => 'Divisi penyelenggara',
            'target_division_ids' => 'Divisi tujuan',
            'target_division_ids.*' => 'Divisi tujuan',
        ];
    }
}
