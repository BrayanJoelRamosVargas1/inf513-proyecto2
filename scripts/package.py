"""Empaquetar archivos versionados con manifiesto, sin secretos ni dependencias locales."""
from pathlib import Path
import subprocess,hashlib,json,zipfile,sys

root=Path(__file__).resolve().parents[1]
target=Path(sys.argv[1]).resolve() if len(sys.argv)>1 else root.parent/'SISTEMA_FICCT_GRUPO09_DEMO_0_3.zip'
assert target.suffix.lower()=='.zip', 'El destino debe ser un archivo ZIP.'
assert not target.exists(), 'El paquete ya existe: elija un nombre nuevo para conservar la entrega anterior.'
tracked=subprocess.check_output(['git','ls-files','-z'],cwd=root).decode('utf-8').split('\0')
names=sorted(n for n in tracked if n and n!='MANIFIESTO_SHA256.json')
for name in names:
    parts=Path(name).parts
    assert '.local' not in parts and 'vendor' not in parts and '.git' not in parts,name
    assert Path(name).name not in ['.env','.env.testing','ACCESOS_DEMO.txt','auth.json'],name
    assert not name.endswith('.dump'),name
assert 'tests/Unit/.gitkeep' in names, 'Falta el marcador de tests/Unit en los archivos versionados.'
manifest={name:hashlib.sha256((root/name).read_bytes()).hexdigest() for name in names}
(root/'MANIFIESTO_SHA256.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED)as z:
    for name in names+['MANIFIESTO_SHA256.json']:z.write(root/name,'sistema/'+name)
with zipfile.ZipFile(target)as z:
    assert z.testzip() is None
    for name,value in manifest.items():assert hashlib.sha256(z.read('sistema/'+name)).hexdigest()==value,name
print(json.dumps({'package':str(target),'files':len(names)+1,'bytes':target.stat().st_size,'crc':'PASS','sha256_manifest':'PASS'},ensure_ascii=False))
