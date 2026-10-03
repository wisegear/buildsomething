@extends('layouts.site')
@section('title', $post->title.' — blogshed.uk')
@section('description', $post->seo_summary)
@section('head')
<link rel="canonical" href="{{ route('blog.show', $post->slug) }}">
<meta property="og:type" content="article">
<meta property="og:site_name" content="blogshed.uk">
<meta property="og:url" content="{{ route('blog.show', $post->slug) }}">
<meta property="og:title" content="{{ $post->title }}">
<meta property="og:description" content="{{ $post->seo_summary }}">
<meta property="article:published_time" content="{{ $post->post_date->toIso8601String() }}">
<meta name="twitter:card" content="{{ $post->image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $post->title }}">
<meta name="twitter:description" content="{{ $post->seo_summary }}">
@if($post->image)
    @php($socialImageUrl = url(app(\App\Services\BlogImages::class)->url($post->image, 'large')))
    <meta property="og:image" content="{{ $socialImageUrl }}">
    <meta property="og:image:alt" content="{{ $post->image_alt ?? $post->title }}">
    <meta name="twitter:image" content="{{ $socialImageUrl }}">
    <meta name="twitter:image:alt" content="{{ $post->image_alt ?? $post->title }}">
@endif
@endsection
@section('content')<article class="article wrap"><a class="text-link" href="{{ route('blog.index') }}">← All field notes</a><p class="eyebrow">{{ $post->post_date->format('j F Y') }} · {{ max(1, (int) ceil(str_word_count(strip_tags($post->body))/200)) }} MIN READ</p><h1>{{ $post->title }}</h1><p class="lead">{{ $post->seo_summary }}</p>@if($post->image)<img class="article-cover" src="{{ app(\App\Services\BlogImages::class)->url($post->image, 'large') }}" srcset="{{ app(\App\Services\BlogImages::class)->url($post->image, 'small') }} 800w, {{ app(\App\Services\BlogImages::class)->url($post->image, 'large') }} 1600w" sizes="(max-width: 900px) 100vw, 860px" alt="{{ $post->image_alt ?? '' }}">@endif
@include('blog.tags')
<div class="prose">{!! $post->body !!}</div>
@include('blog.share')
<div class="article-end"><h3>Your next idea starts with a first step.</h3><x-blog-cta>Create your free account ↗</x-blog-cta></div></article>@endsection
