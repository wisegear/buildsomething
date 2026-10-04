#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
trap '' PIPE
readonly PHP_VERSION="8.5"
readonly OPERATION_SECONDS=240
readonly CLEANUP_SECONDS=30
fail() { printf '{"success":false,"error":"Resource update failed"}\n'; exit 1; }
SUBDOMAIN="${1:-}"
WORKERS="${2:-}"
MEMORY="${3:-}"
[[ $EUID -eq 0 && $# -eq 3 && "$SUBDOMAIN" =~ ^[a-z0-9][a-z0-9-]{1,26}[a-z0-9]$ ]] || fail
[[ "$WORKERS" =~ ^[1-9][0-9]?$ && "$MEMORY" =~ ^[1-9][0-9]{1,3}$ ]] || fail
(( WORKERS <= 50 && MEMORY >= 32 && MEMORY <= 2048 )) || fail
# Run in a transient service so disconnects cannot remove the deadline.
# --pipe keeps credentials on the SSH stream rather than in the journal.
# Install this script as a root-owned file, not writable by the deploy user.
if [[ "${BLOGSHED_SUPERVISED:-}" != "resources" ]]; then
    for bin in systemd-run systemctl readlink timeout; do
        command -v "$bin" >/dev/null 2>&1 || fail "Required tool not found: ${bin}."
    done
    UNIT="blogshed-resources-${SUBDOMAIN}-$$"
    # A signal exit alone does not tell Laravel whether remote work has ended.
    # Confirm the exact transient unit is stopped before returning an ordinary
    # failure (1), which releases the Laravel host lock without claiming success.
    completed_failure() {
        local state
        if state=$(timeout --kill-after=1 5s systemctl show "$UNIT" --property=ActiveState --value 2>/dev/null); then
            case "$state" in
                inactive|failed) exit 1 ;;
            esac
        fi
        # Keep the uncertain-operation protection if the status check fails.
        exit 143
    }
    cancel_operation() {
        trap '' HUP INT TERM
        systemctl stop "$UNIT" >/dev/null 2>&1 || true
        wait "$RUNNER_PID" 2>/dev/null || true
        completed_failure
    }
    exec 3<&0
    systemd-run --quiet --wait --pipe --collect --service-type=exec \
        --unit="$UNIT" \
        --property="RuntimeMaxSec=${OPERATION_SECONDS}s" \
        --property="TimeoutStartSec=10s" \
        --property="TimeoutStopSec=${CLEANUP_SECONDS}s" \
        --property=KillMode=control-group \
        --property=SendSIGKILL=yes \
        --setenv=BLOGSHED_SUPERVISED=resources \
        "$BASH" "$(readlink -f "$0")" "$@" <&3 3<&- &
    RUNNER_PID=$!
    exec 3<&-
    trap cancel_operation HUP INT TERM
    if wait "$RUNNER_PID"; then
        exit 0
    else
        completed_failure
    fi
fi
unset BLOGSHED_SUPERVISED


exec 200>/var/lock/blogshed-provision.lock
flock -n 200 || fail
SITE_USER="bs_${SUBDOMAIN//-/_}"
POOL="/etc/php/${PHP_VERSION}/fpm/pool.d/${SITE_USER}.conf"
# Only modify a root-owned pool at the expected path, never follow a symlink.
[[ -f "$POOL" && ! -L "$POOL" && "$(stat -c %u "$POOL")" == 0 ]] || fail
[[ "$(stat -c %a "$POOL")" =~ ^[0-7][0145][0145]$ ]] || fail
[[ $(grep -c '^pm.max_children[[:space:]]*=' "$POOL") -eq 1 ]] || fail
[[ $(grep -c '^php_admin_value\[memory_limit\][[:space:]]*=' "$POOL") -eq 1 ]] || fail
BACKUP=$(mktemp "${POOL}.backup.XXXXXX")
TEMP=$(mktemp "${POOL}.new.XXXXXX")
cp -p "$POOL" "$BACKUP"
rollback() {
    trap '' HUP INT TERM
    trap - ERR
    cp -p "$BACKUP" "$POOL"
    timeout --kill-after=2 15s "php-fpm${PHP_VERSION}" -t >/dev/null 2>&1 && timeout --kill-after=2 15s systemctl reload "php${PHP_VERSION}-fpm" >/dev/null 2>&1 || true
    rm -f "$BACKUP" "$TEMP"
    fail
}
trap rollback ERR HUP INT TERM
sed -E "s/^pm.max_children[[:space:]]*=.*/pm.max_children = ${WORKERS}/; s/^php_admin_value\[memory_limit\][[:space:]]*=.*/php_admin_value[memory_limit] = ${MEMORY}M/" "$POOL" > "$TEMP"
chmod --reference="$POOL" "$TEMP"
chown --reference="$POOL" "$TEMP"
mv "$TEMP" "$POOL"
"php-fpm${PHP_VERSION}" -t >&2
systemctl reload "php${PHP_VERSION}-fpm"
trap - ERR HUP INT TERM
rm -f "$BACKUP"
printf '{"success":true,"domain":"%s.blogshed.uk","workers":%s,"memory_mb":%s}\n' "$SUBDOMAIN" "$WORKERS" "$MEMORY"
