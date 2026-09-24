<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('blogshed:make-admin {email : The email of an existing registered account}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Register the account first. No account was changed.');

        return 1;
    }

    $user->forceFill(['is_admin' => true])->save();
    $this->info('Admin access granted. The existing password and activation status are unchanged.');

    return 0;
})->purpose('Explicitly grant admin access to an existing account');
