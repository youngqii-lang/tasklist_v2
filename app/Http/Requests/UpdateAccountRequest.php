<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAccountRequest extends StoreAccountRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->account()) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The password is optional: leaving it empty keeps the current password.
     * The role is only accepted from admins; for anybody else it is left untouched.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fullname' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'alpha_num:ascii',
                'max:255',
                Rule::unique('accounts', 'username')->ignore($this->account()),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'role_id' => $this->user()->can('assignRole', Account::class)
                ? ['required', 'integer', Rule::exists('roles', 'id')]
                : ['exclude'],
            'division_id' => [Rule::requiredIf(! $this->resultsInAdmin()), 'nullable', 'integer', Rule::exists('divisions', 'id')],
        ];
    }

    /**
     * The application must always keep at least one active admin.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $account = $this->account();

                if ($validator->errors()->has('role_id') || ! $account->isAdmin() || $this->resultsInAdmin()) {
                    return;
                }

                $hasOtherActiveAdmin = Account::query()
                    ->whereKeyNot($account->id)
                    ->where('is_active', true)
                    ->whereHas('role', fn (Builder $role) => $role->where('code_role', Role::ADMIN))
                    ->exists();

                if (! $hasOtherActiveAdmin) {
                    $validator->errors()->add('role_id', 'Role tidak dapat diubah karena akun ini satu-satunya Admin aktif.');
                }
            },
        ];
    }

    /**
     * Without a role in the request, the account keeps its current role.
     */
    protected function resultsInAdmin(): bool
    {
        if (! $this->user()->can('assignRole', Account::class)) {
            return $this->account()->isAdmin();
        }

        return parent::resultsInAdmin();
    }

    /**
     * Get the account being updated.
     */
    public function account(): Account
    {
        return $this->route('account');
    }
}
