@extends('layouts.site')
@section('title', 'blogshed.uk — Your own free WordPress blog')
@section('description', 'Your own free WordPress blog, with hosting included. A little corner of the internet for your ideas, hobbies, and everyday stories.')
@section('content')
<section class="hero wrap">
    <div class="hero-copy">
        <p class="eyebrow"><span class="status-dot"></span> A little space. All yours.</p>
        <h1>Your own free <em>WordPress blog.</em></h1>
        <p class="lead">A little corner of the internet for your ideas, hobbies, and everyday stories. Make it your own with free WordPress hosting from blogshed.uk.</p>
        <x-blog-cta signed-in-label="Go to your dashboard">Start your free blog <span aria-hidden="true">↗</span></x-blog-cta>
        <p class="button-note">Free. No credit card needed.</p>
        <div class="hero-bottom"><img class="wordpress-mini" src="{{ asset('images/wordpress-mark.png') }}" alt="" width="32" height="32"><span>A free WordPress blog.<br><strong>Make yourself at home.</strong></span></div>
    </div>
    <div class="shed-scene" role="img" aria-label="A green garden shed with a glowing window, an open notebook, and a sign reading your shed to blog">
        <div class="shed-sun"></div><span class="shed-cloud cloud-one"></span><span class="shed-cloud cloud-two"></span>
        <div class="shed-ground"></div>
        <div class="garden-shed">
            <div class="shed-roof"></div>
            <div class="shed-wall"><div class="shed-sign">your shed to blog</div><div class="shed-window"><span></span></div><div class="shed-door"><span class="door-note">Gone<br><em>blogging.</em></span><i></i></div></div>
        </div>
        <div class="shed-planter"><span>✳</span><span>✳</span><i></i></div>
        <div class="shed-notebook"><span>THOUGHTS & LITTLE THINGS</span><strong>Something worth<br><em>writing down.</em></strong><div></div><div></div><div></div></div>
        <span class="shed-caption">A small space for whatever grows.</span>
    </div>
</section>
<div class="promise-strip"><div class="wrap"><span>Free WordPress hosting</span><span aria-hidden="true" class="promise-divider">·</span><span>Make it your own</span><span aria-hidden="true" class="promise-divider">·</span><span>Publish your stories</span></div></div>
<section class="section wrap" id="how-it-works">
    <div class="section-heading"><div><p class="eyebrow">COME ON IN</p><h2>From first idea to first post</h2></div><p>You don’t need to call yourself a writer. If there’s something you love, notice, make, or wonder about, there’s a place for it here.</p></div>
    <div class="steps">
        <article><span class="step-icon">01 <span>⌂</span></span><h3>Create your account</h3><p>Get started with a free account and a space for your blog.</p></article>
        <article><span class="step-icon peach">02 <span>✳</span></span><h3>Make your blog your own</h3><p>Choose a name and personalise its look.</p></article>
        <article><span class="step-icon lavender">03 <span>↗</span></span><h3>Publish your first post</h3><p>Share an idea, a story, or something you love.</p></article>
    </div>
</section>
<section class="manifesto wrap">
    <div><p class="eyebrow">SMALL IS A LOVELY PLACE TO START</p><h2>Somewhere to be<br><em>wonderfully you.</em></h2><p>The best thing about a shed? It doesn’t have to impress anyone. It’s a place for your interests, your half-finished ideas, and the things you could talk about for hours.</p><p>blogshed.uk brings that feeling to blogging. A little home for your words, with free WordPress hosting and room to find your own rhythm. Pull up a chair. Put the kettle on. See what comes out.</p><a class="text-link" href="{{ route('about') }}">The story behind blogshed.uk <span>↗</span></a></div>
    <div class="idea-board"><span class="eyebrow">WHAT’S IN YOUR SHED?</span><div class="idea idea-one">Notes from the allotment <span>☀</span></div><div class="idea idea-two">Things I made this weekend <span>✳</span></div><div class="idea idea-three">Small adventures, good stories <span>♡</span></div><p>Whatever you’re into, there’s room.</p></div>
</section>
<section class="section wrap"><div class="section-heading"><div><p class="eyebrow">A LITTLE HELP FROM NEXT DOOR</p><h2>Notes from the shed.</h2></div><a class="text-link" href="{{ route('blog.index') }}">All field notes <span>↗</span></a></div><div class="post-grid">@forelse($posts as $post) @include('blog.card') @empty <div class="empty-state"><span>✳</span><h3>The kettle’s on. The first notes are brewing.</h3><p>Friendly guides to starting your blog, finding your voice, and making yourself at home are on their way.</p></div>@endforelse</div></section>
<section class="final-cta wrap"><p class="eyebrow">THERE’S A LITTLE CORNER HERE FOR YOU</p><h2>Start your own free blog.</h2><p>Your shed. Your stories. Free WordPress hosting included.</p><x-blog-cta signed-in-label="Go to your dashboard">Start your free blog <span aria-hidden="true">↗</span></x-blog-cta><span class="cta-flower" aria-hidden="true">✳</span></section>
@endsection
