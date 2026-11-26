from pathlib import Path
import os,shutil,subprocess,sys
from test_environment import isolated_test_environment

root=Path(__file__).resolve().parents[1]
# Por defecto conserva el comportamiento histórico (.local/salidas/); FICCT_TEST_OUTPUT_DIR evita reescribir salidas locales.
out=Path(os.environ.get('FICCT_TEST_OUTPUT_DIR',root/'.local'/'salidas'));out.mkdir(parents=True,exist_ok=True)
env=isolated_test_environment(root)
args=['php','vendor/phpunit/phpunit/phpunit','--colors=never','--log-junit',str(out/'phpunit.xml')]+sys.argv[1:]
try:
    run=subprocess.run(args,cwd=root,env=env,capture_output=True,text=True,encoding='utf-8',errors='replace')
finally:
    shutil.rmtree((root/Path(env['APP_CONFIG_CACHE'])).parent,ignore_errors=True)  # F-06: no dejar una carpeta por ejecución
(out/'tests.txt').write_text(run.stdout+run.stderr,encoding='utf-8')
print(run.stdout+run.stderr)
sys.exit(run.returncode)
