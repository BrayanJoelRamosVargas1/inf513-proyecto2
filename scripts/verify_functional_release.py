"""Integridad y coherencia de la entrega funcional; no ejecuta Laravel ni certifica normativa."""
from pathlib import Path
import argparse,hashlib,json,zipfile,xml.etree.ElementTree as ET


def verify(package):
    with zipfile.ZipFile(package) as z:
        assert z.testzip() is None
        names=z.namelist();assert len(names)==len(set(names))
        read=lambda n:z.read('sistema/'+n)
        data=lambda n:json.loads(read(n))
        manifest=data('MANIFIESTO_SHA256.json')
        assert set(names)=={'sistema/'+n for n in manifest}|{'sistema/MANIFIESTO_SHA256.json'}
        for n,h in manifest.items():assert hashlib.sha256(read(n)).hexdigest()==h,n
        p='evidence/cierre-funcional-03/'
        baseline=data(p+'manifiesto-base.json');changes=data(p+'cambios-base.json')
        assert len(baseline)==455
        actual={n for n,h in baseline.items() if hashlib.sha256(read(n)).hexdigest()!=h}
        assert actual==set(changes['modificados'])
        assert not any(n.startswith('evidence/') for n in actual)
        assert not any(n.startswith(('app/Models/','tests/')) or n=='app/Domain/Selection.php' for n in actual)
        assert hashlib.sha256(read('docs/CU_Y_RF_CANONICOS.md')).hexdigest()=='fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2'
        current=data('evidence/current-validation.json');assert current['junit']==p+'regresion/phpunit.xml'
        xml=ET.fromstring(read(current['junit']));cases=xml.findall('.//testcase')
        assert len(cases)==136 and sum(int(c.get('assertions','0')) for c in cases)==585
        assert not xml.findall('.//failure') and not xml.findall('.//error')
        previous=ET.fromstring(read('evidence/operacion-03/regresion-cacheada/phpunit.xml')).findall('.//testcase')
        keys=lambda cs:{(c.get('class'),c.get('name')) for c in cs}
        assert len(previous)==119 and keys(previous)<=keys(cases)
        assert sum(c.get('class','').endswith('ConsolidatedResultsTest') for c in cases)==9
        assert sum(c.get('class','').endswith('ConfigurationCompletionTest') for c in cases)==8
        browser=data(p+'navegador-consolidado.json');assert browser['resultado']=='PASS_EN_MUESTRA'
        assert len(browser['screens'])==3 and all(not s['violations'] and s['reflow']['scroll']<=s['reflow']['width']+1 for s in browser['screens'])
        assert len(browser['authorization'])==12 and all(s['status']==403 for s in browser['authorization'])
        assert browser['scope_options']==['UAGRM','faculty','career']
        ui=data(p+'interfaz/recorrido-navegador.json');assert ui['resultado']=='PASS' and len(ui['comprobaciones'])==16
        assert all(c['resultado']=='PASS' for c in ui['comprobaciones']) and len(ui['solicitudes'])==103
        assert data(p+'interfaz/verificacion-base-datos.json')['historia_existente_identica']
        migration=data(p+'migracion-identificacion.json');assert migration['resultado']=='PASS' and migration['tablas_previas_conservadas']==31
        assert migration['dos_campos_nuevos_nulos_en_registros_previos'] and migration['nuevas_migraciones']==1
        assert read(p+'consolidado-demo.pdf').startswith(b'%PDF-') and read(p+'consolidado-demo.xlsx').startswith(b'PK')
        return {'resultado':'PASS','archivos':len(names),'hashes':len(manifest),'pruebas':136,'aserciones':585,
                'pruebas_anteriores_preservadas':119,'pruebas_nuevas':17,'comprobaciones_interfaz':16,
                'respuestas_http_interfaz':103,'vistas_consolidado_verificadas':3,'sondeos_permiso':12,
                'sha256_zip':hashlib.sha256(Path(package).read_bytes()).hexdigest(),
                'alcance':'Verificación documental del paquete y sus registros de ejecución local; no certificación institucional ni auditoría externa.'}


if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('package',type=Path);p.add_argument('--output',type=Path);a=p.parse_args();r=verify(a.package)
    if a.output:a.output.write_text(json.dumps(r,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(json.dumps(r,ensure_ascii=False))
