"""Real POSIX process tests with synthetic PHP; not Linux application acceptance."""
import json
import os
from pathlib import Path
import shutil
import signal
import subprocess
import tempfile
import time
import unittest


@unittest.skipUnless(os.name == 'posix', 'Requires POSIX processes and flock')
class SupervisorPosixTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='ficct-posix-')
        self.root = Path(self.temp.name)
        (self.root / 'scripts').mkdir()
        source = Path(__file__).resolve().parents[1] / 'run_document_processor.py'
        shutil.copyfile(source, self.root / 'scripts/run_document_processor.py')
        self.bin = self.root / 'bin'
        self.bin.mkdir()
        php = self.bin / 'php'
        php.write_text('#!/bin/sh\nexec sleep 120\n', encoding='utf-8')
        php.chmod(0o700)
        self.env = dict(os.environ, PATH=str(self.bin) + ':' + os.environ['PATH'])
        self.processes = []

    def start(self):
        import sys
        process = subprocess.Popen([sys.executable, str(self.root / 'scripts/run_document_processor.py'),
                                    '--name', 'acceptance', '--idle-seconds', '1'],
                                   env=self.env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        self.processes.append(process)
        return process

    def wait_started(self):
        status = self.root / '.local/document-processor/acceptance.json'
        deadline = time.monotonic() + 10
        while time.monotonic() < deadline:
            if status.exists() and json.loads(status.read_text())['event'] == 'worker_started':
                return
            time.sleep(.05)
        self.fail('Supervisor did not start its synthetic worker')

    def tearDown(self):
        for process in self.processes:
            if process.poll() is None:
                process.send_signal(signal.SIGTERM)
                process.wait(timeout=10)
        self.temp.cleanup()

    def test_duplicate_name_does_not_start_second_supervisor(self):
        first = self.start()
        self.wait_started()
        second = self.start()
        self.assertNotEqual(second.wait(timeout=10), 0)
        self.assertIsNone(first.poll())

    def test_sigterm_stops_worker_and_records_stop(self):
        supervisor = self.start()
        self.wait_started()
        children = subprocess.check_output(['ps', '-o', 'pid=', '--ppid', str(supervisor.pid)], text=True)
        worker_ids = [int(pid) for pid in children.split()]
        self.assertEqual(len(worker_ids), 1)
        supervisor.send_signal(signal.SIGTERM)
        self.assertEqual(supervisor.wait(timeout=10), 0)
        for pid in worker_ids:
            with self.assertRaises(ProcessLookupError):
                os.kill(pid, 0)
        state = json.loads((self.root / '.local/document-processor/acceptance.json').read_text())
        self.assertEqual(state['event'], 'stopped')


if __name__ == '__main__':
    unittest.main(verbosity=2)
