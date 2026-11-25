<?php

namespace App\Http\Controllers\Api;

use App\Domain\Access;
use App\Domain\InstitutionalRegister;
use App\Domain\Selection;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class InstitutionalRegisterController extends Controller
{
    public function index(Request $r, int $id)
    {
        Access::require($r->user(), $id, ['authority']);
        $records = DB::table('institutional_register')->where('call_id', $id)->where('kind', '!=', 'cpd')->orderByDesc('id')->get();
        foreach ($records as $record) {
            $record->payload = Selection::json($record->payload);
        }
        $sources = DB::table('institutional_sources')->join('institutional_dossiers', 'institutional_dossiers.id', '=', 'institutional_sources.dossier_id')->where('institutional_dossiers.call_id', $id)->where(fn ($q) => $q->where('institutional_dossiers.created_by', $r->user()->id)->orWhere('institutional_dossiers.reviewer_id', $r->user()->id))->get(['institutional_sources.id', 'institutional_sources.title', 'institutional_sources.version']);

        return response()->json(['records' => $records, 'sources' => $sources, 'offers' => DB::table('offers')->where('call_id', $id)->orderBy('id')->get(['id', 'title'])]);
    }

    public function store(Request $r, int $id, InstitutionalRegister $register)
    {
        return response()->json(['id' => $register->record($r->user(), $id, $r->all())], 201);
    }

    public function export(Request $r, int $id)
    {
        Access::require($r->user(), $id, ['authority']);
        $records = DB::table('institutional_register')->where('call_id', $id)->where('kind', '!=', 'cpd')->orderBy('id')->get();
        return response()->streamDownload(function () use ($records) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Tipo', 'Referencia', 'Versión', 'Modalidad', 'Estado documental', 'Fecha del documento', 'Autoridad o unidad', 'Fuente', 'Integrantes o campos', 'Calendario y zona horaria', 'Oferta asociada', 'Fundamento', 'Registrado por', 'Fecha de registro', 'Alcance'], ';', '"', '');
            foreach ($records as $row) {
                $p = Selection::json($row->payload);
                $details = isset($p['members']) ? implode('; ', array_map(fn ($m) => $m['name'].' / '.$m['responsibility'], $p['members'])) : implode('; ', $p['fields'] ?? $p['criteria'] ?? []);
                $calendar = $row->kind === 'calendar' ? implode('; ', array_map(fn ($key, $label) => $label.': '.($p[$key] ?? 'No especificado'), array_keys(InstitutionalRegister::CALENDAR_FIELDS), InstitutionalRegister::CALENDAR_FIELDS)).'; '.($p['timezone'] ?? '') : '';
                $values = [InstitutionalRegister::KINDS[$row->kind] ?? $row->kind, $row->reference, $row->version, $p['modality'], InstitutionalRegister::STATES[$p['record_state'] ?? 'internal'], $p['issued_on'] ?? $p['starts_at'] ?? '', $p['authority'] ?? $p['issuer'] ?? '', $row->source_id ?? '', $details, $calendar, $p['offer_id'] ?? '', $p['notes'], $row->recorded_by, $row->created_at, 'Reporte interno; el registro no acredita aprobación institucional.'];
                $values = array_map(InstitutionalRegister::csvCell(...), $values);
                fputcsv($stream, $values, ';', '"', '');
            }
            fclose($stream);
        }, 'registro-interno-convocatoria-'.$id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
