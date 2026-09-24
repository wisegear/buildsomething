@props(['signedInLabel' => 'View panel'])
<a {{ $attributes->class(['button']) }} href="{{ auth()->check() ? route('account') : route('register') }}">@auth {{ $signedInLabel }} <span aria-hidden="true">↗</span>@else{{ $slot }}@endauth</a>
