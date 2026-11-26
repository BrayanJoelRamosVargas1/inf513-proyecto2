@extends('layout')
@section('title','Consolidado de convocatoria')
@section('content')
<a class="back" href="/convocatorias/{{ $call->id }}">← Convocatoria</a>
<div class="page-heading compact"><div><div class="eyebrow">RESULTADOS POR CONVOCATORIA · DEMO</div><h1>Consolidado de convocatoria</h1><p>{{ $call->title }} · Consulta {{ $generated_at->format('d/m/Y H:i:s') }} · America/La_Paz</p></div></div>
<div class="alert warning">Consulta técnica DEMO. La posición por puntaje conserva la misma posición para notas iguales; no resuelve empates. El estado actual y la última publicación se muestran por separado. No existe un umbral aprobatorio institucional confirmado.</div>
<section class="card"><h2>Resumen</h2><p>{{ $totals['offers'] }} ofertas · {{ $totals['applications'] }} postulaciones · {{ $totals['seats'] }} plazas · {{ $totals['enabled'] }} habilitadas activas · {{ $totals['disabled'] }} inhabilitadas · {{ $totals['held'] }} plazas retenidas por renuncia.</p><div class="actions"><a class="button secondary" href="/convocatorias/{{ $call->id }}/consolidado/pdf">Descargar consolidado PDF</a><a class="button secondary" href="/convocatorias/{{ $call->id }}/consolidado/xlsx">Descargar consolidado Excel</a></div><p class="muted">Los reportes incluyen resultados, áreas, documentación requerida y plazas. No incluyen archivos privados, CI, correos ni motivos sensibles. Las notas aún no confirmadas aparecen como pendientes.</p></section>
@forelse($groups as $g)
<section class="card"><h2>{{ $g['offer']->title }}</h2><p>{{ $g['offer']->seats }} plazas · {{ $g['offer']->held_vacancies }} retenidas por renuncia.
@if($g['publication']) Última publicación v{{ $g['publication']->version }}: {{ $g['covered'] }} adjudicaciones activas · {{ $g['vacant'] }} plazas sin cubrir.
@else Sin publicación: cobertura pendiente de aprobación.
@endif</p>
@if($g['offer']->needs_publication_review)<div class="alert warning">Resultados actuales en revisión: requieren nueva publicación. La publicación anterior se conserva.</div>@endif
@if($g['warning'])<p class="alert warning">{{ $g['warning'] }}</p>@endif
<div class="table-wrap"><table><thead><tr><th scope="col">Postulación</th><th scope="col">Postulante</th><th scope="col">Habilitación</th><th scope="col">Méritos /40</th><th scope="col">Examen /60</th><th scope="col">Final /100</th><th scope="col">Posición por puntaje</th><th scope="col">Estado actual</th><th scope="col">Última publicación</th></tr></thead><tbody>
@forelse($g['rows'] as $r)<tr><td><a href="/postulaciones/{{ $r['id'] }}">#{{ $r['id'] }}</a></td><td>{{ $r['name'] }}</td><td>{{ $label($r['eligibility']) }} · {{ $label($r['participation']) }}</td><td>{{ $r['merit']??'Pendiente' }}</td><td>{{ $r['exam']??'Pendiente' }}</td><td>{{ $r['final']??'Pendiente' }}</td><td>{{ $r['position']??'Pendiente' }}@if($r['tie_pending']) · Nuevo examen pendiente @endif</td><td>{{ $label($r['selection']) }}</td><td>
@if(!$g['publication'])
Sin publicación
@elseif($r['award_active'])
Adjudicación activa v{{ $g['publication']->version }}
@elseif($r['published_selected'])
Figuró en v{{ $g['publication']->version }}; participación {{ $label($r['participation']) }}
@else
No seleccionado en v{{ $g['publication']->version }}
@endif
</td></tr>
@empty<tr><td colspan="9">No hay postulaciones en esta oferta.</td></tr>@endforelse
</tbody></table></div>
@foreach($g['rows'] as $r)<details class="separated"><summary>Áreas y documentación · Postulación #{{ $r['id'] }} · {{ $r['name'] }}</summary><div class="table-wrap"><table><thead><tr><th scope="col">Área</th><th scope="col">Peso</th><th scope="col">Nota confirmada /100</th><th scope="col">Estado</th></tr></thead><tbody>@foreach($r['areas'] as $a)<tr><td>{{ $a['area'] }}</td><td>{{ $a['weight'] }} %</td><td>{{ $a['score']??'Pendiente' }}</td><td>{{ $label($a['status']) }}</td></tr>@endforeach</tbody></table></div><div class="table-wrap"><table><thead><tr><th scope="col">Documento requerido</th><th scope="col">Presente en expediente</th><th scope="col">Versión</th><th scope="col">Revisión</th></tr></thead><tbody>@foreach($r['documents'] as $d)<tr><td>{{ $d['title'] }}</td><td>{{ $d['present']?'Sí':'No' }}</td><td>{{ $d['version']??'Sin documento' }}</td><td>{{ $label($d['review']) }}</td></tr>@endforeach</tbody></table></div></details>@endforeach
<p><a href="/ofertas/{{ $g['offer']->id }}/resultados">Ver detalle de selección y publicaciones de esta oferta →</a></p></section>
@empty<section class="card"><p>No hay ofertas en esta convocatoria.</p></section>@endforelse
@endsection
