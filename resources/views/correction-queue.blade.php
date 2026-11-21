@extends('layout')
@section('title','Solicitudes de rectificación')
@section('content')
<div class="page-heading compact"><div><div class="eyebrow">PROCEDIMIENTO ADMINISTRATIVO DEMO</div><h1>Solicitudes de rectificación</h1><p>{{ $requests->where('status','pending')->count() }} pendientes en tus ámbitos vigentes.</p></div></div>
<section class="card"><h2>Solicitudes y decisiones</h2><div class="table-wrap"><table><thead><tr><th>Solicitud</th><th>Convocatoria / Oferta</th><th>Tipo</th><th>Anterior → Propuesto</th><th>Solicitante</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>@forelse($requests as $item)<tr><td>#{{ $item->id }}</td><td>#{{ $item->call_id }} / {{ $item->title }}</td><td>{{ $item->kind==='merit'?'Méritos':'Examen' }}</td><td>{{ $item->old_value }} → {{ $item->new_value }}</td><td>#{{ $item->requester_id }}</td><td>{{ $label($item->status) }}</td><td><a href="/postulaciones/{{ $item->application_id }}/rectificaciones">Evidencia, decisión y versiones →</a></td></tr>@empty<tr><td colspan="7">No hay solicitudes visibles en tus convocatorias.</td></tr>@endforelse</tbody></table></div></section>
@endsection
