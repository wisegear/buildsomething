<?php

namespace App\Jobs;

use App\Jobs\Middleware\SerializeWordPressOperations;
use App\Models\CustomerBlog;
use App\Services\WordPressProvisioner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteCustomerBlog implements ShouldQueue
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
        $lock = Cache::store(config('blogshed.lock_store'))->lock('delete-customer-blog:'.$this->blogId, SerializeWordPressOperations::LOCK_SECONDS);
        if (! $lock->get()) {
            return;
        }
        try {
            $blog = CustomerBlog::find($this->blogId);
            if (! $blog || $blog->status !== 'deleting') {
                return;
            }
            $provisioner->delete($blog, function (bool $uncertain): void {
                $this->remoteOperationUncertain = $uncertain;
            });
            $this->remoteOperationUncertain = false;
            $blog->delete();
        } catch (Throwable $exception) {
            Log::error('Blog deletion failed', ['blog_id' => $this->blogId]);
            $this->failed($exception);
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        CustomerBlog::whereKey($this->blogId)->where('status', 'deleting')->update([
            'status' => 'deletion_failed',
            'failure_reason' => 'Deletion could not be confirmed. Check the assigned server before retrying.',
        ]);
    }
}
