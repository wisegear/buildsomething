<?php

return [
    // Explicit proxy IPs/CIDRs only. Empty means no trusted forwarded headers.
    'proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),
];
