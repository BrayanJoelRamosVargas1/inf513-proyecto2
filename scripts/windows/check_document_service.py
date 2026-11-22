"""Read-only status for an external monitor; no messages, repairs or clean overrides."""
from pathlib import Path
import argparse
import ctypes
import datetime
import json
import os
import subprocess


def read_json(path):
    # A Windows atomic replace can momentarily deny a concurrent reader.
    import time
    for attempt in range(5):
        try:
            return json.loads(Path(path).read_text(encoding='utf-8-sig'))
        except PermissionError:
            if attempt == 4:
                raise
            time.sleep(.02)


def fresh(payload, seconds):
    instant = datetime.datetime.fromisoformat(payload['time_utc'].replace('Z', '+00:00'))
    age = (datetime.datetime.now(datetime.timezone.utc) - instant).total_seconds()
    return 0 <= age <= seconds


def process_matches(pid, expected):
    if os.name != 'nt' or not isinstance(pid, int) or pid <= 0:
        return False
    native = ctypes.WinDLL('kernel32', use_last_error=True)
    native.OpenProcess.argtypes = [ctypes.c_uint, ctypes.c_int, ctypes.c_uint]
    native.OpenProcess.restype = ctypes.c_void_p
    native.QueryFullProcessImageNameW.argtypes = [ctypes.c_void_p, ctypes.c_uint, ctypes.c_wchar_p, ctypes.POINTER(ctypes.c_uint)]
    native.WaitForSingleObject.argtypes = [ctypes.c_void_p, ctypes.c_uint]
    native.CloseHandle.argtypes = [ctypes.c_void_p]
    handle = native.OpenProcess(0x00101000, False, pid)
    if not handle:
        return False
    try:
        if native.WaitForSingleObject(handle, 0) != 258:
            return False
        buffer = ctypes.create_unicode_buffer(32768)
        size = ctypes.c_uint(len(buffer))
        return bool(native.QueryFullProcessImageNameW(handle, 0, buffer, ctypes.byref(size))) and os.path.normcase(os.path.realpath(buffer.value)) == os.path.normcase(os.path.realpath(expected))
    finally:
        native.CloseHandle(handle)


def inspect(config_file, host_binary, allow_console=False, check_queue=True):
    config = read_json(config_file)
    root = Path(config['root'])
    name = config['worker_name']
    import re
    if not re.fullmatch(r'[A-Za-z0-9_-]{1,40}', name):
        raise ValueError('Invalid worker name')
    private = root / '.local/document-processor'
    issues = []
    host = read_json(private / (name + '.host.json'))
    worker = read_json(private / (name + '.json'))
    if not fresh(host, 15):
        issues.append('HOST_HEARTBEAT_STALE')
    if host.get('mode') != 'windows-service' and not (allow_console and host.get('mode') == 'console-test'):
        issues.append('SCM_NOT_CONFIRMED')
    if host.get('state') != 'running' or not process_matches(host.get('host_pid'), host_binary):
        issues.append('HOST_NOT_RUNNING')
    if not process_matches(host.get('supervisor_pid'), config['python']):
        issues.append('SUPERVISOR_NOT_RUNNING')
    if not fresh(worker, 195):
        issues.append('WORKER_HEARTBEAT_STALE')
    if worker.get('event') not in ['worker_started', 'worker_exited'] or (worker.get('event') == 'worker_exited' and worker.get('exit_code') != 0):
        issues.append('WORKER_NOT_HEALTHY')
    if check_queue:
        env = dict(os.environ, **config.get('environment', {}))
        result = subprocess.run([config['php'], 'artisan', 'ficct:scan-queue-status', '--check'], cwd=root, env=env, capture_output=True, timeout=20)
        try:
            queue = json.loads(result.stdout)
            if result.returncode != 0 or queue.get('health') != 'ok':
                issues.append('QUEUE_WARNING')
        except (ValueError, UnicodeError):
            issues.append('QUEUE_UNAVAILABLE')
    return {'health': 'degraded' if issues else 'ok', 'issues': issues, 'mode': host.get('mode'), 'external_alert_delivered': False}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', required=True, type=Path)
    parser.add_argument('--host-binary', required=True, type=Path)
    parser.add_argument('--allow-console', action='store_true', help='Only for isolated acceptance; never confirms SCM installation.')
    args = parser.parse_args()
    try:
        report = inspect(args.config, args.host_binary, args.allow_console)
        code = 0 if report['health'] == 'ok' else 1
    except (OSError, ValueError, KeyError, TypeError, subprocess.TimeoutExpired):
        report = {'health': 'unknown', 'issues': ['STATUS_UNAVAILABLE'], 'external_alert_delivered': False}
        code = 2
    print(json.dumps(report))
    raise SystemExit(code)
