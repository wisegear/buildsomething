@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full border-l-4 border-amber-200 bg-white/10 py-2 ps-3 pe-4 text-start text-base font-medium text-white transition duration-150 ease-in-out focus:border-amber-100 focus:bg-white/15 focus:text-white focus:outline-none'
            : 'block w-full border-l-4 border-transparent py-2 ps-3 pe-4 text-start text-base font-medium text-stone-300 transition duration-150 ease-in-out hover:border-white/20 hover:bg-white/5 hover:text-white focus:border-white/20 focus:bg-white/5 focus:text-white focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
