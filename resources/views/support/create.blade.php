@extends('layouts.site')
@section('title', 'Create a support ticket — blogshed.uk')
@section('content')
<section class="wrap admin-area support-area">
    <a class="text-link" href="{{ route('support.index') }}">← Support tickets</a>
    <h1>Create a support ticket</h1><p>Please describe your question or issue. Do not include passwords or other secrets.</p>
    <form class="panel support-form" method="POST" action="{{ route('support.store') }}">
        @csrf
        <label for="title">Ticket title</label><input id="title" name="title" maxlength="100" required value="{{ old('title') }}">
        @error('title')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        @include('support.message-field')
        <button class="button" type="submit">Create ticket</button>
    </form>
</section>
@endsection
