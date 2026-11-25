"""Read-only consistency checks for canonical RF, documentary sources and the current article matrix."""
from pathlib import Path
import csv,json,re,hashlib,xml.etree.ElementTree as ET
ROOT=Path(__file__).resolve().parents[1]
def verify():
 rows=list(csv.DictReader((ROOT/'docs/02-TRAZABILIDAD.csv').open(encoding='utf-8-sig',newline='')))
 assert len(rows)==30 and [r['RF'] for r in rows]==[f'RF-{i:02}' for i in range(1,31)]
 lines=[l for l in (ROOT/'docs/02-TRAZABILIDAD.md').read_text(encoding='utf-8').splitlines() if l.startswith('| RF-')];assert len(lines)==30
 current=json.loads((ROOT/'evidence/current-validation.json').read_text(encoding='utf-8'));junit=(ROOT/current['junit']).resolve();assert junit.is_relative_to(ROOT)
 cases=ET.parse(junit).findall('.//testcase');ids={(c.attrib['class'].split('\\')[-1],c.attrib['name']) for c in cases}
 for r,line in zip(rows,lines):
  cells=[s.strip() for s in line.strip('|').split('|')];assert len(cells)==8
  assert cells[:3]==[r['RF']+' · '+r['Nombre'],r['CU'],r['Pantalla']]
  assert cells[3].replace('{pdf/xlsx}','{pdf|xlsx}')==r['Endpoint'] and cells[4:6]==[r['Tabla'],r['Regla']] and cells[7]==r['Pendiente']
  refs=r['Test'].split('; ');assert re.findall(r'`([^`]+)`',cells[6])==refs and r['Resultado'] in cells[6]
  for ref in refs:
   path,method=ref.split('::');assert (Path(path).stem,method) in ids,ref
   assert re.search(r'function\s+'+re.escape(method)+r'\s*\(', (ROOT/path).read_text(encoding='utf-8')),ref
 m=json.loads((ROOT/'docs/MATRIZ_NORMATIVA_ACTUAL.json').read_text(encoding='utf-8'));assert len(m['articulos'])==82 and len({a['id'] for a in m['articulos']})==82
 for a in m['articulos']:
  assert all(a[k] for k in ['regla_parafraseada','estado_actual','adenda_tecnica','verificacion_nueva','dependencia_externa','paginas_fisicas'])
  if a['verificacion_nueva'].startswith('tests/'):
   path,method=a['verificacion_nueva'].split('::');assert (Path(path).stem,method) in ids
 catalogue=json.loads((ROOT/'resources/institutional/catalogue.json').read_text(encoding='utf-8'))
 for source in catalogue['sources'].values():assert hashlib.sha256((ROOT/'resources/institutional'/source['file']).read_bytes()).hexdigest()==source['sha256']
 assert hashlib.sha256((ROOT/'docs/CU_Y_RF_CANONICOS.md').read_bytes()).hexdigest()=='fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2'
 return {'status':'PASS','RF':30,'articles':82,'originals':3,'phpunit_cases':len(cases),'canonical_unchanged':True}
if __name__=='__main__':print(json.dumps(verify()))
