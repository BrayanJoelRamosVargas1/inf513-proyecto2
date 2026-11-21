"""Verifica el paquete operativo y sus registros; no reemplaza ejecutar la aplicación."""
from pathlib import Path
import argparse, hashlib, json, zipfile, xml.etree.ElementTree as ET


def verify(package):
    with zipfile.ZipFile(package) as z:
        assert z.testzip() is None
        names=z.namelist(); assert len(names)==len(set(names))
        read=lambda n:z.read('sistema/'+n)
        data=lambda n:json.loads(read(n))
        manifest=data('MANIFIESTO_SHA256.json')
        assert set(names)=={'sistema/'+n for n in manifest}|{'sistema/MANIFIESTO_SHA256.json'}
        for n,h in manifest.items(): assert hashlib.sha256(read(n)).hexdigest()==h,n
        assert hashlib.sha256(read('docs/CU_Y_RF_CANONICOS.md')).hexdigest()=='fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2'
        p='evidence/operacion-03/'
        for n,h in data(p+'codigo-preservado.json').items(): assert hashlib.sha256(read(n)).hexdigest()==h,n
        for filename in ['phpunit.xml','regresion-cacheada/phpunit.xml']:
            xml=ET.fromstring(read(p+filename)); cases=xml.findall('.//testcase')
            assert len(cases)==119 and sum(int(c.get('assertions','0')) for c in cases)==489
            assert not xml.findall('.//failure') and not xml.findall('.//error')
            assert sum(c.get('class','').endswith('SecuritySurfaceTest') for c in cases)==9
        previous=ET.fromstring(read('evidence/phpunit.xml')).findall('.//testcase')
        ids=lambda cs:{(c.get('class'),c.get('name')) for c in cs}
        assert ids(previous)<=ids(cases)
        load=data(p+'carga-autenticada.json')
        assert load['solicitudes']==300 and load['clientes_concurrentes']==5 and load['errores_http']==0
        assert len(load['rows'])==300 and all(r['status']==200 and r['sesion_valida'] for r in load['rows'])
        assert all(load['tablas_sin_cambios_salvo_auditoria'].values())
        restore=data(p+'recuperacion-ejecutable.json')
        assert restore['resultado']=='PASS' and restore['tablas']==31 and restore['triggers_inmutabilidad']==9
        assert restore['actualizacion_historica_rechazada'] and restore['aplicacion_recuperada_uso_base_restaurada']
        assert restore['descargas_http_restauradas_verificadas']==restore['archivos_privados_restaurados_y_sha256']>0
        assert restore['perfiles_con_login_real']==5 and len(restore['lecturas_http_restauradas'])==15
        assert data(p+'cache-en-ejecucion.json')['caches_activas_preservadas']
        profiles=data(p+'perfiles-en-ejecucion.json')['perfiles']; assert len(profiles)==5
        for role in profiles:
            assert role['sesion_autenticada'] and role['status']==200
            assert read(p+role['captura']).startswith(b'\x89PNG')
            assert all(x['status']==404 and x['cabecera_csp'] for x in role['identificadores_invalidos'])
        races=0
        for n in ['concurrency.json','governance-concurrency.json','participation-concurrency.json']:
            record=data(p+n); assert record['result']=='PASS'; races+=len(record['races'])
        assert races==11
        assert data(p+'conservacion.json')['resultado']=='PASS'
        return {'resultado':'PASS','archivos':len(names),'hashes':len(manifest),'pruebas':119,'aserciones':489,'solicitudes_carga':300,'perfiles':5,'escenarios_concurrencia':11,'sha256_zip':hashlib.sha256(Path(package).read_bytes()).hexdigest(),'alcance':'Integridad y coherencia de evidencias locales; no autorización institucional ni auditoría externa.'}


if __name__=='__main__':
    p=argparse.ArgumentParser(); p.add_argument('package',type=Path);p.add_argument('--output',type=Path);a=p.parse_args();result=verify(a.package)
    if a.output:a.output.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(json.dumps(result,ensure_ascii=False))
