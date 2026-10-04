<?php

namespace App\Jobs;

use App\Jobs\Middleware\SerializeWordPressOperations;
use App\Models\CustomerBlog;
use App\Services\WordPressProvisioner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResetWordPressPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 330;

    public bool $failOnTimeout = true;

    public bool $remoteOperationUncertain = false;

    // The password is stored encrypted on the blog, never in a queue payload.
    public function __construct(public int $blogId, public string $resetToken) {}

    public function middleware(): array
    {
        return [new SerializeWordPressOperations];
    }

    public function freshForDeferral(): self
    {
        return new self($this->blogId, $this->resetToken);
    }

    public function handle(WordPressProvisioner $provisioner): void
    {
        $claimed = CustomerBlog::whereKey($this->blogId)
            ->where('password_reset_token', $this->resetToken)->where('status', 'password_reset_pending')
            ->update(['status' => 'password_resetting']);
        if ($claimed !== 1) {
            return;
        }

        try {
            $blog = CustomerBlog::findOrFail($this->blogId);
            if ($blog->user?->activated_at === null || $blog->user?->banned_at !== null || ! is_string($blog->pending_wp_admin_password)) {
                $this->failed(null);

                return;
            }
            $password = $blog->pending_wp_admin_password;
            $provisioner->resetPassword($blog, $password, function (bool $uncertain): void {
                $this->remoteOperationUncertain = $uncertain;
            });
            $this->remoteOperationUncertain = false;
            $blog->update([
                'status' => 'active',
                'wp_admin_password' => $password,
                'pending_wp_admin_password' => null,
                'password_reset_token' => null,
                'failure_reason' => null,
            ]);
        } catch (Throwable $exception) {
            Log::error('WordPress password reset failed', ['blog_id' => $this->blogId, 'exception_type' => $exception::class]);
            $this->failed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        CustomerBlog::whereKey($this->blogId)->where('password_reset_token', $this->resetToken)
            ->whereIn('status', ['password_reset_pending', 'password_resetting'])->update([
                'status' => 'password_reset_failed',
                // The server may have changed it despite a lost SSH response.
                'wp_admin_password' => null,
                'pending_wp_admin_password' => null,
                'password_reset_token' => null,
                'failure_reason' => 'The password change could not be confirmed. Submit a new reset or contact support.',
            ]);
    }
}
