"""Reject unsafe release input before creating a cluster or installing code."""
import hashlib
import io
import json
from pathlib import Path
import tarfile
import tempfile
import unittest
from unittest.mock import patch
import verify_clean_install as install


class ReleaseInputTest(unittest.TestCase):
    def archive(self, folder, extra=(), tamper=False):
        data = {'docs/CU_Y_RF_CANONICOS.md': b'canonical fixture', 'artisan': b'fixture'}
        manifest = {n: hashlib.sha256(v).hexdigest() for n, v in data.items()}
        if tamper:
            data['artisan'] = b'altered'
        data['MANIFIESTO_SHA256.json'] = json.dumps(manifest).encode()
        archive = Path(folder) / 'release.tar.gz'
        with tarfile.open(archive, 'w:gz') as output:
            for name, content in [('sistema/' + n, v) for n, v in data.items()] + list(extra):
                info = tarfile.TarInfo(name)
                info.size = len(content)
                output.addfile(info, io.BytesIO(content))
        return archive, manifest

    def test_verified_manifest(self):
        with tempfile.TemporaryDirectory() as folder:
            archive, manifest = self.archive(folder)
            with patch.object(install, 'CANONICAL', manifest['docs/CU_Y_RF_CANONICOS.md']):
                data, actual = install.verified_content(archive)
            self.assertEqual(actual, manifest)
            self.assertEqual(data['artisan'], b'fixture')

    def test_changed_or_unsigned_bytes_rejected(self):
        with tempfile.TemporaryDirectory() as folder:
            for tamper, extra in ((True, ()), (False, (('sistema/extra.php', b'unsigned'),))):
                archive, _ = self.archive(folder, extra=extra, tamper=tamper)
                with self.assertRaises(ValueError):
                    install.verified_content(archive)

    def test_paths_and_private_content_rejected(self):
        with tempfile.TemporaryDirectory() as folder:
            for name in ('sistema/../../escape', 'sistema/C:/escape', 'sistema/.env',
                         'sistema/.local/secret', 'sistema\\escape', 'sistema/ARTISAN'):
                with self.subTest(name=name):
                    archive, _ = self.archive(folder, extra=((name, b'unsafe'),))
                    with self.assertRaises(ValueError):
                        install.verified_content(archive)

    def test_corrupt_gzip_rejected(self):
        with tempfile.TemporaryDirectory() as folder:
            archive, _ = self.archive(folder)
            content = bytearray(archive.read_bytes())
            content[-8] ^= 1
            archive.write_bytes(content)
            with self.assertRaises((OSError, EOFError)):
                install.verified_content(archive)


if __name__ == '__main__':
    unittest.main()
