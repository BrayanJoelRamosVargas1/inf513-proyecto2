"""Synthetic classification tests, separate from real Windows lifecycle acceptance."""
from pathlib import Path
import datetime
import json
import tempfile
import unittest
from unittest.mock import patch
import check_document_service as health


class ServiceHealthTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.state = self.root / '.local/document-processor'
        self.state.mkdir(parents=True)
        self.config = self.root / 'config.json'
        self.config.write_text(json.dumps({'root': str(self.root), 'worker_name': 'test', 'python': 'python.exe', 'php': 'php.exe', 'environment': {'SECRET': 'private-marker-not-for-output'}}), encoding='utf-8')
        self.now = datetime.datetime.now(datetime.timezone.utc)
        self.host = {'time_utc': self.now.isoformat(), 'state': 'running', 'mode': 'windows-service', 'host_pid': 1, 'supervisor_pid': 2}
        self.worker = {'time_utc': self.now.isoformat(), 'event': 'worker_started'}

    def tearDown(self):
        self.temp.cleanup()

    def inspect(self, **kwargs):
        (self.state / 'test.host.json').write_text(json.dumps(self.host), encoding='utf-8')
        (self.state / 'test.json').write_text(json.dumps(self.worker), encoding='utf-8')
        with patch.object(health, 'process_matches', return_value=True):
            return health.inspect(self.config, 'host.exe', check_queue=False, **kwargs)

    def test_fresh_running_status(self):
        self.assertEqual('ok', self.inspect()['health'])

    def test_stale_host_even_with_running_process(self):
        self.host['time_utc'] = (self.now - datetime.timedelta(seconds=30)).isoformat()
        self.assertIn('HOST_HEARTBEAT_STALE', self.inspect()['issues'])

    def test_future_timestamp_is_not_fresh(self):
        self.host['time_utc'] = (self.now + datetime.timedelta(minutes=2)).isoformat()
        self.assertIn('HOST_HEARTBEAT_STALE', self.inspect()['issues'])

    def test_console_does_not_confirm_scm(self):
        self.host['mode'] = 'console-test'
        self.assertIn('SCM_NOT_CONFIRMED', self.inspect()['issues'])
        self.assertEqual('ok', self.inspect(allow_console=True)['health'])

    def test_worker_failure_even_if_host_is_alive(self):
        self.worker.update(event='worker_exited', exit_code=1)
        self.assertIn('WORKER_NOT_HEALTHY', self.inspect()['issues'])

    def test_worker_stale_even_if_host_heartbeat_is_fresh(self):
        self.worker['time_utc'] = (self.now - datetime.timedelta(seconds=200)).isoformat()
        self.assertIn('WORKER_HEARTBEAT_STALE', self.inspect()['issues'])

    def test_no_paths_credentials_or_pids_in_report(self):
        data = json.dumps(self.inspect())
        self.assertNotIn(str(self.root), data)
        self.assertNotIn('private-marker-not-for-output', data)
        self.assertNotIn('pid', data)
        self.assertFalse(self.inspect()['external_alert_delivered'])

    def test_invalid_or_dead_process_identity(self):
        self.assertFalse(health.process_matches(-1, 'unrelated.exe'))
        self.assertFalse(health.process_matches(0, 'unrelated.exe'))


if __name__ == '__main__':
    unittest.main(verbosity=2)
