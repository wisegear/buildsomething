<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-blog') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
            'location' => ['required', 'string', 'max:255'],
            'monthly_cost' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'provider' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ];
    }
}
