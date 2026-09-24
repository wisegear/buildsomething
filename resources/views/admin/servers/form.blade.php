@extends('layouts.site')
@section('title', ($server->exists ? 'Edit server' : 'Add server').' — blogshed.uk')
@section('content')
<section class="wrap admin-area">
    @include('admin.navigation')
    <a class="text-link" href="{{ route('admin.servers.index') }}">← All servers</a>
    <div class="section-heading"><div><h1>{{ $server->exists ? 'Edit server' : 'Add server' }}</h1><p>Keep your hosting details in one place.</p></div></div>
    @if($errors->any())<div class="error-box" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="panel server-form" method="POST" action="{{ $server->exists ? route('admin.servers.update', $server) : route('admin.servers.store') }}">
        @csrf @if($server->exists) @method('PUT') @endif
        @foreach(['name' => 'Server name', 'ip_address' => 'IP address', 'location' => 'Location', 'provider' => 'Provider name', 'monthly_cost' => 'Cost per month (GBP)'] as $field => $label)
            <div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $server->$field) }}" required @if($field === 'monthly_cost') type="number" min="0" max="99999999.99" step="0.01" @else type="text" maxlength="{{ $field === 'ip_address' ? 45 : 255 }}" @endif @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>@error($field)<p class="field-error" id="{{ $field }}-error">{{ $message }}</p>@enderror</div>
        @endforeach
        <div><label for="active">Active</label><select id="active" name="active" required @error('active') aria-invalid="true" aria-describedby="active-error" @enderror>
            <option value="1" @selected((string) old('active', $server->exists ? (int) $server->active : 1) === '1')>Yes</option>
            <option value="0" @selected((string) old('active', $server->exists ? (int) $server->active : 1) === '0')>No</option>
        </select>@error('active')<p class="field-error" id="active-error">{{ $message }}</p>@enderror</div>
        <div class="row-actions"><button class="button">{{ $server->exists ? 'Save changes' : 'Add server' }}</button><a class="text-link" href="{{ route('admin.servers.index') }}">Cancel</a></div>
    </form>
</section>
@endsection
