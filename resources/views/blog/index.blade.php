@extends('layouts.site')
@section('title', $tag ? $tag->name.' — Field notes — blogshed.uk' : 'Field notes — blogshed.uk')
@section('content')
<section class="page-intro wrap">
    <p class="eyebrow">LEARN A LITTLE. MAKE SOMETHING.</p>
    <h1>@if($tag)Notes about <em>{{ $tag->name }}.</em>@else Notes for <em>the journey.</em>@endif</h1>
    <p class="lead">Friendly guides, small discoveries, and a little encouragement to get your ideas out into the world.</p>
</section>
<section class="wrap section blog-list">
    @if($tags->isNotEmpty())
        <nav class="topic-filter" aria-label="Browse by topic">
            <a class="tag-link" href="{{ route('blog.index') }}" @if(!$tag) aria-current="page" @endif>All topics</a>
            @foreach($tags as $topic)
                <a class="tag-link" href="{{ route('blog.index', ['tag' => $topic->id]) }}" @if($tag?->id === $topic->id) aria-current="page" @endif>{{ $topic->name }}</a>
            @endforeach
        </nav>
    @endif
    <div class="post-grid">
        @forelse($posts as $post)
            @include('blog.card')
        @empty
            <div class="empty-state"><span>✳</span><h2>A fresh notebook.</h2><p>The first field notes are on their way. In the meantime, <a href="{{ route('about') }}">get to know blogshed.uk</a>.</p></div>
        @endforelse
    </div>
    <div class="pagination">{{ $posts->links() }}</div>
</section>
@endsection
