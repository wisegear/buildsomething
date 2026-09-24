<?php

return [
    'enabled' => env('HCAPTCHA_ENABLED', true),
    'sitekey' => env('NOCAPTCHA_SITEKEY'),
    'secret' => env('NOCAPTCHA_SECRET'),
];
