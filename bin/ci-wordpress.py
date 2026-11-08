#!/usr/bin/env python3
"""Provision only disposable CI test resources; never run against the local site."""
import hashlib
import io
import json
import os
import re
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import urllib.request
import zipfile

if os.environ.get('CI') != 'true':
    sys.exit('This installer runs only in CI. Local WordPress test downloads are disabled.')
version, group = sys.argv[1:3]
if version not in ('6.5', '7.1') or group not in ('core', 'forms-a', 'forms-b', 'directory', 'commerce'):
    sys.exit('Unsupported pinned test matrix.')
workspace = Path(tempfile.mkdtemp(prefix='appna-ci-', dir='/tmp'))
core, tests = workspace / 'wordpress', workspace / 'wordpress-tests-lib'
core.mkdir()
def fetch(url):
    with urllib.request.urlopen(url, timeout=120) as response:
        return response.read()

data = fetch(f'https://wordpress.org/wordpress-{version}.tar.gz')
with tarfile.open(fileobj=io.BytesIO(data), mode='r:gz') as archive:
    for item in archive.getmembers():
        if item.issym() or item.islnk() or '..' in Path(item.name).parts or Path(item.name).is_absolute():
            sys.exit('Unsafe core archive member.')
    archive.extractall(workspace)
subprocess.run(['svn', 'export', '--quiet', f'https://develop.svn.wordpress.org/tags/{version}/tests/phpunit', str(tests)], check=True)
packages = json.loads((Path(__file__).resolve().parents[1] / 'tests/fixtures/integration-packages.json').read_text())
digests = []
for package in packages:
    if package['group'] != group:
        continue
    if package['source'] == 'artifact' and os.environ.get('CRAF_APPNA_TEST_EXTENSIONS') == 'no':
        continue
    slug, release = package['slug'], package['version']
    if package['source'] == 'artifact':
        artifact = Path(os.environ.get('APPNA_TEST_PACKAGES', '/tmp/appna-test-packages')) / f'{slug}-{release}.zip'
        if not artifact.is_file():
            sys.exit(f'Required licensed test artifact missing: {artifact.name}. No integration tests were skipped.')
        payload = artifact.read_bytes()
    else:
        payload = fetch(f'https://downloads.wordpress.org/plugin/{slug}.{release}.zip')
    with zipfile.ZipFile(io.BytesIO(payload)) as archive:
        for name in archive.namelist():
            if Path(name).is_absolute() or '..' in Path(name).parts or Path(name).parts[0] != slug:
                sys.exit(f'Unsafe package member for {slug}.')
        archive.extractall(core / 'wp-content/plugins')
    installed = core / 'wp-content/plugins' / package['file']
    if not installed.is_file():
        sys.exit(f'Required plugin entrypoint missing: {package["file"]}')
    header = installed.read_text(errors='replace')[:8192]
    match = re.search(r'^\s*\*?\s*Version:\s*(\S+)', header, re.M)
    if not match or match.group(1) != release:
        sys.exit(f'Package version does not match pinned release: {slug} {release}')
    digests.append({'slug': slug, 'version': release, 'sha256': hashlib.sha256(payload).hexdigest()})

# These credentials belong only to the ephemeral CI MySQL service.
(tests / 'wp-tests-config.php').write_text("""<?php
define( 'ABSPATH', getenv( 'WP_CORE_DIR' ) . '/' );
define( 'DB_NAME', 'appnatively_test' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', getenv( 'APPNA_TEST_DB_PASSWORD' ) );
define( 'DB_HOST', '127.0.0.1:3306' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'WP_TESTS_DOMAIN', 'example.test' );
define( 'WP_TESTS_EMAIL', 'tests@example.test' );
define( 'WP_TESTS_TITLE', 'AppNatively isolated tests' );
define( 'WP_DEBUG', true );
$table_prefix = 'appna_test_';
""")
with open(os.environ['GITHUB_ENV'], 'a') as env:
    env.write(f'WP_CORE_DIR={core}\nWP_TESTS_DIR={tests}\n')
Path('ci-package-digests.json').write_text(json.dumps(digests, indent=2) + '\n')
