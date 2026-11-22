"""Real process acceptance in a preconfigured disposable PostgreSQL clone only."""
from pathlib import Path
import argparse
import ctypes
import json
import os
import subprocess
import time
import uuid
import check_document_service as health


def run(config_path, binary):
    config = health.read_json(config_path)
    root = Path(config['root']).resolve()
    env = dict(os.environ, **config.get('environment', {}))
    # Verify effective Laravel configuration, not only an environment label.
    probe = r'require $argv[1]."/vendor/autoload.php";$app=require $argv[1]."/bootstrap/app.php";$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();echo config("database.connections.pgsql.database");'
    selected = subprocess.run([config['php'], '-r', probe, str(root)], cwd=root, env=env, capture_output=True, timeout=20, check=True).stdout.decode().strip()
    if not selected.startswith(('ficct_service_test_', 'ficct_applicants_')):
        raise ValueError('Disposable test database required; no processes started.')
    name = 'service-test-' + uuid.uuid4().hex[:12]
    config['worker_name'] = name
    state = root / '.local/document-processor'
    state.mkdir(parents=True, exist_ok=True)
    private_config = state / (name + '.config.json')
    private_config.write_text(json.dumps(config), encoding='utf-8')
    marker = state / (name + '.host.stop')
    children = []
    checks = []

    def wait(callback, seconds=25):
        until = time.monotonic() + seconds
        while time.monotonic() < until:
            value = callback()
            if value:
                return value
            time.sleep(.1)
        raise AssertionError('Lifecycle condition timed out')

    def status():
        try:
            return health.read_json(state / (name + '.host.json'))
        except (OSError, ValueError):
            return {}

    def active(process, previous=None):
        value = status()
        return value if value.get('host_pid') == process.pid and value.get('state') == 'running' and value.get('supervisor_pid') != previous and health.process_matches(value.get('supervisor_pid'), config['python']) else None

    def start():
        process = subprocess.Popen([str(binary), str(private_config), '--console'], cwd=root, stdout=subprocess.PIPE, stderr=subprocess.PIPE, creationflags=subprocess.CREATE_NO_WINDOW)
        children.append(process)
        return process, wait(lambda: active(process))['supervisor_pid']

    def worker_pids(supervisor):
        # PID is from this host's private status, never a user-supplied command.
        cmd = f'@(Get-CimInstance Win32_Process -Filter "ParentProcessId = {int(supervisor)}" | Select-Object -ExpandProperty ProcessId) | ConvertTo-Json -Compress'
        result = subprocess.run(['powershell', '-NoProfile', '-Command', cmd], capture_output=True, text=True, timeout=10, check=True).stdout.strip()
        value = json.loads(result) if result else []
        return value if isinstance(value, list) else [value]

    def alive(pid):
        native = ctypes.WinDLL('kernel32', use_last_error=True)
        native.OpenProcess.argtypes = [ctypes.c_uint, ctypes.c_int, ctypes.c_uint]
        native.OpenProcess.restype = ctypes.c_void_p
        native.WaitForSingleObject.argtypes = [ctypes.c_void_p, ctypes.c_uint]
        native.CloseHandle.argtypes = [ctypes.c_void_p]
        handle = native.OpenProcess(0x00100000, False, pid)
        if not handle:
            return False
        try:
            return native.WaitForSingleObject(handle, 0) == 258
        finally:
            native.CloseHandle(handle)

    try:
        host, supervisor = start()
        workers = wait(lambda: worker_pids(supervisor))
        wait(lambda: health.inspect(private_config, binary, allow_console=True, check_queue=False)['health'] == 'ok')
        checks.append('host_supervisor_y_PHP_reales_iniciados')
        duplicate = subprocess.run([str(binary), str(private_config), '--console'], cwd=root, capture_output=True, timeout=10)
        assert duplicate.returncode == 1 and alive(supervisor)
        checks.append('instancia_duplicada_rechazada')
        assert 'SCM_NOT_CONFIRMED' in health.inspect(private_config, binary, check_queue=False)['issues']
        checks.append('consola_no_se_declara_servicio_instalado')
        subprocess.run(['taskkill', '/PID', str(supervisor), '/F'], capture_output=True, check=True)
        resumed = wait(lambda: active(host, supervisor))
        assert resumed['restarts'] >= 1
        wait(lambda: all(not alive(pid) for pid in workers))
        new_supervisor = resumed['supervisor_pid']
        new_workers = wait(lambda: worker_pids(new_supervisor))
        checks.append('supervisor_recuperado_y_descendientes_anteriores_cerrados')
        marker.write_text('stop', encoding='utf-8')
        host.communicate(timeout=25)
        assert host.returncode == 0 and status()['state'] == 'stopped'
        wait(lambda: not alive(new_supervisor) and all(not alive(pid) for pid in new_workers))
        checks.append('parada_controlada_sin_huerfanos')
        host2, supervisor2 = start()
        workers2 = wait(lambda: worker_pids(supervisor2))
        checks.append('reinicio_controlado')
        host2.kill()
        host2.communicate(timeout=10)
        wait(lambda: not alive(supervisor2) and all(not alive(pid) for pid in workers2))
        checks.append('caida_host_cierra_su_JobObject')
        assert 'HOST_NOT_RUNNING' in health.inspect(private_config, binary, allow_console=True, check_queue=False)['issues']
        checks.append('monitor_detecta_caida_sin_esperar_latido_vencido')
        return {'result': 'PASS_CONSOLE_SCOPE', 'checks': checks, 'SCM_installed': False, 'reboot': 'NOT_VERIFIED', 'NO_GO_INSTITUCIONAL': True}
    finally:
        for process in children:
            if process.poll() is None:
                marker.write_text('stop', encoding='utf-8')
                try:
                    process.communicate(timeout=25)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.communicate(timeout=10)
        # Only the exact private file created by this test; preserve runtime history.
        private_config.unlink(missing_ok=True)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', required=True, type=Path)
    parser.add_argument('--host-binary', required=True, type=Path)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    report = run(args.config.resolve(), args.host_binary.resolve())
    args.output.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps(report, ensure_ascii=False))
