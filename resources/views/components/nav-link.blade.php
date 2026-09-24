@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center border-b-2 border-amber-200 px-1 pt-1 text-sm font-medium leading-5 text-white focus:border-amber-100 focus:outline-none transition duration-150 ease-in-out'
            : 'inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-stone-400 transition duration-150 ease-in-out hover:border-white/30 hover:text-white focus:border-white/30 focus:outline-none focus:text-white';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
