<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTasksRequest extends FormRequest
{
    /**
     * Columns the Tasklist can be sorted by.
     *
     * @var list<string>
     */
    public const array SORTABLE_COLUMNS = ['title', 'priority', 'status', 'start_date', 'deadline', 'created_at'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Task::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'division_id' => ['nullable', 'integer', Rule::exists('divisions', 'id')],
            'pic_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')],
            'date_field' => ['nullable', 'string', Rule::in(['start_date', 'deadline'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'digits:4'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), ['after_or_equal:date_from'])],
            'priority' => ['nullable', 'string', Rule::in(Task::PRIORITIES)],
            'status' => ['nullable', 'string', Rule::in(array_keys(Task::STATUS_LABELS))],
            'sort' => ['nullable', 'string', Rule::in(self::SORTABLE_COLUMNS)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
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
            'max' => ':attribute maksimal :max.',
            'min' => ':attribute minimal :min.',
            'integer' => ':attribute harus berupa angka.',
            'exists' => ':attribute yang dipilih tidak valid.',
            'in' => ':attribute yang dipilih tidak valid.',
            'digits' => ':attribute harus :digits digit.',
            'date.date_format' => 'Tanggal harus berformat YYYY-MM-DD.',
            'month.date_format' => 'Bulan harus berformat YYYY-MM.',
            'date_from.date_format' => 'Tanggal awal harus berformat YYYY-MM-DD.',
            'date_to.date_format' => 'Tanggal akhir harus berformat YYYY-MM-DD.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
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
            'search' => 'Pencarian',
            'division_id' => 'Divisi',
            'pic_id' => 'PIC',
            'date_field' => 'Dasar tanggal',
            'date' => 'Tanggal',
            'month' => 'Bulan',
            'year' => 'Tahun',
            'date_from' => 'Tanggal awal',
            'date_to' => 'Tanggal akhir',
            'priority' => 'Prioritas',
            'status' => 'Status',
            'sort' => 'Urutan',
            'direction' => 'Arah urutan',
            'per_page' => 'Jumlah per halaman',
            'page' => 'Halaman',
        ];
    }
}
