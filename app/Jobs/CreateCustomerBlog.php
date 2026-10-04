<?php

namespace App\Jobs;

use App\Jobs\Middleware\SerializeWordPressOperations;
use App\Models\CustomerBlog;
use App\Services\WordPressProvisioner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateCustomerBlog implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 330;

    public bool $failOnTimeout = true;

    public bool $remoteOperationUncertain = false;

    public function middleware(): array
    {
        return [new SerializeWordPressOperations];
    }

    public function __construct(public int $blogId) {}

    public function handle(WordPressProvisioner $provisioner): void
    {
        // Only one worker can claim this request, even when dispatches overlap.
        $claimed = CustomerBlog::whereKey($this->blogId)->where('status', 'pending')
            ->update(['status' => 'provisioning']);
        if ($claimed !== 1) {
            return;
        }

        $blog = CustomerBlog::findOrFail($this->blogId);

        if ($blog->user?->banned_at !== null) {
            $blog->update(['status' => 'failed', 'failure_reason' => 'Banned accounts cannot create blogs.']);

            return;
        }

        if ($blog->user?->activated_at === null) {
            $blog->update(['status' => 'failed', 'failure_reason' => 'Account activation is required before blog setup.']);

            return;
        }

        try {
            $credentials = $provisioner->create($blog, function (bool $uncertain): void {
                $this->remoteOperationUncertain = $uncertain;
            });
            $this->remoteOperationUncertain = false;
            $blog->update([
                'status' => 'active',
                'wp_admin_username' => $credentials['username'],
                'wp_admin_password' => $credentials['password'],
                'provisioned_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // Process/database exceptions can contain command output or credential bindings.
            Log::error('Blog provisioning failed', ['blog_id' => $blog->id, 'exception_type' => $exception::class]);
            $blog->update(['status' => 'failed', 'failure_reason' => 'Setup could not be completed. Please contact Lee for help.']);
        }
    }

    public function failed(?Throwable $exception): void
    {
        CustomerBlog::whereKey($this->blogId)->whereIn('status', ['pending', 'provisioning'])->update([
            'status' => 'failed',
            'failure_reason' => 'Setup could not be completed. Please contact Lee for help.',
        ]);
    }
}
