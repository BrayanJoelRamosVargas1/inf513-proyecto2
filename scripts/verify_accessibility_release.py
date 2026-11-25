"""Comprueba el paquete de cierre sin conectar a PostgreSQL ni reejecutar pruebas."""
from pathlib import Path
import argparse,json,hashlib,zipfile,xml.etree.ElementTree as ET

def verify(package):
    with zipfile.ZipFile(package) as z:
        assert z.testzip() is None
        names=z.namelist();assert len(names)==len(set(names))
        read=lambda n:z.read('sistema/'+n)
        data=lambda n:json.loads(read(n))
        manifest=data('MANIFIESTO_SHA256.json')
        assert set(names)=={'sistema/'+n for n in manifest}|{'sistema/MANIFIESTO_SHA256.json'}
        for n,h in manifest.items():assert hashlib.sha256(read(n)).hexdigest()==h,n
        assert 'sistema/tests/Unit/.gitkeep' in names
        assert hashlib.sha256(read('docs/CU_Y_RF_CANONICOS.md')).hexdigest()=='fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2'
        prefix='evidence/cierre-accesibilidad-03/'
        protected=data(prefix+'codigo-protegido.json')
        for n,h in protected.items():assert hashlib.sha256(read(n)).hexdigest()==h,n
        cases=ET.fromstring(read(prefix+'regresion/phpunit.xml')).findall('.//testcase')
        assert len(cases)==110 and sum(int(c.attrib.get('assertions','0')) for c in cases)==415
        xml=ET.fromstring(read(prefix+'regresion/phpunit.xml'));assert not xml.findall('.//failure') and not xml.findall('.//error')
        a=data(prefix+'accesibilidad.json');assert len(a['screens'])==6
        assert all(not s['violations'] and not s['reflow']['overflow'] and s['skipWorking'] for s in a['screens'])
        http=data(prefix+'http/recorrido-http.json')
        assert len(http['checks'])==21 and all(c['result']=='PASS' for c in http['checks'])
        assert len(http['http_requests'])==104 and all(r['status']==r['expected'] for r in http['http_requests'])
        summary=data(prefix+'cierre.json');assert summary['resultado']=='PASS_EN_ALCANCE_PROBADO'
        assert summary['codigos_cerrados']==['REP-01','A11Y-01','A11Y-02']
        return {'resultado':'PASS','paquete':Path(package).name,'archivos':len(names),'hashes':len(manifest),'codigo_preexistente_protegido':len(protected),'tests':110,'aserciones':415,'pantallas':6,'avisos_axe':0,'desbordamientos_320px':0,'http_requests':104,'sha256_zip':hashlib.sha256(Path(package).read_bytes()).hexdigest(),'alcance':'Integridad y coherencia de evidencia nueva; no reejecución funcional ni certificación institucional.'}

if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('package',type=Path);p.add_argument('--output',type=Path);a=p.parse_args()
    result=verify(a.package)
    if a.output:a.output.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(json.dumps(result,ensure_ascii=False))
