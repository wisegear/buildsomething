<?php

namespace App\Services;

use App\Models\SignupAssessment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SignupRecorder
{
    // Called inside the account creation transaction, before registration events.
    public function record(User $user, Request $request): void
    {
        $ip = $request->ip();
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? inet_ntop(inet_pton($ip)) : null;

        // Serialize same-IP PostgreSQL signups, including the first signup.
        // Transaction-scoped locks release automatically on commit/rollback.
        if ($ip !== null && DB::connection()->getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['signup:'.$ip]);
        }

        $previous = $ip === null ? 0 : SignupAssessment::where('registration_ip', $ip)->count();

        $user->signupAssessment()->create([
            'registration_ip' => $ip,
            'user_agent' => $request->userAgent(),
            'registered_at' => $user->created_at,
            'accept_language' => $request->header('Accept-Language'),
            'referrer' => $request->header('Referer'),
            'previous_ip_registrations' => $previous,
            'email_domain' => strtolower(substr(strrchr($user->email, '@'), 1)),
        ]);
    }
}
