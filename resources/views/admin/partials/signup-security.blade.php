<details>
    <summary>Signup / Security</summary>
    @if($assessment)
        <dl style="overflow-wrap: anywhere; white-space: normal;">
            @foreach([
                'registration_ip' => 'Registration IP', 'registered_at' => 'Registered at',
                'previous_ip_registrations' => 'Previous signups from this IP',
                'user_agent' => 'User agent', 'accept_language' => 'Accept-Language',
                'referrer' => 'Referrer', 'email_domain' => 'Email domain',
            ] as $field => $label)
                <dt><strong>{{ $label }}</strong></dt><dd>{{ $assessment->$field ?? 'Not available' }}</dd>
            @endforeach
        </dl>
    @else
        <p>No signup information recorded for this account.</p>
    @endif
</details>
