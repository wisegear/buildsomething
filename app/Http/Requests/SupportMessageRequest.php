<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class SupportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $this->user() !== null && ($ticket === null || $this->user()->can('manage-blog') || $ticket->user_id === $this->user()->id);
    }

    public function rules(): array
    {
        return [
            'title' => [$this->routeIs('support.store') ? 'required' : 'sometimes', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:20000'],
        ];
    }

    public function sanitizedBody(): string
    {
        $config = (new HtmlSanitizerConfig)->allowElement('p')->allowElement('br')
            ->allowElement('strong')->allowElement('b')->allowElement('em')->allowElement('i')
            ->allowElement('u')->allowElement('ul')->allowElement('ol')->allowElement('li')
            ->allowElement('blockquote')->allowElement('a', ['href', 'title'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])->withMaxInputLength(20000);
        $body = (new HtmlSanitizer($config))->sanitize($this->validated('body'));
        if (trim(str_replace("\xc2\xa0", ' ', html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) === '') {
            throw ValidationException::withMessages(['body' => 'Please enter a message.']);
        }

        return $body;
    }
}
