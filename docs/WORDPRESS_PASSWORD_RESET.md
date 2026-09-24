# WordPress password resets

Blog owners reset their original WordPress administrator password from **Account → Reset Admin Password**. The form requires matching passwords of 12–128 characters. Laravel queues a single attempt and shows a waiting/updating state until the assigned server confirms success. Existing WordPress sessions are invalidated. This does not change the Laravel account password.

Only the authenticated, activated blog owner can submit a reset. Laravel selects the blog, server and original administrator itself. Reset requests are rate-limited, protected by CSRF, and serialized with creation/deletion on that physical server. A request token prevents an old queued job from acting on a later reset. The pending password is encrypted in the database, is absent from queue payloads, and is cleared on completion or failure. Password fields are not flashed back into the session.

## Deploy Laravel

Apply the new migration and restart queue workers after installing the remote script:

```bash
php artisan migrate --force
php artisan config:cache
php artisan queue:restart
```

Use the persistent queue/cache and timeouts documented in the README. New reset requests use the same per-host lock, including the protective wait after a transport exception, SSH disconnect or signal exit. Preflight failures and normal remote command exits release the lock immediately, even when the password change could not be confirmed. Do not clear a host lock to bypass an operation that may still be running.

## Install on each WordPress server

From a directory containing the reviewed release files, run as root:

```bash
install -o root -g root -m 0755 reset-wordpress-password.sh /usr/local/bin/reset-wordpress-password
install -o root -g root -m 0755 create-wordpress.sh /usr/local/bin/create-wordpress
install -o root -g root -m 0755 delete-wordpress.sh /usr/local/bin/delete-wordpress
```

Extend the existing restricted deployment account's sudo rules using `visudo`, preserving its create/delete entries:

```sudoers
blogshed-deploy ALL=(root) NOPASSWD: /usr/local/bin/reset-wordpress-password
```

The script validates both arguments internally; no shell, generic WP-CLI or general systemctl access should be added to the deployment account. Use `visudo -c` to validate the resulting policy. Do not enable sudo input logging (`log_input`) for this command: stdin carries the password. Do not enable shell tracing around it.

The interface is `reset-wordpress-password SUBDOMAIN ADMIN_USERNAME`, with the new password followed by a newline on stdin. Passwords must never be supplied as command-line arguments. The script validates root-owned site metadata, the original generated admin username and its current administrator role, then runs WP-CLI as the dedicated site user. It uses `wp user update --prompt=user_pass --skip-email`, followed by destruction of that user's sessions. It returns only confirmation and identifiers, never the password. Existing metadata without `WP_ADMIN_USER` is supported using the administrator recorded by Laravel and the site's current administrator list.

## Email policy

Panel resets work without an email service and do not send notification emails. BlogShed does not configure an outgoing mail service on deployment servers. Customers may configure WordPress plugins to use their own external SMTP or email API service; this feature does not block WordPress mail or outbound email connections. Laravel's own mail configuration remains separate.

If you already installed the previously proposed no-mail configuration, remove only the BlogShed `99-blogshed-no-mail.ini` files from PHP's FPM and CLI `conf.d` directories. Remove the corresponding `php_admin_value[sendmail_path] = /bin/false` and `php_admin_value[auto_prepend_file] = /usr/local/lib/blogshed/no-mail.php` entries from any pools created with that version. Test the PHP-FPM configuration and reload it. Remove `/usr/local/lib/blogshed/no-mail.php` only after all references to it are gone. No server cleanup is needed if that configuration was never installed.

## Verify on a disposable site

1. Apply the migration, scripts and sudo rule. Restart workers.
2. Open the account page and choose **Reset Admin Password**. Submit matching new passwords. Wait for the queued reset to complete.
3. Confirm the old password no longer logs in, the new one does, and a previously open WordPress session has been signed out.
4. Confirm the saved account-page password updates only on success. Passwords are not present in command arguments, job payloads or application logs.
5. Confirm mismatched/short passwords are rejected and another account cannot reset the site.
6. Confirm the panel reset works without a configured email provider. If a customer configures an external provider, its email features remain available.
7. Test a reset while another same-server operation is running: it must wait rather than overlap. Test an unconfirmed remote result: the account must show an error and never claim success. An explicit new reset may be submitted after checking server state; it waits for the protective lease only if remote termination is uncertain.

If a user has renamed or removed the provisioned WordPress administrator, the script refuses to reset an unrelated user. Operator reconciliation is required.

### Confirmed interruption handling

Deploy the updated create, delete and reset scripts together. After a failed supervised operation, each script checks its exact systemd unit with a bounded status query. An inactive or failed unit returns ordinary failure (exit 1), allowing the updated Laravel worker to release the host lock immediately. Active or unreadable unit state returns an uncertain failure (143). An SSH disconnect can still prevent delivery of this confirmation, so the protective lease remains necessary when the server cannot confirm termination. Restart Laravel workers after updating application code; an existing lease is not retroactively cleared.
