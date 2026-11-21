"""Integridad de la entrega y coherencia de sus evidencias; no ejecuta Laravel."""
from pathlib import Path
import json,zipfile,hashlib,xml.etree.ElementTree as ET,argparse

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
        p='evidence/seguridad-extendida-03/'
        for n,h in data(p+'codigo-preservado.json').items():assert hashlib.sha256(read(n)).hexdigest()==h,n
        xml=ET.fromstring(read(p+'regresion/phpunit.xml'));cases=xml.findall('.//testcase')
        assert len(cases)==118 and sum(int(c.get('assertions','0')) for c in cases)==487
        assert not xml.findall('.//failure') and not xml.findall('.//error')
        assert len([c for c in cases if c.get('class','').endswith('SecuritySurfaceTest')])==8
        final=ET.fromstring(read(p+'security-final.xml'));assert len(final.findall('.//testcase'))==8 and not final.findall('.//failure') and not final.findall('.//error')
        b=data(p+'navegador-ampliado.json');assert b['result']=='PASS_EN_MUESTRA' and len(b['screens'])==29
        assert all(not s['violations'] and not s['reflow']['overflow'] for s in b['screens'])
        assert len(b['security'])==14 and all(s['status']==s['expected'] and s['secured'] for s in b['security'])
        assert len([n for n in names if n.startswith('sistema/'+p+'pantallas/') and n.endswith('.png')])==58
        http=data(p+'http/recorrido-http.json');assert len(http['checks'])==21 and len(http['http_requests'])==104
        assert all(c['result']=='PASS' for c in http['checks']) and all(r['status']==r['expected'] for r in http['http_requests'])
        assert data(p+'conservacion.json')['resultado']=='PASS'
        audit=data(p+'composer-audit.json');assert not audit['advisories'] and not audit['abandoned']
        assert data(p+'cierre.json')['resultado']=='PASS_EN_ALCANCE_PROBADO'
        return {'resultado':'PASS','archivos':len(names),'hashes':len(manifest),'tests':118,'aserciones':487,'pantallas':29,'sondeos_acceso_cabeceras':14,'solicitudes_recorrido_http':104,'sha256_zip':hashlib.sha256(Path(package).read_bytes()).hexdigest(),'alcance':'Comprobación documental del paquete, sin certificar uso institucional ni pentest integral.'}

if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('package',type=Path);p.add_argument('--output',type=Path);a=p.parse_args();r=verify(a.package)
    if a.output:a.output.write_text(json.dumps(r,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(json.dumps(r,ensure_ascii=False))
