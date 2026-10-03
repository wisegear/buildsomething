@php
    $postUrl = route('blog.show', $post->slug);
    $xShareUrl = 'https://twitter.com/intent/tweet?'.http_build_query(['url' => $postUrl, 'text' => $post->title], '', '&', PHP_QUERY_RFC3986);
    $facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?'.http_build_query(['u' => $postUrl], '', '&', PHP_QUERY_RFC3986);
@endphp
<nav class="article-share" aria-label="Share this post">
    <span>Share this story</span>
    <a class="text-link" href="{{ $xShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Share on X (opens in a new tab)">Share on X <span aria-hidden="true">↗</span></a>
    <a class="text-link" href="{{ $facebookShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Share on Facebook (opens in a new tab)">Share on Facebook <span aria-hidden="true">↗</span></a>
</nav>
