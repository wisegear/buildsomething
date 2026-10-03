"""Safe local contract/failure tests. Services, users and MySQL are simulated.

Run: python3 -m unittest discover -s tests/scripts -v
No root access, hosting services or network required. These do NOT certify
systemd cgroup deadlines or Nginx/PHP behavior on a Linux hosting server.
"""
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

PROJECT = Path(__file__).resolve().parents[2]
SHIM = r'''import json, os, pathlib, signal, subprocess, sys, time
name = pathlib.Path(sys.argv[0]).name
args = sys.argv[1:]
root = pathlib.Path(os.environ['TEST_ROOT'])
scenario = os.environ.get('SCENARIO', '')
state = root / 'state'
def marker(kind):
    return state / kind
def record(event):
    with (root / 'events').open('a') as f:
        f.write(event + '\n')
def finish(code=0):
    sys.exit(code)
def exists(kind):
    return marker(kind).exists()
def remove(kind):
    marker(kind).unlink(missing_ok=True)
record(name)
if name == 'systemd-run':
    if scenario.startswith('supervisor_'):
        print(json.dumps({'success': False, 'error': 'Interrupted test operation'}))
        finish(143)
    for opt in ('--wait', '--pipe', '--collect', '--service-type=exec',
                '--property=RuntimeMaxSec=240s', '--property=TimeoutStopSec=30s',
                '--property=TimeoutStartSec=10s', '--property=KillMode=control-group',
                '--property=SendSIGKILL=yes'):
        assert opt in args, opt
    env = os.environ.copy()
    for arg in args:
        if arg.startswith('--setenv='):
            k, v = arg[len('--setenv='):].split('=', 1)
            env[k] = v
    index = next(i for i, arg in enumerate(args) if not arg.startswith('--'))
    os.execvpe(args[index], args[index:], env)
elif name == 'readlink':
    print(str(pathlib.Path(args[-1]).resolve()))
elif name == 'timeout':
    seconds = float(args[1].rstrip('s'))
    try:
        finish(subprocess.run(args[2:], timeout=seconds).returncode)
    except subprocess.TimeoutExpired:
        finish(124)
elif name == 'id':
    finish(0 if exists('user') else 1)
elif name == 'useradd':
    assert (root / 'etc/blogshed/sites/test-blog.conf').exists(), 'Missing early recovery metadata'
    marker('user').touch()
    if scenario == 'partial_user': finish(1)
elif name == 'userdel':
    if scenario == 'cleanup_user_fail': finish(1)
    remove('user')
elif name == 'pkill':
    finish(1)  # No real tenant processes exist in the simulation.
elif name in ('chown', 'flock'):
    pass
elif name == 'stat':
    path = pathlib.Path(args[-1])
    print('root' if args[1] == '%U' else oct(path.stat().st_mode & 0o777)[2:])
elif name == 'cat':
    if args:
        os.execv('/bin/cat', ['/bin/cat'] + args)
    data = sys.stdin.read()
    if scenario == 'partial_pool' and data.startswith('[bs_'):
        print('[incomplete]')
        finish(1)
    print(data, end='')
elif name == 'mysql':
    sql = args[-1] if args else sys.stdin.read()
    if 'SELECT COUNT(*)' in sql:
        print(1 if scenario == 'collision' else 0)
    elif 'CREATE DATABASE' in sql:
        marker('db').touch()
        if scenario == 'partial_db': finish(1)
    elif 'CREATE USER' in sql:
        assert not args, 'Password must not appear in command arguments'
        marker('db_user').touch()
    elif 'DROP DATABASE' in sql:
        if scenario in ('cleanup_db_fail', 'delete_db_fail'): finish(1)
        if scenario == 'cleanup_hang': time.sleep(60)
        remove('db')
    elif 'DROP USER' in sql:
        remove('db_user')
elif name == 'php-fpm8.5':
    pass
elif name == 'nginx':
    enabled = root / 'etc/nginx/sites-enabled/test-blog.blogshed.uk'
    if enabled.is_symlink():
        site = root / 'var/www/test-blog.blogshed.uk'
        assert (site / 'installed').exists(), 'Site enabled before installation'
        assert 'PROVISIONING_STATE=ready' in (root / 'etc/blogshed/sites/test-blog.conf').read_text()
        if scenario == 'nginx_fail': finish(1)
elif name == 'systemctl':
    if args[0] == 'show':
        assert args[-2:] == ['--property=ActiveState', '--value']
        if scenario == 'supervisor_unknown': finish(1)
        print('active' if scenario == 'supervisor_active' else 'inactive')
    if scenario == 'delete_term' and args == ['reload', 'nginx']:
        os.kill(os.getppid(), signal.SIGTERM)
        finish(1)
    if scenario == 'delete_reload_fail' and args == ['reload', 'nginx']: finish(1)
elif name == 'sudo':
    assert args[:2] == ['-n', '-u']
    os.execvp(args[3], args[3:])
elif name == 'wp':
    if args[:2] == ['user', 'list']:
        print('admin_01234567' if scenario != 'wrong_admin' else 'another_admin')
        finish()
    if args[:2] == ['user', 'update']:
        assert args[2] == 'admin_01234567'
        assert '--prompt=user_pass' in args and '--skip-email' in args
        assert '--skip-plugins' in args and '--skip-themes' in args
        assert not any(a.startswith('--user_pass=') for a in args)
        password = sys.stdin.readline().rstrip('\n')
        assert password == 'New password!$ 123'
        if scenario == 'reset_fail': finish(1)
        marker('password_changed').touch()
        finish()
    if args[:3] == ['user', 'session', 'destroy']:
        assert '--all' in args
        finish()
    site = root / 'var/www/test-blog.blogshed.uk'
    assert site.stat().st_mode & 0o777 == 0o700, 'Site readable while provisioning'
    assert not (root / 'etc/nginx/sites-enabled/test-blog.blogshed.uk').is_symlink()
    assert not any(a.startswith(('--dbpass=', '--admin_password=')) for a in args)
    if args[:2] == ['config', 'create']:
        assert '--prompt=dbpass' in args and len(sys.stdin.readline().strip()) >= 24
        (site / 'wp-config.php').write_text('private config')
    elif args[:2] == ['core', 'install']:
        assert '--prompt=admin_password' in args and len(sys.stdin.readline().strip()) >= 24
        assert '--admin_email=owner+blog@example.com' in args
        if scenario in ('wp_fail', 'cleanup_db_fail', 'cleanup_user_fail', 'cleanup_hang'): finish(1)
        if scenario in ('term', 'hup'):
            os.kill(os.getppid(), signal.SIGTERM if scenario == 'term' else signal.SIGHUP)
            finish(1)
        (site / 'installed').touch()
else:
    raise AssertionError('Unexpected shim ' + name)
'''


class WordPressScriptsTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        scripts = ('create-wordpress.sh', 'delete-wordpress.sh', 'reset-wordpress-password.sh')
        if any(not (PROJECT / name).is_file() for name in scripts):
            raise unittest.SkipTest('Local deployment scripts are intentionally excluded from Git.')

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='blogshed-script-test-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        for folder in ('bin', 'state', 'var/www', 'var/log/nginx',
                       'etc/php/8.5/fpm/pool.d', 'etc/nginx/sites-available',
                       'etc/nginx/sites-enabled', 'etc/ssl/blogshed'):
            (self.root / folder).mkdir(parents=True, exist_ok=True)
        for cert in ('origin.pem', 'origin.key'):
            (self.root / 'etc/ssl/blogshed' / cert).touch()
        for name in ('systemd-run', 'systemctl', 'readlink', 'timeout', 'id', 'useradd',
                     'userdel', 'pkill', 'chown', 'flock', 'stat', 'cat', 'mysql',
                     'php-fpm8.5', 'nginx', 'sudo', 'wp'):
            path = self.root / 'bin' / name
            path.write_text('#!' + sys.executable + '\n' + SHIM)
            path.chmod(0o755)
        for name in ('create-wordpress.sh', 'delete-wordpress.sh', 'reset-wordpress-password.sh'):
            source = (PROJECT / name).read_text()
            self.assertIn('if [[ $EUID -ne 0 ]]; then', source)
            source = source.replace('if [[ $EUID -ne 0 ]]; then', 'if false; then')
            for prefix in ('/var/lock', '/etc/blogshed', '/var/www', '/var/log/nginx',
                           '/run/php', '/etc/php', '/etc/nginx', '/etc/ssl', '/home'):
                source = source.replace(prefix, str(self.root) + prefix)
            (self.root / name).write_text(source)
        self.metadata = self.root / 'etc/blogshed/sites/test-blog.conf'
        self.site = self.root / 'var/www/test-blog.blogshed.uk'

    def run_script(self, name='create', scenario=''):
        args = ['bash', str(self.root / (name + '-wordpress.sh')), 'test-blog']
        if name == 'create':
            args.append('owner+blog@example.com')
        result = subprocess.run(args, capture_output=True, text=True, timeout=20,
                                env={**os.environ, 'TEST_ROOT': str(self.root),
                                     'SCENARIO': scenario,
                                     'PATH': str(self.root / 'bin') + os.pathsep + os.environ['PATH']})
        self.assertTrue(result.stdout.strip(), result.stderr)
        data = json.loads(result.stdout)  # Also rejects duplicate rollback JSON.
        self.assertEqual(data['success'], result.returncode == 0, result.stderr)
        return data

    def assert_clean(self):
        self.assertFalse(self.metadata.exists())
        self.assertFalse(self.site.exists())
        self.assertEqual(list((self.root / 'state').iterdir()), [])
        self.assertEqual(list((self.root / 'etc/nginx/sites-enabled').iterdir()), [])
        self.assertEqual(list((self.root / 'etc/nginx/sites-available').iterdir()), [])
        self.assertEqual(list((self.root / 'etc/php/8.5/fpm/pool.d').iterdir()), [])

    def test_create_then_delete(self):
        self.assertTrue(self.run_script()['success'])
        # macOS sandbox may strip setgid; verify that bit on the Linux target.
        permission_mask = 0o7777 if sys.platform.startswith('linux') else 0o777
        for directory in [self.site, *self.site.rglob('*')]:
            if directory.is_dir():
                expected = 0o2755 if directory == self.site / 'wp-content/uploads' else 0o2750
                self.assertEqual(directory.stat().st_mode & permission_mask, expected & permission_mask)
        self.assertEqual((self.site / 'wp-config.php').stat().st_mode & 0o777, 0o600)
        # WordPress derives new directories from the parent's low permission
        # bits and uploaded files from the directory's read/write bits. A moved
        # upload may retain a tenant-only group, so Nginx needs other-read.
        uploads = self.site / 'wp-content/uploads'
        media_directory_mode = uploads.stat().st_mode & 0o777
        self.assertEqual(media_directory_mode & 0o005, 0o005)
        self.assertEqual(media_directory_mode & 0o666, 0o644)
        self.assertEqual(self.site.stat().st_mode & 0o007, 0)
        self.assertEqual(self.metadata.stat().st_mode & 0o777, 0o600)
        self.assertTrue(self.run_script('delete')['deleted'])
        self.assert_clean()

    def test_supervisor_releases_only_confirmed_stopped_operations(self):
        for script in ('create-wordpress.sh', 'delete-wordpress.sh', 'reset-wordpress-password.sh'):
            for scenario, expected in [('supervisor_stopped', 1), ('supervisor_active', 143), ('supervisor_unknown', 143)]:
                with self.subTest(script=script, scenario=scenario):
                    args = ['bash', str(self.root / script), 'test-blog']
                    if script == 'reset-wordpress-password.sh':
                        args.append('admin_01234567')
                    result = subprocess.run(args, capture_output=True, text=True, timeout=20,
                                            env={**os.environ, 'TEST_ROOT': str(self.root), 'SCENARIO': scenario,
                                                 'PATH': str(self.root / 'bin') + ':' + os.environ['PATH']})
                    self.assertEqual(result.returncode, expected, result.stderr)
                    self.assertFalse(json.loads(result.stdout)['success'])

    def test_creation_failures_clean_partial_resources(self):
        for scenario in ('partial_user', 'partial_db', 'partial_pool', 'wp_fail', 'nginx_fail', 'term', 'hup'):
            with self.subTest(scenario=scenario):
                self.assertFalse(self.run_script(scenario=scenario)['success'])
                self.assert_clean()

    def test_cleanup_failure_retains_metadata_and_delete_can_recover(self):
        for scenario in ('cleanup_db_fail', 'cleanup_user_fail'):
            with self.subTest(scenario=scenario):
                result = self.run_script(scenario=scenario)
                self.assertIn('incomplete', result['error'])
                self.assertTrue(self.metadata.exists())
                self.assertTrue(self.run_script('delete')['success'])
                self.assert_clean()

    def test_cleanup_deadline_retains_recovery_metadata(self):
        script = self.root / 'create-wordpress.sh'
        # Shorten only the rollback budget in the disposable test copy.
        script.write_text(script.read_text().replace(
            'CLEANUP_DEADLINE=$((SECONDS + CLEANUP_SECONDS))',
            'CLEANUP_DEADLINE=$((SECONDS + 1))'))
        result = self.run_script(scenario='cleanup_hang')
        self.assertIn('incomplete', result['error'])
        self.assertTrue(self.metadata.exists())
        self.assertTrue((self.root / 'state/db').exists())
        self.assertTrue(self.run_script('delete')['success'])
        self.assert_clean()

    def test_database_collision_aborts_before_resource_creation(self):
        self.assertFalse(self.run_script(scenario='collision')['success'])
        self.assert_clean()
        self.assertNotIn('useradd', (self.root / 'events').read_text())

    def test_partial_deletion_retains_metadata_and_can_be_resumed(self):
        for scenario in ('delete_db_fail', 'delete_reload_fail', 'delete_term'):
            with self.subTest(scenario=scenario):
                self.assertTrue(self.run_script()['success'])
                self.assertFalse(self.run_script('delete', scenario)['success'])
                self.assertTrue(self.metadata.exists())
                self.assertTrue(self.run_script('delete')['success'])
                self.assert_clean()

    def reset_password(self, scenario='', password='New password!$ 123\n'):
        # New metadata binds the original administrator; use a fixed test user.
        import re
        data = self.metadata.read_text()
        data = re.sub(r'^WP_ADMIN_USER=.*$', 'WP_ADMIN_USER=admin_01234567', data, flags=re.M)
        self.metadata.write_text(data)
        run = subprocess.run(['bash', str(self.root / 'reset-wordpress-password.sh'), 'test-blog', 'admin_01234567'],
                             input=password, capture_output=True, text=True, timeout=20,
                             env={**os.environ, 'TEST_ROOT': str(self.root), 'SCENARIO': scenario,
                                  'PATH': str(self.root / 'bin') + os.pathsep + os.environ['PATH']})
        self.assertNotIn(password.strip(), run.stdout + run.stderr)
        result = json.loads(run.stdout)
        self.assertEqual(result['success'], run.returncode == 0, run.stderr)
        return result

    def test_password_reset_uses_stdin_and_confirms_exact_target(self):
        self.assertTrue(self.run_script()['success'])
        result = self.reset_password()
        self.assertTrue(result['password_reset'])
        self.assertEqual(result['domain'], 'test-blog.blogshed.uk')
        self.assertEqual(result['admin_username'], 'admin_01234567')
        self.assertTrue((self.root / 'state/password_changed').exists())
        self.assertTrue(self.metadata.exists())
        self.assertTrue(self.site.exists())

    def test_reset_rejects_invalid_input_wrong_admin_and_wp_failure(self):
        self.assertTrue(self.run_script()['success'])
        for scenario, password in [('', 'short\n'), ('wrong_admin', 'New password!$ 123\n'), ('reset_fail', 'New password!$ 123\n')]:
            with self.subTest(scenario=scenario):
                self.assertFalse(self.reset_password(scenario, password)['success'])
                self.assertFalse((self.root / 'state/password_changed').exists())
                self.assertTrue(self.metadata.exists())

    def test_tampered_metadata_prevents_deletion(self):
        self.assertTrue(self.run_script()['success'])
        original = self.metadata.read_text()
        self.metadata.write_text(original.replace('DOMAIN=test-blog.blogshed.uk', 'DOMAIN=other.blogshed.uk'))
        self.assertFalse(self.run_script('delete')['success'])
        self.assertTrue(self.site.exists())
        self.assertTrue((self.root / 'state/db').exists())
        self.metadata.write_text(original)
        self.assertTrue(self.run_script('delete')['success'])
        self.assert_clean()


if __name__ == '__main__':
    unittest.main()
