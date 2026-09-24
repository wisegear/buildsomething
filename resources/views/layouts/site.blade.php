<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'blogshed.uk — Your little corner of the internet')</title><meta name="description" content="@yield('description', 'Your shed to blog. A free WordPress blog for everyday stories, favourite things, and ideas that deserve a little space of their own.')">
@yield('head')@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body><a href="#main" class="skip-link">Skip to content</a><header class="site-header wrap"><a href="{{ route('home') }}" class="brand" aria-label="blogshed.uk home"><span class="brand-mark shed-mark" aria-hidden="true">⌂</span>blogshed<span class="brand-dot">.uk</span></a>
<nav aria-label="Main navigation"><a href="{{ route('home') }}#how-it-works">How it works</a><a href="{{ route('blog.index') }}" @if(request()->routeIs('blog.*')) aria-current="page" @endif>Field notes</a><a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>About</a><a href="{{ route('terms') }}" @if(request()->routeIs('terms')) aria-current="page" @endif>Terms</a></nav>
<div class="nav-actions">
    @auth
        <details class="account-menu" x-data @click.outside="$el.open = false" @keydown.escape.prevent.stop="$el.open = false; $refs.accountToggle.focus()" @focusout="if (!$el.contains($event.relatedTarget)) $el.open = false">
            <summary x-ref="accountToggle"><span class="account-name">{{ auth()->user()->name }}</span><span class="account-chevron" aria-hidden="true">⌄</span></summary>
            <div class="account-dropdown">
                <a href="{{ route('account') }}">Blog Page</a>
                <a href="{{ route('profile.edit') }}">Manage profile</a>
                <a href="{{ route('terms') }}" @if(request()->routeIs('terms')) aria-current="page" @endif>Blog service terms</a>
                <a href="{{ route('support.index') }}">Support @if($supportWaitingCount ?? 0)<span class="badge" aria-label="{{ $supportWaitingCount }} tickets awaiting your reply">{{ $supportWaitingCount }}</span>@endif</a>
                @can('manage-blog')<a href="{{ route('admin.index') }}">Admin</a>@endcan
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Logout</button></form>
            </div>
        </details>
    @else
        <div class="guest-links"><a href="{{ route('login') }}">Login</a><span aria-hidden="true">/</span><a href="{{ route('register') }}">Register</a></div>
    @endauth
</div></header>
<main id="main">@if(session('status'))<div class="wrap"><div class="notice" role="status">{{ session('status') }}</div></div>@endif @yield('content')</main>
<footer class="site-footer wrap"><div><a class="brand" href="{{ route('home') }}">blogshed<span class="brand-dot">.uk</span></a><p>A little corner of the internet. Your shed to blog.</p></div><div class="footer-links"><a href="{{ route('blog.index') }}">Field notes</a><a href="{{ route('about') }}">About blogshed.uk</a><a href="{{ route('terms') }}">Blog service terms</a></div><div class="footer-bottom"><span>© {{ date('Y') }} blogshed.uk. Room for your words.</span><span>Start small. Make it yours.</span></div></footer></body></html>
