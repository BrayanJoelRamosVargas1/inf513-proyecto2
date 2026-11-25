"""Failure and preservation checks in temporary directories, never in the active runtime."""
from pathlib import Path
import json
import subprocess
import tempfile
import unittest
from unittest.mock import patch
import sync_runtime as sync


class RuntimeSyncTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name) / 'source'
        self.target = Path(self.tmp.name) / 'runtime'
        for folder in (self.root, self.target):
            (folder / 'app').mkdir(parents=True)
            (folder / 'public/build').mkdir(parents=True)
            (folder / 'bootstrap/cache').mkdir(parents=True)
            (folder / '.local').mkdir()
            (folder / '.local/database.json').write_text('{}')
            (folder / 'artisan').write_text('fixture')
            for name in ('composer.lock', 'package-lock.json'):
                (folder / name).write_text('{}')
        (self.root / 'app/Controller.php').write_text('new code')
        (self.target / 'app/Controller.php').write_text('old code')
        (self.target / '.env').write_text('secret-do-not-touch')
        (self.target / 'storage').mkdir()
        (self.target / 'storage/original.pdf').write_bytes(b'original bytes')

    def tearDown(self):
        self.tmp.cleanup()

    def apply(self, refresh=False):
        hashes = sync.inventory(self.root)
        return sync.apply(self.root, self.target, hashes,
                          [n for n, h in hashes.items() if not (self.target/n).exists() or sync.digest(self.target/n) != h],
                          Path(self.tmp.name) / 'backups', refresh=refresh)

    def test_apply_and_rollback_preserve_secrets_and_original_files(self):
        backup = self.apply()
        self.assertEqual((self.target / 'app/Controller.php').read_text(), 'new code')
        sync.rollback(self.target, backup)
        self.assertEqual((self.target / 'app/Controller.php').read_text(), 'old code')
        self.assertEqual((self.target / '.env').read_text(), 'secret-do-not-touch')
        self.assertEqual((self.target / 'storage/original.pdf').read_bytes(), b'original bytes')
        self.assertFalse((backup / 'files/.env').exists())

    def test_rollback_refuses_to_overwrite_a_later_manual_change(self):
        backup = self.apply()
        (self.target / 'app/Controller.php').write_text('later manual change')
        with self.assertRaisesRegex(ValueError, 'changed since'):
            sync.rollback(self.target, backup)
        self.assertEqual((self.target / 'app/Controller.php').read_text(), 'later manual change')

    def test_cache_failure_restores_code_and_removes_only_its_new_cache(self):
        def fail(*args, **kwargs):
            (self.target / 'bootstrap/cache/routes-v7.php').write_text('failed cache')
            return subprocess.CompletedProcess(args, 1, b'', b'controlled error')
        with patch.object(sync.subprocess, 'run', side_effect=fail):
            with self.assertRaises(RuntimeError):
                self.apply(refresh=True)
        self.assertEqual((self.target / 'app/Controller.php').read_text(), 'old code')
        self.assertFalse((self.target / 'bootstrap/cache/routes-v7.php').exists())
        self.assertEqual((self.target / 'storage/original.pdf').read_bytes(), b'original bytes')

    def test_path_traversal_is_rejected(self):
        with self.assertRaises(ValueError):
            sync.safe_path(self.target, '../outside.php')

    def test_stale_frontend_is_rejected(self):
        (self.root / 'resources/js/portal').mkdir(parents=True)
        (self.root / 'resources/js/portal/main.jsx').write_text('first build')
        (self.root / 'scripts').mkdir()
        for name in ('package.json', 'vite.config.js', 'scripts/write_frontend_build_manifest.mjs'):
            (self.root / name).write_text('fixture')
        sources=['resources/js/portal/main.jsx','package.json','package-lock.json','vite.config.js','scripts/write_frontend_build_manifest.mjs']
        receipt={'sources':{n:sync.digest(self.root/n) for n in sources},'artifacts':{}}
        (self.root/'public/build/source-hashes.json').write_text(json.dumps(receipt))
        sync.verify_build(self.root)
        (self.root/'resources/js/portal/main.jsx').write_text('uncompiled edit')
        with self.assertRaisesRegex(ValueError, 'differs from its build'):
            sync.verify_build(self.root)


if __name__ == '__main__':
    unittest.main(verbosity=2)
