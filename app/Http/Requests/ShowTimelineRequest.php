<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Services\TimelineService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowTimelineRequest extends FormRequest
{
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
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'mode' => ['nullable', 'string', Rule::in(array_keys(TimelineService::MODE_DAYS))],
            'division_id' => ['nullable', 'integer', Rule::exists('divisions', 'id')],
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
            'date_format' => ':attribute harus berformat YYYY-MM-DD.',
            'in' => ':attribute yang dipilih tidak valid.',
            'integer' => ':attribute tidak valid.',
            'exists' => ':attribute yang dipilih tidak valid.',
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
            'start_date' => 'Tanggal mulai periode',
            'mode' => 'Mode tampilan',
            'division_id' => 'Divisi',
        ];
    }
}
