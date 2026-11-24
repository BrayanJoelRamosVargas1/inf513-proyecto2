"""Independent Decimal oracle over PostgreSQL snapshots; never calls the PHP calculation code."""
import argparse
from decimal import Decimal, ROUND_HALF_UP
import hashlib
import json
from pathlib import Path

app = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--fixture', type=Path, required=True)
parser.add_argument('--evidence', type=Path, required=True)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--snapshot-dir', default='final-readback')
args = parser.parse_args()
fixture_path = args.fixture.resolve()
if not fixture_path.is_relative_to((app / '.local/academic-close').resolve()):
    raise ValueError('Use the retained private fixture; do not distribute credentials')
fixture = json.loads(fixture_path.read_text(encoding='utf-8'))
root = args.evidence.resolve()
if not root.is_relative_to((app / 'evidence').resolve()):
    raise ValueError('Application evidence directory required')
if Path(args.snapshot_dir).name != args.snapshot_dir:
    raise ValueError('Snapshot directory must be a single evidence child')
snapshots = [json.loads((root / args.snapshot_dir / f'postgresql-after-{call}.json').read_text(encoding='utf-8'), parse_float=Decimal) for call in fixture['calls'].values()]
applications = {a['id']: a for s in snapshots for a in s['applications']}
assignments = {a['id']: a for s in snapshots for a in s['assignments']}
grades = [g for s in snapshots for g in s['grades']]
offers = {o['id']: o for s in snapshots for o in s['offers']}
publications = [p for s in snapshots for p in s['publications']]
q = lambda n: Decimal(str(n)).quantize(Decimal('.01'), rounding=ROUND_HALF_UP)
checked = []
for case in fixture['cases']:
    application = applications[case['application']]
    assigned_grades = [g for g in grades if g['application_id'] == application['id']]
    if case.get('disabled'):
        assert application['eligibility'] == 'disabled' and application['final'] is None and not assigned_grades
        continue
    offer = offers[fixture['offers'][case['offer']]['id']]
    scores = {'Redes': '0'} if case.get('pending') else case['scores']
    assert len(assigned_grades) == len(scores)
    for grade in assigned_grades:
        assignment = assignments[grade['assignment_id']]
        assert q(grade['score']) == q(scores[assignment['area']])
        assert grade['user_id'] == assignment['user_id']
        assert assignment['user_id'] == fixture['actors']['evaluator'+str(fixture['offers'][case['offer']]['assigned'][assignment['area']])]['id']
        assert grade['status'] == 'confirmed'
    exam = sum(Decimal(scores[area])*Decimal(str(weight))/100 for area, weight in offer['weights'].items())*Decimal('.6')
    merit = Decimal('40') if case['offer'] == 'software' and case['student'] == 'D' else Decimal(case['merit'])
    assert q(application['merit']) == q(merit)
    assert q(application['exam']) == q(exam)
    assert q(application['final']) == q(merit+exam)
    checked.append({'application': application['id'], 'student': case['student'], 'offer': case['offer'], 'merit': str(q(merit)), 'exam': str(q(exam)), 'final': str(q(merit+exam))})

expected = {'software': {'A', 'D'}, 'redes': {'A', 'F'}, 'electronica': {'H', 'A', 'I'}, 'exacto': {'J', 'K'}, 'empate_fuera': {'D'}, 'segunda': {'L'}, 'sin_habilitados': set(), 'pendiente': {'F'}}
seat_checks = []
for key, setup in fixture['offers'].items():
    selected = {c['student'] for c in fixture['cases'] if c['offer'] == key and applications[c['application']]['selection'] == 'selected'}
    assert selected == expected[key]
    assert len(selected) <= offers[setup['id']]['seats']
    versions = [p for p in publications if p['offer_id'] == setup['id']]
    assert len(versions) == (2 if key == 'software' else 1)
    seat_checks.append({'offer': key, 'seats': setup['seats'], 'selected': sorted(selected), 'vacant': setup['seats']-len(selected), 'publication_versions': len(versions)})

original = [p for call in fixture['calls'].values() for p in json.loads((root / 'results' / f'postgresql-after-{call}.json').read_text(encoding='utf-8'), parse_float=Decimal)['publications'] or []]
assert all(any(p == old for p in publications) for old in original)
for snapshot in snapshots:
    assert snapshot['call']['status'] == 'results'
    for tie in fixture['offers']:
        assert not offers[fixture['offers'][tie]['id']]['needs_publication_review']
receipt = {'result': 'PASS', 'decimal_results': checked, 'seats': seat_checks, 'grade_records_checked': len(grades), 'preserved_original_publications': len(original), 'current_publications': len(publications), 'input_snapshot_sha256': {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted((root / args.snapshot_dir).glob('postgresql-after-*.json'))}, 'limits': 'Synthetic DEMO with no assumed institutional pass threshold. The zero-point selection is a technical behavior, not institutional approval. Commission winners are explicit test decisions.'}
args.output.resolve().write_text(json.dumps(receipt, ensure_ascii=False, indent=2), encoding='utf-8')
print(json.dumps({'result': 'PASS', 'independent_results': len(checked), 'grades': len(grades), 'offers': len(seat_checks), 'original_publications_preserved': len(original)}))
