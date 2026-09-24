<?php

return [
    // All workers must use the same persistent cache for host-wide locks.
    'lock_store' => env('BLOGSHED_LOCK_STORE', 'database'),
    'ssh_host' => env('BLOGSHED_SSH_HOST'),
    'ssh_user' => env('BLOGSHED_SSH_USER', 'blogshed-deploy'),
    'ssh_key' => env('BLOGSHED_SSH_KEY'),
    'ssh_port' => (int) env('BLOGSHED_SSH_PORT', 22),
];
