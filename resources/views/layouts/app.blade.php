@extends('layouts.site')
@section('title', 'Your profile — blogshed.uk')
@section('content')
<div class="wrap" style="padding-top:35px">@isset($header){{ $header }}@endisset</div>
{{ $slot }}
@endsection
