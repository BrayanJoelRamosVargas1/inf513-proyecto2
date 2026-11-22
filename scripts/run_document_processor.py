"""Local process supervisor. Never installs a service, changes AV protection or releases files."""
from pathlib import Path
import argparse, datetime, json, os, re, signal, subprocess, time

def stop_child(child):
    if child is None or child.poll() is not None:
        return
    if os.name == 'nt':
        subprocess.run(['taskkill', '/PID', str(child.pid), '/T', '/F'],
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    else:
        os.killpg(child.pid, signal.SIGTERM)
    try:
        child.wait(timeout=5)
    except subprocess.TimeoutExpired:
        child.kill()
        child.wait()

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--name', default='worker-1')
    parser.add_argument('--cycles', type=int, default=0, help='0 = until the private stop marker appears')
    parser.add_argument('--idle-seconds', type=float, default=2)
    parser.add_argument('--watchdog-seconds', type=int, default=180)
    args = parser.parse_args()
    assert re.fullmatch(r'[A-Za-z0-9_-]{1,40}', args.name)
    assert args.cycles >= 0 and 1 <= args.idle_seconds <= 60 and args.watchdog_seconds >= 140
    root = Path(__file__).resolve().parents[1]
    private = root / '.local/document-processor'
    private.mkdir(parents=True, exist_ok=True)
    lock = (private / (args.name + '.lock')).open('a+b')
    lock.seek(0)
    if lock.read(1) == b'':
        lock.write(b'1')
        lock.flush()
    lock.seek(0)
    try:
        if os.name == 'nt':
            import msvcrt
            msvcrt.locking(lock.fileno(), msvcrt.LK_NBLCK, 1)
        else:
            import fcntl
            fcntl.flock(lock.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        raise SystemExit('A supervisor with this name is already running.')
    stop = private / (args.name + '.stop')
    status = private / (args.name + '.json')
    log = private / (args.name + '.jsonl')
    child = None
    def record(event, **data):
        payload = {'time_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'event': event, **data}
        temporary = status.with_suffix('.tmp')
        temporary.write_text(json.dumps(payload), encoding='utf-8')
        temporary.replace(status)
        if log.exists() and log.stat().st_size > 2 * 1024 * 1024:
            old = log.with_suffix('.previous.jsonl')
            if old.exists():
                old.unlink()  # Only this supervisor's bounded, private operational log.
            log.rename(old)
        with log.open('a', encoding='utf-8') as handle:
            handle.write(json.dumps(payload) + '\n')
    def terminate_signal(*_):
        raise KeyboardInterrupt
    signal.signal(signal.SIGTERM, terminate_signal)
    cycle = failures = 0
    try:
        record('started')
        while not stop.exists() and (args.cycles == 0 or cycle < args.cycles):
            cycle += 1
            options = {'creationflags': subprocess.CREATE_NO_WINDOW} if os.name == 'nt' else {'start_new_session': True}
            child = subprocess.Popen(['php', 'artisan', 'ficct:scan-worker', '--max-jobs=20', '--max-seconds=60'],
                                     cwd=root, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, **options)
            record('worker_started', cycle=cycle)
            deadline = time.monotonic() + args.watchdog_seconds
            timed_out = False
            while child.poll() is None:
                if stop.exists() or time.monotonic() >= deadline:
                    timed_out = not stop.exists()
                    stop_child(child)
                    break
                time.sleep(.2)
            code = child.wait()
            record('watchdog_terminated' if timed_out else 'worker_exited', cycle=cycle, exit_code=code)
            child = None
            failures = failures + 1 if code != 0 or timed_out else 0
            delay = min(30, 2 ** min(failures, 5)) if failures else args.idle_seconds
            until = time.monotonic() + delay
            while not stop.exists() and time.monotonic() < until:
                time.sleep(.2)
    except KeyboardInterrupt:
        record('stop_requested')
    finally:
        stop_child(child)
        record('stopped', cycle=cycle)
        lock.close()
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
