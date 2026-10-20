<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'string', 'max:100'],
            'email'        => ['sometimes', 'email', 'unique:users,email,' . $this->route('user')],
            'password'     => ['sometimes', 'confirmed', Password::defaults()],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'role'         => ['nullable', 'string', 'exists:roles,name'],
        ];
    }
}
