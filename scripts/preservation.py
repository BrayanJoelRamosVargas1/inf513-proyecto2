"""Huella de filas existentes antes/después de la migración. No exporta sus valores."""
from pathlib import Path
import os, subprocess, json, hashlib, sys
root=Path(__file__).resolve().parents[1]
cfg=json.loads((root/'.local/database.json').read_text())
pg=Path(os.environ.get('FICCT_PG_BIN',r'C:\Program Files\PostgreSQL\15\bin'))
env=dict(os.environ,PGPASSWORD=cfg['password'])
def sql(query):
    return subprocess.check_output([str(pg/'psql.exe'),'-h','127.0.0.1','-p',str(cfg['port']),'-U',cfg['user'],'-d','ficct_demo','-tAc',query],env=env).decode('utf-8').strip()
target=root/'.local/preservation-baseline.json'
if sys.argv[1]=='before':
    assert not target.exists(), 'No sobrescribir una línea de base existente.'
    baseline={}
    for table in ['users','calls','offers','applications','documents','reviews','assignments','grades','tie_exams','publications','audit_events']:
        columns=json.loads(sql("SELECT json_agg(column_name ORDER BY ordinal_position) FROM information_schema.columns WHERE table_schema='public' AND table_name='"+table+"'"))
        names=','.join('"'+c+'"' for c in columns)
        rows=sql('SELECT COALESCE(json_agg(t ORDER BY id),\'[]\') FROM (SELECT '+names+' FROM '+table+') t')
        baseline[table]={'columns':columns,'sha256':hashlib.sha256(rows.encode()).hexdigest(),'count':json.loads(sql('SELECT count(*) FROM '+table))}
    target.write_text(json.dumps(baseline),encoding='utf-8')
    print('Línea de base privada registrada.')
else:
    baseline=json.loads(target.read_text(encoding='utf-8')); report={}
    for table,entry in baseline.items():
        names=','.join('"'+c+'"' for c in entry['columns'])
        rows=sql('SELECT COALESCE(json_agg(t ORDER BY id),\'[]\') FROM (SELECT '+names+' FROM '+table+') t')
        actual=hashlib.sha256(rows.encode()).hexdigest()
        assert actual==entry['sha256'], table+' cambió durante la migración'
        report[table]={'rows':entry['count'],'sha256':actual,'preserved':True}
    (root/'evidence/migration-preservation.json').write_text(json.dumps({'result':'PASS','tables':report},indent=2),encoding='utf-8')
    print('PASS: todas las filas y columnas originales permanecen idénticas.')
