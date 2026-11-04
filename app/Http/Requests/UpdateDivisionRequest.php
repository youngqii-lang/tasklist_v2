<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Unique;

class UpdateDivisionRequest extends StoreDivisionRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('division')) ?? false;
    }

    /**
     * Build the unique rule for a division column, ignoring the division being edited.
     */
    protected function uniqueRule(string $column): Unique
    {
        return parent::uniqueRule($column)->ignore($this->route('division'));
    }
}
