"""Auditoría reproducible de las matrices y el ZIP; no modifica la aplicación."""
from pathlib import Path
import argparse
import csv
import datetime
import hashlib
import json
import re
import xml.etree.ElementTree as ET
import zipfile

ROOT = Path(__file__).resolve().parents[1]
CANONICAL = 'fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2'


def verify(package):
    rows = list(csv.DictReader((ROOT / 'docs/02-TRAZABILIDAD.csv').open(encoding='utf-8-sig', newline='')))
    assert len(rows) == 30
    assert [r['RF'] for r in rows] == [f'RF-{i:02}' for i in range(1, 31)]
    table = {}
    for line in (ROOT / 'docs/02-TRAZABILIDAD.md').read_text(encoding='utf-8').splitlines():
        if line.startswith('| RF-'):
            cells = [s.strip() for s in line.strip('|').split('|')]
            assert len(cells) == 8
            key = cells[0].split(' · ')[0]
            assert key not in table
            table[key] = cells
    assert len(table) == 30
    current = ROOT / 'evidence/current-validation.json'
    junit = (ROOT / json.loads(current.read_text(encoding='utf-8'))['junit']).resolve() if current.exists() else ROOT / 'evidence/phpunit.xml'
    assert junit.is_relative_to(ROOT.resolve())
    cases = ET.parse(junit).findall('.//testcase')
    keys = {(c.attrib['class'].split('\\')[-1], c.attrib['name']) for c in cases}
    refs = set()
    for row in rows:
        cells = table[row['RF']]
        assert cells[0] == row['RF'] + ' · ' + row['Nombre']
        assert cells[1] == row['CU'] and cells[2] == row['Pantalla']
        assert cells[3].replace('{pdf/xlsx}', '{pdf|xlsx}') == row['Endpoint']
        assert cells[4] == row['Tabla'] and cells[5] == row['Regla']
        assert cells[7] == row['Pendiente']
        methods = row['Test'].split('; ')
        assert re.findall(r'`([^`]+)`', cells[6]) == methods
        assert row['Resultado'] in cells[6]
        for ref in methods:
            path, method = ref.split('::')
            assert (Path(path).stem, method) in keys, ref
            assert re.search(r'function\s+' + re.escape(method) + r'\s*\(', (ROOT / path).read_text(encoding='utf-8')), ref
            refs.add(ref)
    contract = hashlib.sha256((ROOT / 'docs/CU_Y_RF_CANONICOS.md').read_bytes()).hexdigest()
    assert contract == CANONICAL
    with zipfile.ZipFile(package) as archive:
        assert archive.testzip() is None
        names = archive.namelist()
        assert len(names) == len(set(names))
        manifest = json.loads(archive.read('sistema/MANIFIESTO_SHA256.json'))
        assert set(names) == {'sistema/' + n for n in manifest} | {'sistema/MANIFIESTO_SHA256.json'}
        for name, digest in manifest.items():
            assert hashlib.sha256(archive.read('sistema/' + name)).hexdigest() == digest, name
        for name in ['docs/02-TRAZABILIDAD.csv', 'docs/02-TRAZABILIDAD.md', 'docs/CU_Y_RF_CANONICOS.md', 'evidence/traceability-documentation-fix.json']:
            assert archive.read('sistema/' + name) == (ROOT / name).read_bytes(), name
    return {
        'result': 'PASS', 'executed_at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
        'rf_rows': len(rows), 'csv_markdown_all_fields': 'PASS',
        'unique_methods_found_in_source_and_recorded_xml': len(refs),
        'recorded_junit': junit.relative_to(ROOT.resolve()).as_posix(),
        'canonical_sha256': contract, 'zip_files': len(names),
        'verified_manifest_hashes': len(manifest), 'zip_crc': 'PASS',
        'package': Path(package).name, 'package_sha256': hashlib.sha256(Path(package).read_bytes()).hexdigest(),
        'scope': 'Direct local documentary verification; not institutional or external certification',
    }


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--package', type=Path, default=ROOT.parent / 'SISTEMA_FICCT_GRUPO09_DEMO_0_3.zip')
    parser.add_argument('--output', type=Path, default=ROOT / 'evidence/validacion-local-03/documental.json')
    args = parser.parse_args()
    report = verify(args.package)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(report, ensure_ascii=False))
