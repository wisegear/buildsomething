# Blog resource settings

Admin → Blogs shows maximum PHP-FPM workers and the PHP memory limit per worker (MB). This is a limit, not reserved RAM or a total blog memory cap. The form accepts 1–50 workers and 32–2048 MB per worker; consider host capacity when increasing either value.

Newly provisioned blogs record the script defaults (5 workers, 512 MB). Existing records show those defaults as unconfirmed: existing manual server changes are not discovered automatically. Apply the desired values to confirm them. Updates run on the persistent queue using the shared host lock. An unconfirmed update preserves the last confirmed values and requested values for retry.

## Deployment

Run `php artisan migrate --force`, build frontend assets, and restart queue workers. On every hosting server install the supplied script:

```sh
sudo install -o root -g root -m 0755 update-wordpress-resources.sh /usr/local/bin/update-wordpress-resources
```

Extend the restricted deployment account's existing sudo policy with `visudo`:

```sudoers
blogshed-deploy ALL=(root) NOPASSWD: /usr/local/bin/update-wordpress-resources
```

Validate the policy with `visudo -c`. Do not grant generic shell or systemctl access. The script validates its arguments, edits only the expected root-owned PHP 8.5 pool, shares the provisioning file lock, tests PHP configuration, and reloads PHP-FPM. Failure attempts to restore the previous pool and reload it. Supervision and timeouts follow the existing WordPress scripts. Changes reload the hosting server's PHP-FPM service, so test on staging first. The application records values only after matching server confirmation. Tests mock remote operations; no live server configuration is changed by tests.
