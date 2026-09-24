<?php

namespace App\Http\Requests;

use App\Models\Tag;
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
            'tags' => ['bail', 'nullable', 'string', 'max:1000', function ($attribute, $value, $fail) {
                $names = Tag::names($value);
                if (count($names) > 10) {
                    $fail('Use no more than 10 tags per post.');
                }
                foreach ($names as $name) {
                    if (mb_strlen($name) > 50 || preg_match('/[\x00-\x1F\x7F<>]/u', $name)) {
                        $fail('Each tag must be at most 50 characters and contain no HTML or control characters.');
                        break;
                    }
                }
            }],
            'title' => ['required', 'string', 'max:255'], 'seo_summary' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:200000'], 'post_date' => ['required', 'date_format:Y-m-d'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=6000,max_height=6000'],
            'image_alt' => ['nullable', 'string', 'max:255'], 'remove_image' => ['sometimes', 'boolean'], 'action' => ['required', 'in:draft,publish'],
        ];
    }
}
