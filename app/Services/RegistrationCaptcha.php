<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class RegistrationCaptcha
{
    public function verify(Request $request): void
    {
        if (! config('captcha.enabled')) {
            return;
        }

        if (! config('captcha.sitekey') || ! config('captcha.secret')) {
            throw ValidationException::withMessages(['h-captcha-response' => 'The security check is temporarily unavailable. Please try again later.']);
        }

        $data = $request->validate(['h-captcha-response' => ['required', 'string', 'max:10000']], [
            'h-captcha-response.required' => 'Please complete the security check.',
        ]);

        try {
            $response = Http::asForm()->connectTimeout(3)->timeout(10)->post('https://api.hcaptcha.com/siteverify', [
                'secret' => config('captcha.secret'),
                'sitekey' => config('captcha.sitekey'),
                'response' => $data['h-captcha-response'],
                'remoteip' => $request->ip(),
            ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['h-captcha-response' => 'The security check could not connect. Please try again.']);
        }

        if (! $response->successful() || $response->json('success') !== true) {
            throw ValidationException::withMessages(['h-captcha-response' => 'The security check could not be verified. Please complete it again.']);
        }
    }
}
