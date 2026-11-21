@extends('layout')
@section('title','Auditoría')
@section('content')
<a class="back" href="/convocatorias/{{ $call->id }}">← {{ $call->title }}</a><div class="page-heading compact"><div><div class="eyebrow">CU7 / TRANSPARENCIA</div><h1>Historial del proceso</h1><p>Eventos de aplicación con responsable, antes y después. No se eliminan desde el portal.</p></div></div><section class="card"><div class="table-wrap"><table><thead><tr><th>Evento</th><th>Fecha local</th><th>Actor</th><th>Operación</th><th>Evidencia</th></tr></thead><tbody>@foreach($events as $event)<tr><td>#{{ $event->id }}</td><td>{{ $event->created_at }}</td><td>#{{ $event->user_id }}</td><td>{{ $event->action }}<small>{{ $event->entity }}</small></td><td><details><summary>Ver detalle</summary><p>{{ $event->reason }}</p><pre>Antes: {{ $event->before??'Sin valor previo' }}
Después: {{ $event->after??'Sin datos adicionales' }}</pre></details></td></tr>@endforeach</tbody></table></div>{{ $events->links('pagination::simple-default') }}</section>
@endsection
