<?php

use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\ServerController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CustomerBlogController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\WordPressPasswordController;
use App\Models\Server;
use Illuminate\Support\Facades\Route;

Route::get('/', [PagesController::class, 'index'])->name('home');

Route::view('/about', 'about')->name('about');
Route::view('/terms', 'terms')->name('terms');
Route::get('/blog', [PagesController::class, 'blog'])->name('blog.index');
Route::get('/blog/{slug}', [PagesController::class, 'post'])->name('blog.show');
Route::get('/account', function () {
    return response()->view('account', ['customerBlog' => request()->user()->customerBlog()->first(), 'locations' => Server::where('active', true)->orderBy('location')->pluck('location')->unique()->values()])
        ->header('Cache-Control', 'no-store, private');
})->middleware('auth')->name('account');
Route::post('/account/blog', [CustomerBlogController::class, 'store'])->middleware(['auth', 'throttle:5,1'])->name('account.blog.store');
Route::post('/account/blog/password', [WordPressPasswordController::class, 'store'])->middleware(['auth', 'throttle:5,1'])->name('account.blog.password');
Route::middleware(['auth', 'can:manage-blog'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('support', [SupportController::class, 'adminIndex'])->name('support.index');
    Route::get('/', DashboardController::class)->name('index');
    Route::resource('blogs', BlogController::class)->only(['index', 'destroy']);
    Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::resource('servers', ServerController::class)->except('show');
    Route::resource('posts', PostController::class)->except('show');
});
Route::middleware('auth')->group(function () {
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:10,1')->name('support.store');
    Route::get('/support/{ticket}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/replies', [SupportController::class, 'reply'])->middleware('throttle:20,1')->name('support.reply');
    Route::patch('/support/{ticket}/status', [SupportController::class, 'status'])->middleware('throttle:20,1')->name('support.status');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
