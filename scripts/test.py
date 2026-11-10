from pathlib import Path
import os,subprocess,sys

root=Path(__file__).resolve().parents[1]
(root/'evidence').mkdir(exist_ok=True)
env=dict(os.environ,APP_ENV='testing',DB_CONNECTION='pgsql',DB_DATABASE='ficct_test',BCRYPT_ROUNDS='4',SESSION_DRIVER='array',CACHE_STORE='array',APP_DEBUG='false')
args=['php','vendor/phpunit/phpunit/phpunit','--colors=never','--log-junit','evidence/phpunit.xml']+sys.argv[1:]
run=subprocess.run(args,cwd=root,env=env,capture_output=True,text=True,encoding='utf-8',errors='replace')
(root/'evidence/tests.txt').write_text(run.stdout+run.stderr,encoding='utf-8')
print(run.stdout+run.stderr)
sys.exit(run.returncode)
