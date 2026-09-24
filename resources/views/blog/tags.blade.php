@if($post->tags->isNotEmpty())
<div class="post-tags" aria-label="Post topics">
    @foreach($post->tags as $postTag)
        <a class="tag-link" href="{{ route('blog.index', ['tag' => $postTag->id]) }}">{{ $postTag->name }}</a>
    @endforeach
</div>
@endif
