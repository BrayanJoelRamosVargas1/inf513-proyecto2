"""Verificación de integridad y continuidad documental, sin ejecutar Laravel."""
from pathlib import Path
import argparse, hashlib, json, zipfile, xml.etree.ElementTree as ET

UPDATED = {'README.md', 'docs/ESTADO_PROYECTO.md', 'docs/MANUAL_Y_DEMO.md',
           'docs/03-CONTRASTE_NORMATIVO_DEMO_0_3.md'}


def verify(package):
    with zipfile.ZipFile(package) as z:
        assert z.testzip() is None
        names=z.namelist(); assert len(names)==len(set(names))
        read=lambda n:z.read('sistema/'+n)
        data=lambda n:json.loads(read(n))
        manifest=data('MANIFIESTO_SHA256.json')
        assert set(names)=={'sistema/'+n for n in manifest}|{'sistema/MANIFIESTO_SHA256.json'}
        for n,h in manifest.items(): assert hashlib.sha256(read(n)).hexdigest()==h,n
        baseline=data('evidence/continuidad-03/manifiesto-base-operativa.json')
        assert len(baseline)==431
        changed={n for n,h in baseline.items() if hashlib.sha256(read(n)).hexdigest()!=h}
        assert changed==UPDATED,changed
        assert not any(n.startswith(('app/','routes/','database/','resources/','tests/')) for n in set(manifest)-set(baseline))
        canonical='fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2'
        assert hashlib.sha256(read('docs/CU_Y_RF_CANONICOS.md')).hexdigest()==canonical
        p='evidence/navegador-participacion-03/'
        ui=data(p+'recorrido-navegador.json'); assert ui['resultado']=='PASS'
        assert len(ui['comprobaciones'])==13 and all(c['resultado']=='PASS' for c in ui['comprobaciones'])
        assert len(ui['solicitudes'])==85
        assert {s:sum(r['status']==s for r in ui['solicitudes']) for s in [200,302,403]}=={200:64,302:16,403:5}
        for i in range(1,14): assert read(p+f'pantallas/{i:02}.png').startswith(b'\x89PNG')
        db=data(p+'verificacion-base-datos.json')
        assert db['resultado']=='PASS' and db['tablas_demo_disponible_sin_cambios']==31
        assert db['historia_existente_identica'] and db['publicacion_nueva_sin_reemplazo']
        xml=ET.fromstring(read('evidence/operacion-03/regresion-cacheada/phpunit.xml'));cases=xml.findall('.//testcase')
        assert len(cases)==119 and sum(int(c.get('assertions','0')) for c in cases)==489
        assert not xml.findall('.//failure') and not xml.findall('.//error')
        for n in UPDATED|{'docs/CONTINUIDAD_VERIFICADA_DEMO_0_3.md'}:
            text=read(n).decode('utf-8'); assert '8116' in text or n=='docs/03-CONTRASTE_NORMATIVO_DEMO_0_3.md'
            assert '15/11/2026' in text
        return {'resultado':'PASS','archivos':len(names),'hashes':len(manifest),
                'archivos_base_conservados':len(baseline)-len(changed),'documentos_actualizados':sorted(changed),
                'comprobaciones_navegador_nuevas':13,'respuestas_http':85,'capturas':13,
                'pruebas_phpunit_historicas_conservadas':119,'aserciones_historicas':489,
                'phpunit_ejecutado_por_este_verificador':False,'canonico_sha256':canonical,
                'sha256_zip':hashlib.sha256(Path(package).read_bytes()).hexdigest(),
                'alcance':'Verificación documental de entrega, sin certificación institucional.'}


if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('package',type=Path);p.add_argument('--output',type=Path);a=p.parse_args();r=verify(a.package)
    if a.output:a.output.write_text(json.dumps(r,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(json.dumps(r,ensure_ascii=False))
