@extends('layouts.site')
@section('title', 'Your account — blogshed.uk')
@if(auth()->user()->activated_at === null || ($customerBlog && in_array($customerBlog->status, ['pending', 'provisioning', 'deleting', 'password_reset_pending', 'password_resetting'])))
    @section('head')<meta http-equiv="refresh" content="10">@endsection
@endif
@section('content')
<section class="page-intro wrap">
    <p class="eyebrow">YOU’RE IN GOOD COMPANY</p>
    <h1>Hello, <em>{{ auth()->user()->name }}.</em></h1>
    <p class="lead">{{ auth()->user()->activated_at ? 'Your account is ready. This is where your next chapter begins.' : 'Welcome to BlogShed. Your account is awaiting activation.' }}</p>
</section>
<section class="wrap account-grid">
    <div class="panel">
        <p class="eyebrow">YOUR FREE WORDPRESS WEBSITE</p>

        @if(auth()->user()->activated_at === null)
            <h2>Your signup is currently being checked.</h2>
            <p role="status">Once an administrator activates your account, you can set up your blog here. This page will update automatically.</p>
        @elseif(! $customerBlog)
            <h2>Let’s get your idea a home.</h2>
            <p>Choose a name for your free WordPress blog. We’ll set it up at yourname.blogshed.uk.</p>
            @if($locations->isEmpty())
                <p role="status">No locations are available right now. Please check back soon.</p>
            @else
            <form x-data="{ location: @js(old('location', $locations->count() === 1 ? $locations->first() : '')), subdomain: @js(old('subdomain', '')), description: @js(old('description', '')), agreed: @js(in_array(old('terms_accepted'), ['1', 1, true, 'yes', 'on', 'true'], true)) }" method="POST" action="{{ route('account.blog.store') }}">
                @csrf
                @if($locations->count() > 1)
                    <label for="location">1. Choose your blog location</label>
                    <select id="location" name="location" x-model="location" required>
                        <option value="">Select a location</option>
                        @foreach($locations as $location)<option value="{{ $location }}" @selected(old('location') === $location)>{{ $location }}</option>@endforeach
                    </select>
                @else
                    <input type="hidden" name="location" value="{{ $locations->first() }}">
                @endif
                @error('location') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                <fieldset :disabled="!location" style="border:0;padding:0;margin:0;min-width:0">
                <label for="subdomain">Your blog address</label>
                <div class="blog-domain-field"><input id="subdomain" name="subdomain" x-model="subdomain" value="{{ old('subdomain') }}" required minlength="3" maxlength="28" pattern="[a-z0-9]+(-[a-z0-9]+)*" autocomplete="off" aria-describedby="subdomain-help"><span>.blogshed.uk</span></div>
                <p id="subdomain-help" class="field-help">Use 3–28 lowercase letters, numbers or hyphens.</p>
                @error('subdomain') <p class="field-error" role="alert">{{ $message }}</p> @enderror

                <label for="description">What do you intend to do with your blog?</label>
                <textarea id="description" name="description" x-model="description" rows="5" maxlength="5000" required aria-describedby="description-help">{{ old('description') }}</textarea>
                <p id="description-help" class="field-help">Tell us what your blog will be about and how you plan to use it (up to 5,000 characters).</p>
                @error('description') <p class="field-error" role="alert">{{ $message }}</p> @enderror

                <div class="blog-terms">
                    <h3>Terms of use</h3>
                    <ul id="blog-terms-list">
                        <li>Keep it legal and don't use blogshed.uk to harm or abuse others</li>
                        <li>No hate speech, threats, scams, spam or malicious content</li>
                        <li>You are responsible for what you publish</li>
                        <li>Serious abuse may result in immediate removal without warning</li>
                        <li>Please keep your own backup of anything important</li>
                    </ul>
                    <p><a class="text-link" href="{{ route('terms') }}">Read the full blog service terms →</a></p>
                    <label class="terms-agreement" for="terms_accepted">
                        <input type="checkbox" id="terms_accepted" name="terms_accepted" value="1" x-model="agreed" required @checked(in_array(old('terms_accepted'), ['1', 1, true, 'yes', 'on', 'true'], true)) aria-describedby="blog-terms-list">
                        <span>I have read and agree to these terms.</span>
                    </label>
                    @error('terms_accepted') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>

                <button class="button" type="submit" :disabled="!location || subdomain.length < 3 || subdomain.length > 28 || !/^[a-z0-9]+(-[a-z0-9]+)*$/.test(subdomain) || !description.trim() || description.length > 5000 || !agreed">Create my blog ↗</button>
                </fieldset>
            </form>
            @endif
        @else
            <h2>Your blog</h2>
            <p><strong>Address:</strong> <a href="https://{{ $customerBlog->domain }}" target="_blank" rel="noopener noreferrer">{{ $customerBlog->domain }} ↗</a></p>
            @if(in_array($customerBlog->status, ['active', 'password_reset_failed', 'password_reset_pending', 'password_resetting']))
                <p>Your blog is ready. Open WordPress to start writing and make it your own.</p>
                <p><a class="button" href="https://{{ $customerBlog->domain }}/wp-admin" target="_blank" rel="noopener noreferrer">Open WordPress admin ↗</a></p>
                <p><strong>WordPress username:</strong> {{ $customerBlog->wp_admin_username }}</p>
                @if($customerBlog->status === 'active' && $customerBlog->wp_admin_password)
                    <p><strong>WordPress password:</strong> <code>{{ $customerBlog->wp_admin_password }}</code></p>
                @endif
                <p class="field-help">Keep these details private. You can reset your administrator password here without needing email.</p>
                @if(in_array($customerBlog->status, ['password_reset_pending', 'password_resetting']))
                    <p role="status">{{ $customerBlog->status === 'password_reset_pending' ? 'Your password reset is queued. It will begin when the server is available.' : 'Your administrator password is being updated.' }} This page will update automatically.</p>
                @else
                    @if($customerBlog->status === 'password_reset_failed')
                        <p role="alert">The password change could not be confirmed. Submit a new reset or contact support. Your website has not been deleted.</p>
                    @endif
                    <div x-data="{ showReset: @js($errors->wordpressPassword->any()) }">
                        <button class="button" type="button" x-on:click="showReset = !showReset" :aria-expanded="showReset" aria-controls="wordpress-password-form">Reset Admin Password</button>
                        <form id="wordpress-password-form" x-show="showReset" x-cloak method="POST" action="{{ route('account.blog.password') }}">
                            @csrf
                            <label for="wp-password">New password</label>
                            <input id="wp-password" name="password" type="password" required minlength="12" maxlength="128" autocomplete="new-password" aria-describedby="wp-password-help">
                            <p id="wp-password-help" class="field-help">Use 12–128 characters. Resetting your password also signs out existing WordPress sessions.</p>
                            <label for="wp-password-confirmation">Confirm new password</label>
                            <input id="wp-password-confirmation" name="password_confirmation" type="password" required minlength="12" maxlength="128" autocomplete="new-password">
                            @foreach($errors->wordpressPassword->all() as $error)<p class="field-error" role="alert">{{ $error }}</p>@endforeach
                            <button class="button" type="submit">Save new password</button>
                        </form>
                    </div>
                @endif
            @elseif(in_array($customerBlog->status, ['deleting', 'deletion_failed']))
                <p role="status">{{ $customerBlog->status === 'deleting' ? 'Your blog is being deleted.' : 'Blog deletion needs attention. Please contact Lee for help.' }}</p>
            @elseif($customerBlog->status === 'failed')
                <p>We couldn’t finish setting up your blog. Your requested name is saved. Please <a href="{{ route('support.index') }}">contact support</a> so we can help.</p>
            @elseif($customerBlog->status === 'pending')
                <p role="status">Your blog setup is queued. It will begin when the server is available. This page will update automatically.</p>
            @else
                <p role="status">Your blog is being set up. This page will update automatically when it’s ready.</p>
            @endif
        @endif
    </div>
    <div class="panel">
        <h3>Make yourself at home</h3>
        <p><a class="text-link" href="{{ route('support.index') }}">Customer support ↗</a></p>
        <p><a class="text-link" href="{{ route('blog.index') }}">Explore the field notes ↗</a></p>
        @can('manage-blog')<p><a class="text-link" href="{{ route('admin.posts.index') }}">Manage blog posts ↗</a></p>@endcan
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-link" type="submit">Log out</button></form>
    </div>
    @if($customerBlog)
        <div class="panel account-terms-reminder" aria-labelledby="terms-reminder-heading">
            <h3 id="terms-reminder-heading">A reminder of your blog terms</h3>
            <ul>
                <li>Keep it legal and don't use blogshed.uk to harm or abuse others</li>
                <li>No hate speech, threats, scams, spam or malicious content</li>
                <li>You are responsible for what you publish</li>
                <li>Serious abuse may result in immediate removal without warning</li>
                <li>Please keep your own backup of anything important</li>
            </ul>
            <p><a class="text-link" href="{{ route('terms') }}">Read the full blog service terms →</a></p>
        </div>
    @endif
</section>
@endsection
