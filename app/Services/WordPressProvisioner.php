<?php

namespace App\Services;

use App\Models\CustomerBlog;
use Closure;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class WordPressProvisioner
{
    public function updateResources(CustomerBlog $blog, ?Closure $operationState = null): void
    {
        $server = $blog->server;
        if (! $server || ! filter_var($server->ip_address, FILTER_VALIDATE_IP)
            || ! preg_match('/\\A[a-z0-9][a-z0-9-]{1,26}[a-z0-9]\\z/', $blog->subdomain)
            || $blog->pending_workers < 1 || $blog->pending_workers > 50
            || $blog->pending_memory_mb < 32 || $blog->pending_memory_mb > 2048) {
            throw new RuntimeException('Invalid resource update target or limits.');
        }
        $user = config('blogshed.ssh_user');
        $key = config('blogshed.ssh_key');
        if (! $user || ! $key) {
            throw new RuntimeException('Server SSH access is not configured.');
        }
        $command = ['ssh', '-i', $key, '-p', (string) config('blogshed.ssh_port'),
            '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes', '-o', 'ConnectTimeout=10',
            $user.'@'.$server->ip_address, 'sudo', '-n', '/usr/local/bin/update-wordpress-resources',
            $blog->subdomain, (string) $blog->pending_workers, (string) $blog->pending_memory_mb];
        $operationState?->__invoke(true);
        $result = Process::timeout(300)->run($command);
        if ($result->exitCode() !== null && $result->exitCode() >= 0 && $result->exitCode() < 128) {
            $operationState?->__invoke(false);
        }
        $data = json_decode(trim($result->output()), true);
        if ($result->failed() || ! is_array($data) || ($data['success'] ?? null) !== true
            || ($data['domain'] ?? null) !== $blog->domain
            || ($data['workers'] ?? null) !== $blog->pending_workers
            || ($data['memory_mb'] ?? null) !== $blog->pending_memory_mb) {
            throw new RuntimeException('The server did not confirm the resource update.');
        }
    }

    public function delete(CustomerBlog $blog, ?Closure $operationState = null): void
    {
        $server = $blog->server;
        if (! $server || ! filter_var($server->ip_address, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('The assigned server is missing or has an invalid IP address.');
        }
        // Match the deletion script and keep the remote shell argument strictly bounded.
        if (! preg_match('/\A[a-z0-9][a-z0-9-]{1,26}[a-z0-9]\z/', $blog->subdomain)) {
            throw new RuntimeException('The deletion script requires a subdomain of 3–28 characters.');
        }
        $user = config('blogshed.ssh_user');
        $key = config('blogshed.ssh_key');
        if (! $user || ! $key) {
            throw new RuntimeException('Server SSH access is not configured.');
        }

        // Inactive servers still host existing blogs and must support their removal.
        $process = Process::timeout(300);
        $command = [
            'ssh', '-i', $key,
            '-p', (string) config('blogshed.ssh_port'),
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=yes',
            '-o', 'ConnectTimeout=10',
            $user.'@'.$server->ip_address,
            'sudo', '-n', '/usr/local/bin/delete-wordpress', $blog->subdomain,
        ];
        // Preflight and command construction cannot have started a remote operation.
        $operationState?->__invoke(true);
        $result = $process->run($command);
        // SSH 255, signals and exceptions cannot confirm remote termination.
        // A normal command exit confirms completion, even if its result is a failure.
        if ($result->exitCode() !== null && $result->exitCode() >= 0 && $result->exitCode() < 128) {
            $operationState?->__invoke(false);
        }

        $data = json_decode(trim($result->output()), true);
        if ($result->failed() || ! is_array($data)
            || ($data['success'] ?? null) !== true
            || ($data['deleted'] ?? null) !== true
            || ($data['domain'] ?? null) !== $blog->domain) {
            throw new RuntimeException('The assigned server did not confirm deletion of this blog.');
        }
    }

    public function resetPassword(CustomerBlog $blog, #[\SensitiveParameter] string $password, ?Closure $operationState = null): void
    {
        $server = $blog->server;
        if (! $server || ! filter_var($server->ip_address, FILTER_VALIDATE_IP)
            || ! preg_match('/\A[a-z0-9][a-z0-9-]{1,26}[a-z0-9]\z/', $blog->subdomain)
            || ! preg_match('/\Aadmin_[a-f0-9]{8}\z/', $blog->wp_admin_username ?? '')) {
            throw new RuntimeException('The assigned WordPress password reset target is invalid.');
        }
        if (strlen($password) < 12 || strlen($password) > 512 || preg_match('/[\x00-\x1F\x7F]/', $password)) {
            throw new RuntimeException('Invalid WordPress password format.');
        }
        $user = config('blogshed.ssh_user');
        $key = config('blogshed.ssh_key');
        if (! $user || ! $key) {
            throw new RuntimeException('Server SSH access is not configured.');
        }

        // Inactive servers may still host a customer's active blog.
        $process = Process::timeout(300)->input($password."\n");
        $command = [
            'ssh', '-i', $key, '-p', (string) config('blogshed.ssh_port'),
            '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes', '-o', 'ConnectTimeout=10',
            $user.'@'.$server->ip_address,
            'sudo', '-n', '/usr/local/bin/reset-wordpress-password', $blog->subdomain, $blog->wp_admin_username,
        ];
        // Preflight and command construction cannot have started a remote operation.
        $operationState?->__invoke(true);
        $result = $process->run($command);
        // SSH 255, signals and exceptions cannot confirm remote termination.
        // A normal command exit confirms completion, even if its result is a failure.
        if ($result->exitCode() !== null && $result->exitCode() >= 0 && $result->exitCode() < 128) {
            $operationState?->__invoke(false);
        }
        $data = json_decode(trim($result->output()), true);
        if ($result->failed() || ! is_array($data) || ($data['success'] ?? null) !== true
            || ($data['password_reset'] ?? null) !== true || ($data['domain'] ?? null) !== $blog->domain
            || ($data['admin_username'] ?? null) !== $blog->wp_admin_username) {
            throw new RuntimeException('The assigned server did not confirm the password change.');
        }
    }

    public function create(CustomerBlog $blog, ?Closure $operationState = null): array
    {
        if ($blog->user?->banned_at !== null) {
            throw new RuntimeException('Banned accounts cannot create blogs.');
        }
        $email = $blog->user?->email;
        // Match the script's supported email format before contacting the server.
        if (! is_string($email) || strlen($email) > 254
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! preg_match('/\A[a-zA-Z0-9_+.-]+@[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)+\z/', $email)) {
            throw new RuntimeException('The blog owner must have an email address supported by the provisioning script.');
        }

        if (strlen($blog->subdomain) < 3 || strlen($blog->subdomain) > 28
            || ! preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $blog->subdomain)) {
            throw new RuntimeException('Provisioning requires a subdomain of 3–28 lowercase letters, digits, or single internal hyphens.');
        }

        $server = $blog->server;
        if (! $server || ! $server->active) {
            throw new RuntimeException('The assigned server is unavailable for provisioning.');
        }
        $host = $server->ip_address;
        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('The assigned server IP address is invalid.');
        }
        $user = config('blogshed.ssh_user');
        $key = config('blogshed.ssh_key');

        if (! $host || ! $user || ! $key) {
            throw new RuntimeException('The selected server provisioning is not configured.');
        }

        $process = Process::timeout(300);
        $command = [
            'ssh', '-i', $key,
            '-p', (string) config('blogshed.ssh_port'),
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=yes',
            '-o', 'ConnectTimeout=10',
            $user.'@'.$host,
            // SSH joins these arguments for a remote shell, so quote the email
            // for that shell as well as using an array for the local process.
            'sudo', '-n', '/usr/local/bin/create-wordpress', $blog->subdomain, escapeshellarg($email),
        ];
        // Preflight and command construction cannot have started a remote operation.
        $operationState?->__invoke(true);
        $result = $process->run($command);
        // SSH 255, signals and exceptions cannot confirm remote termination.
        // A normal command exit confirms completion, even if its result is a failure.
        if ($result->exitCode() !== null && $result->exitCode() >= 0 && $result->exitCode() < 128) {
            $operationState?->__invoke(false);
        }

        if ($result->failed()) {
            throw new RuntimeException('The selected server could not create the blog.');
        }

        // The script can print progress before its final JSON object.
        $output = trim($result->output());
        $start = strrpos($output, "\n{");
        $json = $start === false ? $output : substr($output, $start + 1);
        $data = json_decode($json, true);

        if (! is_array($data) || ($data['success'] ?? false) !== true || ($data['domain'] ?? null) !== $blog->domain) {
            throw new RuntimeException('The selected server returned an unexpected response.');
        }

        $username = $data['admin_user'] ?? $data['admin_username'] ?? $data['username'] ?? null;
        $password = $data['admin_password'] ?? $data['password'] ?? null;

        if (! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('The selected server did not return WordPress login details.');
        }

        return ['username' => $username, 'password' => $password];
    }
}
