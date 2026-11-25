"""Reconcile the operative audit baseline without changing rows, files or counters."""
import argparse
import collections
import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import urllib.request

app=Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser(description=__doc__)
p.add_argument('--output',type=Path,required=True)
a=p.parse_args();out=a.output.resolve()
if not out.is_relative_to(app.parent/'AUDITORIA_INTEGRAL_INF513_20261123'):
    raise ValueError('Use the existing audit evidence directory')
private=app/'.local/integral-audit/20261123195243'
b=json.loads((private/'baseline.json').read_text(encoding='utf-8'))
s=json.loads((app/'.local/operacion-03/state.json').read_text(encoding='utf-8'))
c=s['config'];env=dict(os.environ,PGPASSWORD=c['password']);pg=Path('C:/Program Files/PostgreSQL/15/bin')
def sql(q):
    return subprocess.check_output([str(pg/'psql.exe'),'-X','-v','ON_ERROR_STOP=1','-h','127.0.0.1','-p',str(c['port']),'-U',c['user'],'-d',s['database'],'-tAc',q],env=env).decode('utf-8').strip()
changed=[]
for table,digest in b['tables'].items():
    if not re.fullmatch('[a-z_]+',table):raise ValueError('Invalid table')
    actual=sql('select md5(coalesce(jsonb_agg(to_jsonb(t) order by to_jsonb(t)::text)::text,\'[]\')) from "'+table+'" t')
    if actual!=digest:changed.append(table)
assert set(changed)<=set(['access_events','audit_events','page_visits','public_visit_receipts','visit_receipts']),changed
for name,digest in b['private_files'].items():
    file=Path(s['runtime'])/'storage/app/private'/name
    assert file.resolve().is_relative_to((Path(s['runtime'])/'storage/app/private').resolve())
    assert hashlib.sha256(file.read_bytes()).hexdigest()==digest,name
checks={}
for table in changed:
    dump=subprocess.check_output([str(pg/'pg_restore.exe'),'--data-only','--table',table,'--file','-',str(private/'operative-before.dump')],env=env).decode('utf-8').replace('\r\n','\n')
    match=re.search(r'COPY public\.'+table+r' \(([^\n]+)\) FROM stdin;\n(.*?)\n\\\.',dump,re.S);assert match,table
    columns=match[1];old=match[2].splitlines();current=sql('COPY (SELECT '+columns+' FROM "'+table+'") TO STDOUT').splitlines()
    if table!='page_visits':
        assert not(collections.Counter(old)-collections.Counter(current)),table
        checks[table]={'original_rows_preserved':len(old),'current_rows':len(current)}
    else:
        names=columns.split(', ');key=names.index('path');count=names.index('total');original=[x.split('\t')for x in old];now={r[key]:r for r in [x.split('\t')for x in current]}
        assert all(r[key]in now and int(now[r[key]][count])>=int(r[count])for r in original)
        checks[table]={'original_paths_preserved':len(original),'decreased_counters':0}
canonical=hashlib.sha256((app/'docs/CU_Y_RF_CANONICOS.md').read_bytes()).hexdigest()
assert canonical=='fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2'
report={'result':'PASS','checked_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'unchanged_tables':len(b['tables'])-len(changed),'traffic_changes':checks,'original_private_files_preserved':len(b['private_files']),'canonical_sha256':canonical,'http_status':urllib.request.urlopen('http://127.0.0.1:8116/p2',timeout=15).status,'database_writes_performed_by_check':False}
out.write_text(json.dumps(report,indent=2),encoding='utf-8');print(json.dumps(report))
