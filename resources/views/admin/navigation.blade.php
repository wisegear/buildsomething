<nav class="admin-navigation" aria-label="Admin navigation">
    <a href="{{ route('admin.index') }}" @if(request()->routeIs('admin.index')) aria-current="page" @endif>Overview</a>
    <a href="{{ route('admin.posts.index') }}" @if(request()->routeIs('admin.posts.*')) aria-current="page" @endif>Blog posts</a>
    <a href="{{ route('admin.blogs.index') }}" @if(request()->routeIs('admin.blogs.*')) aria-current="page" @endif>Blogs</a>
    <a href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>Users</a>
    <a href="{{ route('admin.servers.index') }}" @if(request()->routeIs('admin.servers.*')) aria-current="page" @endif>Servers</a>
    <a href="{{ route('admin.support.index') }}" @if(request()->routeIs('admin.support.*', 'support.show')) aria-current="page" @endif>Support</a>
</nav>
