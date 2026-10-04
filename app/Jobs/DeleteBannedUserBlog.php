<?php

namespace App\Jobs;

use App\Jobs\Middleware\SerializeWordPressOperations;
use App\Models\CustomerBlog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DeleteBannedUserBlog implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 330;

    public bool $remoteOperationUncertain = false;

    public function __construct(public int $blogId) {}

    public function middleware(): array
    {
        return [new SerializeWordPressOperations];
    }

    public function handle(): void
    {
        // The existing server lease lets an in-flight operation finish first.
        DB::transaction(function (): void {
            $blog = CustomerBlog::lockForUpdate()->find($this->blogId);
            if (! $blog || $blog->user?->banned_at === null || $blog->status === 'deleting') {
                return;
            }
            $blog->update([
                'status' => 'deleting',
                'failure_reason' => null,
                'pending_wp_admin_password' => null,
                'password_reset_token' => null,
            ]);
            DeleteCustomerBlog::dispatch($blog->id)->afterCommit();
        });
    }
}
