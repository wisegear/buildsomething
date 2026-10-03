<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## BlogShed provisioning

The public site and account area run in Laravel. Each registered user can request one WordPress blog at a `blogshed.uk` subdomain. A database queue job uses the restricted `blogshed-deploy` SSH account to run the selected server’s `/usr/local/bin/create-wordpress` script. The script must return a final JSON object with `success`, `domain`, and WordPress admin username/password fields. The account page displays the result only to its owner; login details are encrypted in the application database.

Before enabling blog requests on the Laravel/Ploi server:

Install `nginx-blog-fallback.conf` as `/etc/nginx/conf.d/blogshed-fallback.conf`
on each WordPress hosting server, then run `nginx -t` and reload Nginx. It uses
the same origin certificate as customer blogs. Unknown subdomains return
`404 Blog not found` instead of falling through to the first customer blog.
Only one HTTPS default server can be configured for each listening address;
check existing defaults before installing this configuration.

1. Run `php artisan migrate --force` and ensure `APP_KEY` is stable and backed up. Changing it makes stored WordPress passwords unreadable.
2. Add each server in Admin → Servers with its IP address, location, and Active set to Yes. Set `BLOGSHED_SSH_KEY` to the private key path readable by the queue worker. The key should already allow `blogshed-deploy` to run the provisioning script through `sudo` without a password. Keep strict SSH host key checking enabled and add each server's verified host key to the queue worker account's `known_hosts`.
3. Keep `QUEUE_CONNECTION=database`, `DB_QUEUE_RETRY_AFTER=720` and `BLOGSHED_LOCK_STORE=database`. Run a supervised worker such as `php artisan queue:work database --queue=default --timeout=330 --tries=1`. Restart it after deployments.
4. Verify the script's final JSON includes `success: true`, the exact requested domain, and `admin_user`/`admin_password` (or `admin_username`/`username` and `password`). If a job fails, inspect Laravel logs and reconcile the site on the assigned server before considering a retry; provisioning jobs deliberately do not retry automatically.

Both remote scripts now require systemd with `systemd-run --wait --pipe --collect` and GNU tooling. Install both updated scripts as root-owned, non-writable by the SSH deployment account. Each operation runs in a transient service with a 240-second runtime limit, a 10-second startup limit and a 30-second shutdown limit. The service’s control group contains child commands, including commands that create their own process groups. See the [systemd service settings](https://github.com/systemd/systemd/blob/main/man/systemd.service.xml) and [systemd-run interface](https://github.com/systemd/systemd/blob/main/man/systemd-run.xml). Laravel allows 300 seconds for SSH and 330 seconds for the job; queue retry intervals are at least 720 seconds. Set an equivalent visibility timeout if using SQS. Configure the worker supervisor's shutdown grace to exceed the job timeout (for example, `stopwaitsecs=720`). Drain existing jobs before deploying these timeout changes: previously serialized jobs retain their old timeout values. Rebuild Laravel's configuration cache and restart workers when deploying.

Create, delete and password reset jobs share a cache lock keyed by the canonical server IP, so duplicate server records with the same address serialize together. All application workers must use the same persistent `BLOGSHED_LOCK_STORE` (database by default); do not use an array or per-machine file cache in production. Use the same IP for records describing the same physical host; different IP aliases cannot be automatically identified as one machine. Requests waiting for the lock are deferred for 15 seconds before any SSH operation starts. Actual remote operations still get only one attempt. Preflight failures release the lock immediately. Normal SSH command exits (including script failures or invalid responses) also release it; these still fail the request unless its success response is valid. The server scripts now check their exact systemd unit after an interrupted/failed operation: confirmed stopped units return ordinary failure so the lock is released immediately. Deploy all three updated scripts to enable this. Transport exceptions, SSH exit 255 and unconfirmed signal exits remain uncertain. An uncertain SSH result keeps the server lock for up to 660 seconds, allowing the remote deadline to expire before another queued request runs. The scripts' own shared file lock also protects against direct invocations outside Laravel.

Creation writes root-only recovery metadata before creating resources and removes it only after successful rollback. Interrupted/partial cleanup retains it so an operator can inspect the host and use the deletion script to finish cleanup. Never reset a failed request to pending just because a timeout or lock expires: inspect the assigned server first. A disconnect can leave the service running until its deadline; missing success output is an uncertain result, even if the site appears online.

The local PHP tests use fake queues/processes; `python3 -m unittest discover -s tests/scripts -v` tests the scripts with simulated services, databases and Linux users in temporary directories. Neither suite contacts hosting servers or proves systemd/Nginx behavior. Before launch, complete the disposable-site acceptance checks in [the audit](docs/PRE_LAUNCH_AUDIT.md), including real timeout/cancellation, two-site isolation, overlapping requests and partial deletion recovery.

## WordPress passwords and mail

WordPress administrator passwords can be reset from the account panel without email. Deploy the reset script, migration and sudo rule using [the setup instructions](docs/WORDPRESS_PASSWORD_RESET.md). BlogShed does not configure outgoing mail on the WordPress servers. Customers may connect their own external email service; WordPress mail is not blocked.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

Blog requests store their assigned server. Multiple available locations require a selection; a single active server is selected automatically. Servers in the same location share one location option (the oldest active record is selected). No active servers means signup for a blog is paused. All servers use the configured SSH user, key and port and must provide `/usr/local/bin/create-wordpress`. The legacy `BLOGSHED_SSH_HOST` is no longer used. Existing blogs retain a nullable assignment and must be assigned explicitly before any pending provisioning can run; there is no fallback to another host. Assigned servers cannot be deleted.

### Deleting customer blogs

Admin → Blogs lists customer sites and their assigned servers. Type the full domain to queue a deletion. The queue worker uses the assigned server IP and existing SSH settings to run `sudo -n /usr/local/bin/delete-wordpress SUBDOMAIN`. Install the supplied V2 script on each server and allow the deployment account to run it without an interactive sudo password. Inactive servers can still be used for deletion.

A record is removed only after exit code zero and JSON containing `success: true`, `deleted: true`, and the exact domain. Failure, timeout or an unexpected response preserves the record as `deletion_failed`; inspect the server before retrying because deletion may have partially completed. The user account remains. Pending/provisioning blogs and blogs without a server assignment cannot be deleted from this page. The supplied script accepts 3–28 character subdomains; longer existing names require a compatible server script before deletion. Tests simulate SSH and do not delete real sites.

### Signup / Security

New registrations create a separate `signup_assessments` record, visible in Admin → Users → Signup / Security. Run the migrations before deploying this code. Existing users have no assessment; historical IPs cannot be reconstructed. IPs use an indexed, canonical IPv4/IPv6 string (45 characters) for PostgreSQL and SQLite compatibility. Timestamps use timezone-aware columns. User, IP and email domain have indexes. Only local signup details are stored; enrichment fields, risk flags and risk status have been removed.

The prior-IP count excludes the new account and includes only retained assessments. Deleting a user cascades to their assessment. PostgreSQL transaction-scoped advisory locks serialize same-IP capture to avoid concurrent signups missing each other under the default READ COMMITTED isolation level. Other database drivers capture a best-effort count. Repeated IPs never reject signup or provisioning.

Set `TRUSTED_PROXIES` to a comma-separated list of actual proxy IPs/CIDRs, including the maintained Cloudflare ranges if Cloudflare is in the forwarding chain. Empty trusts none. Do not use wildcards or trust all networks. Configure each proxy to sanitize/append forwarding headers correctly and restrict origin ingress to your intended proxies. Laravel resolves `Request::ip()` using `X-Forwarded-For` only across trusted hops; the application does not directly trust `CF-Connecting-IP` or arbitrary client headers. If the web server restores the client address itself, configure its trusted sources equally carefully. Rebuild the configuration cache after changes. Validate the resulting signup IP against a known client during deployment.

Email verification review: verification routes exist, but `User` does not implement `MustVerifyEmail`. Email verification is not enforced. The account blog-request route and provisioning job now require manual admin activation, but an activated user can still provision without verifying their email. Manual activation does not mark an email address as verified. Requiring verification before allocating Saturn resources needs a separate change to both the request boundary and queued provisioning safeguards.

### Manual account activation

New accounts and existing users without blogs must be activated in Admin → Users using **Activate User** before creating a blog. They see a signup-check message and the account page refreshes every ten seconds until activated. Activation is stored as `users.activated_at`, cannot be set during registration, and repeated activation does not change the original timestamp. The blog request endpoint and provisioning worker both enforce activation. The migration preserves existing blog owners and previously accepted blog requests by activating those accounts. Run migrations and restart queue workers on deployment.

### Registration CAPTCHA

Registration uses hCaptcha, matching Prop's widget, with server-side token verification before creating any account or signup assessment. Configure `NOCAPTCHA_SITEKEY` and `NOCAPTCHA_SECRET` in the local environment and allow the BlogShed hostname in the hCaptcha dashboard. `HCAPTCHA_ENABLED` defaults to true. Missing keys, failed challenges and verification outages stop signup with a retry message; existing logins are unaffected. Tests disable CAPTCHA for unrelated flows and fake verification responses in dedicated CAPTCHA tests. Never use test credentials in production. Refresh cached configuration after changing credentials.

### Admin account setup

Seeding does not create users, set passwords, or grant admin access. Register an account with a strong password, then run `php artisan blogshed:make-admin your-email@example.com` on the application host to grant access explicitly. This preserves the account's password and activation status. Existing accounts are not changed by this update: if the old seeder was previously used, reset that account's password before enabling public access.

### Registration and provisioning safeguards

Registration accepts at most five attempts per minute per client IP, including unsuccessful CAPTCHA attempts. CAPTCHA verification precedes email uniqueness validation. Configure trusted proxies correctly so unrelated clients do not share a proxy's rate limit.

New blog names must contain 3–28 lowercase letters or digits with optional single internal hyphens. The worker validates names again before SSH. Deletion retains support for internal consecutive hyphens on legacy records. Verify both installed WordPress scripts accept the documented format before deployment; longer existing names require reconciliation and a compatible script, not truncation or renaming in the database.

A provisioning worker atomically claims a pending request before contacting the server. Duplicate jobs cannot claim a request already in progress or completed. Automatic retries remain disabled: after failure or timeout, check the assigned server and logs before any manual recovery.

Staff replies to another user's support ticket set Awaiting Reply, activating the customer's badge. Replies from the ticket owner reopen the ticket.

## Repository and local deployment scripts

The Laravel application is versioned at https://github.com/wisegear/buildsomething.
The root files `create-wordpress.sh`, `delete-wordpress.sh` and
`reset-wordpress-password.sh` are intentionally ignored by Git. Keep these local
copies separately and install them on each WordPress server using the documented
extensionless command names. A fresh clone does not contain them; Laravel uses
the installed remote commands, not local shell files. The Python script tests
require all three local files and are skipped when they are absent.

Environment files, dependencies, generated frontend assets, uploaded files and
runtime storage are excluded. Copy `.env.example` to `.env` and configure the
new environment separately. Install dependencies with `composer install` and
`npm ci`, and build frontend assets with `npm run build` during deployment.

## Blog post tags

Administrators can enter up to ten comma-separated tags in the post editor.
Tags are normalised to lowercase and shared between posts. Clear the field to
remove assignments. Public cards and articles link to a topic-filtered Field
notes listing; only published posts dated today or earlier contribute topics
or results. Filtering is preserved across pagination.

For this release, run `php artisan migrate --force` before serving the updated
application, then rebuild assets with `npm ci` and `npm run build` and refresh
any deployment caches. The new `2026_09_24_200000_create_post_tags_tables`
migration adds tags and their post associations; it does not alter existing
migrations or post content. Existing posts start without tags.
