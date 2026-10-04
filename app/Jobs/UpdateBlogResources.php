<?php

namespace App\Jobs;

use App\Jobs\Middleware\SerializeWordPressOperations;
use App\Models\CustomerBlog;
use App\Services\WordPressProvisioner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateBlogResources implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 330;

    public bool $failOnTimeout = true;

    public bool $remoteOperationUncertain = false;

    public function __construct(public int $blogId, public string $updateToken) {}

    public function middleware(): array
    {
        return [new SerializeWordPressOperations];
    }

    public function freshForDeferral(): self
    {
        return new self($this->blogId, $this->updateToken);
    }

    public function handle(WordPressProvisioner $provisioner): void
    {
        $claimed = CustomerBlog::whereKey($this->blogId)
            ->where('resource_update_token', $this->updateToken)->where('status', 'resource_update_pending')
            ->update(['status' => 'resource_updating']);
        if ($claimed !== 1) {
            return;
        }

        try {
            $blog = CustomerBlog::findOrFail($this->blogId);
            $provisioner->updateResources($blog, function (bool $uncertain): void {
                $this->remoteOperationUncertain = $uncertain;
            });
            $this->remoteOperationUncertain = false;
            $blog->update([
                'status' => 'active',
                'workers' => $blog->pending_workers,
                'memory_mb' => $blog->pending_memory_mb,
                'pending_workers' => null,
                'pending_memory_mb' => null,
                'resource_update_token' => null,
                'failure_reason' => null,
            ]);
        } catch (Throwable $exception) {
            Log::error('Blog resource update failed', ['blog_id' => $this->blogId, 'exception_type' => $exception::class]);
            $this->failed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        CustomerBlog::whereKey($this->blogId)->where('resource_update_token', $this->updateToken)
            ->whereIn('status', ['resource_update_pending', 'resource_updating'])->update([
                'status' => 'resource_update_failed',
                // Keep last confirmed values, but mark the result as uncertain in the UI.
                'resource_update_token' => null,
                'failure_reason' => 'The resource update could not be confirmed. Retry to apply the requested values.',
            ]);
    }
}
