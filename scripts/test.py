from pathlib import Path
import os,subprocess,sys
from test_environment import isolated_test_environment

root=Path(__file__).resolve().parents[1]
(root/'evidence').mkdir(exist_ok=True)
env=isolated_test_environment(root)
args=['php','vendor/phpunit/phpunit/phpunit','--colors=never','--log-junit','evidence/phpunit.xml']+sys.argv[1:]
run=subprocess.run(args,cwd=root,env=env,capture_output=True,text=True,encoding='utf-8',errors='replace')
(root/'evidence/tests.txt').write_text(run.stdout+run.stderr,encoding='utf-8')
print(run.stdout+run.stderr)
sys.exit(run.returncode)
