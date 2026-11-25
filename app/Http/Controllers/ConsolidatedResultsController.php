<?php

namespace App\Http\Controllers;

use App\Domain\Access;
use App\Domain\Selection;
use App\ReadModels\CallConsolidation;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ConsolidatedResultsController extends Controller
{
    public function show(Request $request, int $id, CallConsolidation $read)
    {
        return view('consolidated-results', $read->read($request->user(), $id));
    }

    public function export(Request $request, int $id, string $format, CallConsolidation $read)
    {
        $data = $read->read($request->user(), $id);
        $tables = $this->tables($data);
        $filePrefix = $data['call']->mode === 'HISTORICAL' ? 'consolidado-referencia-' : 'consolidado-demo-';
        Access::transaction($request->user(), function () use ($request, $id, $format, $data) {
            Access::require($request->user(), $id, ['authority']);
            Selection::audit($request->user(), $id, 'consolidation.exported', 'call:'.$id, null,
                ['format' => $format, 'applications' => $data['totals']['applications'], 'generated_at' => $data['generated_at']->toIso8601String()]);
        });
        if ($format === 'pdf') {
            $pdf = new Dompdf(['isRemoteEnabled' => false]);
            $pdf->loadHtml(view('consolidated-report', $data + ['tables' => $tables])->render());
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();

            return response($pdf->output(), 200, ['Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filePrefix.$id.'.pdf"']);
        }
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach ($tables as $title => $table) {
            $sheet = new Worksheet($book, $title);
            $book->addSheet($sheet);
            $sheet->setCellValueExplicit('A1', ($data['call']->mode === 'HISTORICAL' ? 'Reporte interno de referencia histórica' : 'DEMO · Sin validez institucional').' · '.$data['call']->title, DataType::TYPE_STRING);
            $sheet->fromArray($table['headers'], null, 'A3');
            foreach ($table['rows'] as $i => $row) {
                foreach ($row as $j => $value) {
                    $sheet->setCellValueExplicit([$j + 1, $i + 4], (string) ($value ?? 'Pendiente'), DataType::TYPE_STRING);
                }
            }
            $end = Coordinate::stringFromColumnIndex(count($table['headers']));
            $sheet->getStyle('A3:'.$end.'3')->getFont()->setBold(true);
            $sheet->setAutoFilter('A3:'.$end.max(3, count($table['rows']) + 3));
            $sheet->freezePane('A4');
            foreach ($sheet->getColumnIterator('A', $end) as $column) {
                $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }
        }
        $book->setActiveSheetIndex(0);

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $filePrefix.$id.'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function tables(array $data): array
    {
        $label = app('view')->shared('label');
        $tables = [
            'Resultados' => ['headers' => ['Postulación', 'Postulante', 'Oferta', 'Habilitación', 'Participación', 'Mérito /40', 'Examen /60', 'Final /100', 'Posición por puntaje', 'Estado actual', 'Última publicación', 'Adjudicación activa'], 'rows' => []],
            'Áreas' => ['headers' => ['Postulación', 'Oferta', 'Área', 'Peso %', 'Nota /100', 'Estado'], 'rows' => []],
            'Documentación' => ['headers' => ['Postulación', 'Oferta', 'Documento requerido', 'Presente en expediente', 'Versión', 'Revisión'], 'rows' => []],
            'Plazas' => ['headers' => ['Oferta', 'Cupos', 'Última publicación', 'Adjudicaciones activas', 'Sin cubrir', 'Retenidas por renuncia', 'Nueva publicación pendiente'], 'rows' => []],
        ];
        foreach ($data['groups'] as $g) {
            $o = $g['offer'];
            $publication = $g['publication'] ? 'v'.$g['publication']->version : 'Sin publicación';
            $tables['Plazas']['rows'][] = [$o->title, $o->seats ?? 'No especificadas', $publication, $g['covered'], $g['vacant'], $o->held_vacancies, $o->needs_publication_review ? 'Sí' : 'No'];
            foreach ($g['rows'] as $r) {
                $tables['Resultados']['rows'][] = [$r['id'], $r['name'], $o->title, $label($r['eligibility']), $label($r['participation']), $r['merit'], $r['exam'], $r['final'], $r['position'], $label($r['selection']), $publication, $r['award_active'] ? 'Sí' : 'No'];
                foreach ($r['areas'] as $a) {
                    $tables['Áreas']['rows'][] = [$r['id'], $o->title, $a['area'], $a['weight'], $a['score'], $label($a['status'])];
                }
                foreach ($r['documents'] as $d) {
                    $tables['Documentación']['rows'][] = [$r['id'], $o->title, $d['title'], $d['present'] ? 'Sí' : 'No', $d['version'], $label($d['review'])];
                }
            }
        }

        if ($data['institutional_records']->isNotEmpty()) {
            $tables['Registro institucional'] = ['headers' => ['Tipo', 'Referencia', 'Versión', 'Modalidad', 'Estado documental', 'Fecha', 'Emisor', 'Respaldo', 'Fundamento'], 'rows' => []];
            foreach ($data['institutional_records'] as $record) {
                $p = \App\Domain\Selection::json($record->payload);
                $tables['Registro institucional']['rows'][] = [\App\Domain\InstitutionalRegister::KINDS[$record->kind] ?? $record->kind, $record->reference, $record->version, $p['modality'], \App\Domain\InstitutionalRegister::STATES[$p['record_state'] ?? 'internal'], $p['issued_on'] ?? $p['starts_at'] ?? null, $p['authority'] ?? $p['issuer'] ?? null, $record->source_id, $p['notes']];
            }
        }

        foreach ($data['institutional_records']->where('kind', 'calendar') as $record) {
            $p = \App\Domain\Selection::json($record->payload);
            $tables['Calendarios registrados'] ??= ['headers' => ['Referencia', 'Versión', 'Modalidad', 'Fecha documentada', 'Valor', 'Zona horaria'], 'rows' => []];
            foreach (\App\Domain\InstitutionalRegister::CALENDAR_FIELDS as $key => $title) {
                $tables['Calendarios registrados']['rows'][] = [$record->reference, $record->version, $p['modality'], $title, $p[$key] ?? 'No especificado', $p['timezone']];
            }
        }
        if ($data['documentary']) {
            $tables['Calendario documental'] = ['headers' => ['Modalidad', 'Dato de la fuente', 'Valor original', 'Observación'], 'rows' => []];
            foreach ($data['documentary']['calendars'] as $mode => $calendar) {
                foreach ($calendar as $key => $value) {
                    if ($key !== 'conflict') {
                        $tables['Calendario documental']['rows'][] = [$mode, $key, $value ?? 'No especificado', $calendar['conflict'] ?? 'Referencia documental histórica'];
                    }
                }
            }
        }
        return $tables;
    }
}
