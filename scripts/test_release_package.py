"""Checks for publication leaks and archive tampering using temporary files."""
from pathlib import Path
import hashlib
import io
import json
import tarfile
import tempfile
import unittest
import package_academic_release as release


class ReleasePackageTest(unittest.TestCase):
    def test_private_files_and_known_secrets_are_rejected_without_echoing_secret(self):
        secret='private-value-that-must-not-be-published'
        with self.assertRaises(ValueError) as error:
            release.reject_secrets({'app/example.php':secret.encode()},[secret])
        self.assertNotIn(secret,str(error.exception))
        with self.assertRaises(ValueError):
            release.reject_secrets({'.local/config.json':b'private'},[])

    def make_archive(self,path,expected,tamper=False,link=False):
        content=dict(expected)
        content['MANIFIESTO_SHA256.json']=json.dumps({n:hashlib.sha256(b).hexdigest() for n,b in expected.items()}).encode()
        if tamper:
            content['app/example.php']=b'altered after hashing'
        with tarfile.open(path,'w:gz') as archive:
            for name,data in content.items():
                info=tarfile.TarInfo('sistema/'+name)
                info.size=len(data)
                archive.addfile(info,io.BytesIO(data))
            if link:
                info=tarfile.TarInfo('sistema/unsafe-link')
                info.type=tarfile.SYMTYPE
                info.linkname='../../private'
                archive.addfile(info)

    def test_hash_verification_rejects_payload_changed_after_manifest(self):
        with tempfile.TemporaryDirectory() as temp:
            file=Path(temp)/'test.tar.gz'
            expected={'docs/CU_Y_RF_CANONICOS.md':(release.ROOT/'docs/CU_Y_RF_CANONICOS.md').read_bytes(),'app/example.php':b'reviewed'}
            self.make_archive(file,expected)
            self.assertEqual(release.verify_archive(file,expected),(3,2))
            self.make_archive(file,expected,tamper=True)
            with self.assertRaises(ValueError):
                release.verify_archive(file,expected)

    def test_archive_links_are_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            file=Path(temp)/'test.tar.gz'
            expected={'docs/CU_Y_RF_CANONICOS.md':(release.ROOT/'docs/CU_Y_RF_CANONICOS.md').read_bytes()}
            self.make_archive(file,expected,link=True)
            with self.assertRaisesRegex(ValueError,'Unsafe'):
                release.verify_archive(file,expected)


if __name__=='__main__':
    unittest.main(verbosity=2)
