"""Real local ClamAV adapter checks. No database writes, network download or antivirus exclusions."""
from pathlib import Path
import argparse, datetime, hashlib, json, os, shutil, subprocess

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--binary', type=Path, required=True)
    parser.add_argument('--database', type=Path, required=True)
    parser.add_argument('--aged-database', type=Path, required=True,
                        help='Genuine signed old database, not a modified header or simulated clock')
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    binary = args.binary.resolve(strict=True)
    database = args.database.resolve(strict=True)
    aged = args.aged_database.resolve(strict=True)
    assert database.is_dir() and aged.is_dir() and binary.is_file()
    work = root / '.local' / 'clamav-adapter-checks' / datetime.datetime.now().strftime('%Y%m%d%H%M%S%f')
    work.mkdir(parents=True)
    clean = work / 'clean.pdf'
    shutil.copy2(root / 'resources/demo-documents/cpd.pdf', clean)
    custom = work / 'test-signatures'
    custom.mkdir()
    for file in database.iterdir():
        if file.suffix in ['.cvd', '.cld', '.sign']:
            shutil.copy2(file, custom / file.name)
    marker = b'FICCT_ANTIMALWARE_ACCEPTANCE_TEST_ONLY_2026'
    (custom / 'ficct-test.ndb').write_text('FICCT.TestOnly.Acceptance:0:*:' + marker.hex() + '\n', encoding='ascii')
    synthetic = work / 'synthetic-detection.pdf'
    synthetic.write_bytes(clean.read_bytes() + b'\n' + marker + b'\n')
    literal = lambda p: "'" + str(p.as_posix()).replace("'", "\\'") + "'"
    helper = work / 'adapter.php'
    helper.write_text(
        '<?php require ' + literal(root / 'vendor/autoload.php') +
        ';$app=require ' + literal(root / 'bootstrap/app.php') +
        ';$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();'
        '$data=json_decode(file_get_contents($argv[1]),true);'
        'config(["document_safety.binary"=>$data["binary"],"document_safety.database"=>$data["database"]]);'
        'echo json_encode(app(App\\Domain\\ClamAvScanner::class)->scan($data["file"]));', encoding='utf-8')
    results = []
    for name, file, signatures, expected in [
        ('clean_pdf', clean, database, 'clean'),
        ('harmless_test_signature_pdf', synthetic, custom, 'blocked'),
        ('missing_database', clean, work / 'missing-database', 'error'),
        ('expired_signed_database', clean, aged, 'error'),
    ]:
        case = work / 'case.json'
        case.write_text(json.dumps({'binary': str(binary), 'database': str(signatures), 'file': str(file)}), encoding='utf-8')
        run = subprocess.run(['php', str(helper), str(case)], cwd=root,
                             env=dict(os.environ, APP_CONFIG_CACHE=str(work / 'unused-config.php')),
                             capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=90)
        assert run.returncode == 0, 'PHP adapter did not complete; inspect private diagnostics.'
        actual = json.loads(run.stdout)
        results.append({'name': name, 'expected': expected, 'actual': actual, 'passed': actual['state'] == expected})
    # Confirm the reason for both non-clean native cases, rather than inferring it from exit code alone.
    native = []
    for name, file, signatures in [('test_detection', synthetic, custom), ('aged_database', clean, aged)]:
        run = subprocess.run([str(binary), '--stdout', '--no-summary', '--database=' + str(signatures),
                              '--fail-if-cvd-older-than=2', '--alert-exceeds-max=yes', '--alert-encrypted=yes', '--', str(file)],
                             capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=90)
        text = (run.stdout + run.stderr).replace(str(work), '[WORK]').replace(str(aged), '[AGED_DATABASE]')
        passed = (run.returncode == 1 and 'FICCT.TestOnly.Acceptance' in text) if name == 'test_detection' else (
            run.returncode == 2 and 'older than' in text.lower())
        native.append({'name': name, 'passed': passed, 'exit_code': run.returncode, 'output': text})
    report = {'result': 'PASS_LIMITED_SCOPE' if all(r['passed'] for r in results + native) else 'FAIL',
              'no_test_doubles': True, 'adapter_cases': results, 'native_confirmation': native,
              'signature_sha256': {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in database.glob('*.cvd')},
              'scope': 'Real engine; harmless extra test signature. Not EICAR acceptance, malware corpus testing, or institutional approval.'}
    (work / 'result.json').write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps(report, ensure_ascii=False))
    return 0 if report['result'] == 'PASS_LIMITED_SCOPE' else 1

if __name__ == '__main__':
    raise SystemExit(main())
