"""Aísla las cachés de PHPUnit de una aplicación local que ya esté ejecutándose."""
from pathlib import Path
import os
import secrets


def isolated_test_environment(root: Path) -> dict:
    local = root.resolve() / '.local'
    cache = local / ('phpunit-cache-' + secrets.token_hex(8))
    cache.mkdir(parents=True)
    assert cache.resolve().is_relative_to(local.resolve())
    return dict(os.environ, APP_ENV='testing', APP_DEBUG='false', DB_CONNECTION='pgsql',
                DB_DATABASE='ficct_test', BCRYPT_ROUNDS='4', SESSION_DRIVER='array', CACHE_STORE='array',
                APP_CONFIG_CACHE=(cache / 'config.php').relative_to(root.resolve()).as_posix(),
                APP_ROUTES_CACHE=(cache / 'routes.php').relative_to(root.resolve()).as_posix())
