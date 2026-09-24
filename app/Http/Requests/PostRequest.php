<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'], 'seo_summary' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:200000'], 'post_date' => ['required', 'date_format:Y-m-d'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=6000,max_height=6000'],
            'image_alt' => ['nullable', 'string', 'max:255'], 'remove_image' => ['sometimes', 'boolean'], 'action' => ['required', 'in:draft,publish'],
        ];
    }
}
