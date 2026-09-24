# BlogShed pre-launch audit

Audit date: 23 September 2026

**Decision: hold launch until the server-side findings below are addressed and an end-to-end staging test passes.** The Laravel application passes the completed checks. The supplied scripts match its command and response contracts, but successful simulated SSH tests do not certify the hosting server.

## Password recovery policy update — 24 September 2026

WordPress administrator password recovery is available through the authenticated Laravel account panel and the `reset-wordpress-password` script, without requiring email. BlogShed does not configure outgoing mail on deployment servers; customers may connect their own external email service. No WordPress mail block or network restriction is installed by this project. The historical WordPress email-delivery requirement below is superseded by panel reset and session-invalidation checks in [the deployment and test guide](WORDPRESS_PASSWORD_RESET.md). Laravel account email remains separate.

## Follow-up implementation status

The historical findings below describe the audited versions. Subsequent local changes now:

- Delay site publication until installation, permissions and metadata are ready; deny Nginx symlink access, protect `wp-config.php`, and align upload limits.
- Pass the owner's validated, remotely quoted email from Laravel, and pass passwords to WP-CLI/MySQL through standard input.
- Run both scripts under a transient systemd service (240-second runtime, 10-second startup, 30-second shutdown), with SSH/job/retry limits of 300/330/720 seconds.
- Serialize create/delete jobs by canonical host IP using a shared persistent cache lock. Contention defers unstarted requests; remote failures are never automatically retried. Uncertain operations retain the host lease for 660 seconds.
- Write recovery metadata before creating resources, track creation intent before each mutation, bound rollback, and retain metadata after incomplete cleanup. Deletion retains metadata until all removal steps finish.

Follow-up validation: 92 PHP tests passed (625 assertions), plus seven script contract/failure tests with simulated services. Both scripts passed Bash syntax checks. The script tests cover partial resource creation, TERM/HUP interruption, bounded cleanup, retained metadata, resumed deletion and tampered metadata.

These are local implementation changes, not a production sign-off. Deploy both scripts and the Laravel configuration together, rebuild configuration caches, and restart workers as described in the README. Live staging acceptance below is still required.

## Completed checks

| Check | Result |
| --- | --- |
| Full automated suite, isolated SQLite database | 85 passed, 590 assertions |
| Full automated suite, newly created disposable PostgreSQL database | 85 passed, 590 assertions; test database removed afterward |
| Production frontend build | Passed; editor bundle warning noted below |
| PHP dependency vulnerability audit | No known advisories; no abandoned dependencies reported |
| JavaScript dependency vulnerability audit | Zero known vulnerabilities |
| Composer manifest validation | Passed |
| Blade template compilation | Passed; compiled views cleared afterward |
| Route cache compilation | Passed using a temporary cache path |
| Formatting of changed PHP files | Passed |
| Both supplied Bash scripts | Bash syntax checks passed; source review completed |
| Existing local database migration status | All 13 migrations applied |
| Browser smoke tests | Public pages, login, account, profile, admin pages, support editor and draft editor checked |
| Responsive checks | Desktop homepage at 1440px; public and authenticated pages at 375px, with no document-level horizontal overflow |

Automated coverage includes authentication, reset flows with simulated mail, CAPTCHA verification with simulated provider responses, manual activation, authorization, private support tickets, HTML sanitization, image processing, blog validation, encrypted credentials, duplicate provisioning jobs, server selection and confirmed/failed deletion responses.

Browser interactions successfully logged into a disposable test account, confirmed blog creation stays disabled without consent, created a local support ticket through TinyMCE, and saved and reopened a blog draft. Browser console checks returned no errors. Public checks included the homepage, About, Terms, blog listing, a published article, login, registration and password reset request page. Authenticated checks used a separate SQLite database and no running queue worker. No live WordPress site was created or deleted.

## Application fixes made

1. **Provisioning logs no longer retain raw exception messages.** Database and process exceptions can contain sensitive values. Logs now retain only the blog ID and exception class. A regression test checks the exact logged context and safe customer-facing failure message.
2. **Assigned server IP addresses cannot be changed through admin.** Previously an address edit could redirect future provisioning/deletion commands for existing records. Updates now lock the server row and reject a changed IP when blogs are assigned. Name, price, location and active status remain editable. A regression test verifies both rejection and permitted edits.
3. **PostgreSQL connections explicitly use UTC.** The actual local database session default was Europe/London, while Laravel uses UTC. The PostgreSQL run exposed an incorrect signup timestamp that SQLite did not catch. Explicit UTC fixes the existing timestamp test on both databases. Rebuild configuration caches and restart workers when deploying. This does not rewrite old timestamps; review historical signup timestamps if they were previously written through a non-UTC session.

## Script/application compatibility

The temporary reference files `create-wordpress.sh` and `delete-wordpress.sh` were left unchanged and are not required by the application's test suite. Remove them from the application release as intended; the installed executable copies must remain on every WordPress server.

| Contract | Review |
| --- | --- |
| Remote command paths | Laravel calls `/usr/local/bin/create-wordpress SUBDOMAIN` and `/usr/local/bin/delete-wordpress SUBDOMAIN` through `sudo -n` |
| Domain | Both sides use `SUBDOMAIN.blogshed.uk` |
| Names | Application creates 3–28 character names with single internal hyphens; scripts accept that subset. Deletion additionally accepts legacy consecutive internal hyphens |
| Creation response | Script emits `success`, `domain`, `admin_username`, `admin_password`; Laravel accepts these exact fields |
| Deletion response | Script emits `success: true`, `deleted: true`, exact `domain`; Laravel requires all three and exit status zero |
| Metadata | Creation writes the identifiers and paths required by deletion; both use PHP 8.5 paths and the same lock file |
| Assignment | Operations target the assigned server; inactive servers remain available for deletion |
| Failed/uncertain deletion | Application retains the record; script removes metadata last, supporting investigation of partial failures |
| Description and terms | Stored by Laravel only; not passed to WordPress. Script uses the subdomain as the WordPress title |
| Customer email | Not passed to the script; every WordPress administrator currently gets `admin@blogshed.uk` |

## Findings requiring attention before launch

### High: the site is publicly enabled before WordPress installation

`create-wordpress.sh:442–446` enables and reloads the Nginx site before `wp core install` at lines 456–467. With working DNS and readable files, requests can reach an uninstalled WordPress instance. A visitor could race installation.

Move site enabling and Nginx reload until after installation, configuration, final permissions and trusted metadata are complete. Keep rollback tracking in place. Verify that a request during provisioning cannot reach the installer, including any default Nginx virtual host behavior on the real server.

### High: tenant isolation is not established by these scripts alone

The script assigns all site files to group `www-data`, with directories 750 and files 640 (`create-wordpress.sh:498–501`), while its Nginx block has no explicit symlink protection. A tenant who can execute PHP through an installed plugin can create a symlink in their own site to another site's file. A shared Nginx worker able to read both sites may serve the linked file as static content, including another site's configuration.

This is a configuration-dependent risk, not a confirmed exploit on the target server: its global Nginx settings were not provided. Nginx's documented default permits symlinks: [official disable_symlinks documentation](https://nginx.org/en/docs/http/ngx_http_core_module.html#disable_symlinks). Explicitly prevent cross-owner symlink reads or use stronger tenant isolation, and verify with two disposable sites. Dedicated PHP users alone do not establish this boundary. Also review process visibility: database and WordPress secrets are currently passed in command arguments, which may be observable to other local users if the host does not restrict process visibility.

### High: application and remote operation lifetimes do not match

Laravel stops SSH after 120 seconds (`app/Services/WordPressProvisioner.php:69`); the creation script allows three separate 60-second operations and additional unbounded commands. A valid slow operation can outlive the client. The application then marks it failed even if the remote process continues. The script has an ERR rollback trap but no explicit disconnect/TERM/HUP recovery contract.

Establish one bounded remote operation lifetime, including cancellation and rollback, then place the SSH timeout, job timeout and queue retry interval above it in that order. Preserve single-attempt behavior until remote reconciliation is reliable. Test interruption after resource creation and confirm the final server state before allowing recovery. Merely increasing the SSH timeout does not solve uncertain remote state.

### Medium: simultaneous jobs fail rather than wait

Both scripts use the same nonblocking `flock -n` (`create-wordpress.sh:189`, `delete-wordpress.sh:110`). A second create/delete on the same server returns failure immediately. Laravel deliberately has no automatic retries, so concurrent worker activity can leave ordinary requests needing manual help.

Serialize create and delete operations per physical server in the application queue, including any duplicate server records pointing at that host. A single supervised worker is a temporary operational constraint only if no other path runs these scripts concurrently. Test two near-simultaneous requests and a create/delete overlap before enabling multiple workers.

### Medium: WordPress recovery email is not the customer's email

`create-wordpress.sh:209` sets the same WordPress admin email for every customer, and Laravel sends only the subdomain. Password recovery therefore does not go to the customer's registered address unless they change it in WordPress.

Choose the intended recovery policy. Either pass and validate the owner's email through a safely quoted interface, or clearly require changing the WordPress email on first login and provide an operator-assisted recovery process. Verify mail delivery from WordPress separately from Laravel mail.

### Local configuration blocker: hCaptcha secret missing

The local application has CAPTCHA enabled and a site key, but no configured secret. Registration correctly fails closed. Supply the secret in the production secret configuration and verify a real challenge for the live hostname. No keys were exposed or changed during this audit.

## Other deployment observations

- Nginx upload size is not set in the supplied WordPress vhost, while PHP advertises 32 MB. Without a larger inherited setting, Nginx's default is 1 MB, so larger uploads receive HTTP 413. Align Nginx and PHP limits, allowing for multipart overhead. See [official client_max_body_size documentation](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size).
- The editor JavaScript bundle is approximately 1.29 MB uncompressed / 430 KB gzip. It is loaded for editor pages; the public app bundle is approximately 98 KB / 35 KB gzip. This is a performance warning, not a failed build.
- Manual activation is enforced; email verification is intentionally not enforced by the current User model. Activation does not verify ownership of an email address.
- If provisioning rolls back completely before writing metadata, the deletion script will refuse deletion because trusted metadata is absent. Such failed requests require operator reconciliation; there is no automatic reset workflow.
- Both scripts assume a Linux host with PHP-FPM 8.5, WP-CLI, MySQL, Nginx, systemd, GNU tooling, the expected certificate files, and correct sudo permissions. Those prerequisites were not executed or inspected on a hosting server.

## Remaining live/staging acceptance test

After fixing the server findings, perform this on a designated disposable subdomain on each hosting server:

1. Install the reviewed scripts with root ownership and restricted write permissions. Confirm the deployment account's exact sudo permissions, key readability and verified SSH host keys.
2. Configure the production environment: production mode, debug disabled, HTTPS URL, stable backed-up application encryption key, secure host-only session cookies, real mail transport, real CAPTCHA keys, trusted proxies and persistent database queue. Serve Laravel from its `public` directory only.
3. Confirm wildcard DNS reaches the selected hosting location, and that proxy/origin TLS validates for each new subdomain. Multiple selectable servers require DNS routing that actually selects the assigned host; these scripts do not manage DNS.
4. Register, complete a real CAPTCHA, activate the account, request a blog, run the supervised worker and verify the returned credentials against WordPress. Confirm the site remains inaccessible to installation attempts until setup completes.
5. Publish a post, upload a file above 1 MB, change the WordPress recovery email according to policy and receive a password reset message. Separately receive a Laravel password reset email.
6. Verify two-site isolation, concurrent request handling, interrupted provisioning and failed deletion behavior. Check that another blog remains intact throughout.
7. Delete the disposable blog through Admin → Blogs. Confirm the site, database, database user, Linux user, FPM pool, Nginx configuration and metadata are removed, and that the Laravel account remains.
8. Verify backups and restoration, worker supervision and restart behavior, disk capacity controls, and log access. Rebuild application configuration and restart workers after deploying these fixes.

These checks remain unverified. No production deployment, real email delivery, live CAPTCHA completion, SSH provisioning, or destructive remote operation was performed during this audit.
