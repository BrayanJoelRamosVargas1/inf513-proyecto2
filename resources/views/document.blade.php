@extends('layout')
@section('title','Documento del expediente')
@section('content')
<a class="back" href="/postulaciones/{{ $document->application_id }}">← Volver al expediente</a>
<div class="page-heading compact"><div><h1>Documento del expediente</h1><p>{{ $document->name }} · Versión {{ $document->version }}</p></div></div>
<section class="card"><h2>Revisar antes de validar</h2><p>El archivo que se abre o descarga es el original cargado por el postulante. El sistema no lo convierte ni lo sustituye.</p>
<dl><dt>Formato</dt><dd>{{ ['application/pdf'=>'PDF','image/png'=>'Imagen PNG','image/jpeg'=>'Imagen JPG'][$mime] }}</dd><dt>Tamaño del archivo</dt><dd>{{ number_format($bytes/1024,1,',','.') }} KB</dd><dt>Carga</dt><dd>{{ $document->created_at }}</dd>@if($dimensions)<dt>Dimensiones</dt><dd>{{ $dimensions[0] }} × {{ $dimensions[1] }} píxeles</dd>@endif</dl>
@if($smallImage)<div class="alert warning"><strong>Imagen de tamaño reducido.</strong> Revise su legibilidad. Los expedientes antiguos de esta demostración pueden contener imágenes mínimas de prueba; no son documentos oficiales ni acreditan requisitos.</div>@endif
<div class="actions"><a class="button primary" href="/documentos/{{ $document->id }}/ver" target="_blank" rel="noopener">Abrir original en otra pestaña</a><a class="button secondary" href="/documentos/{{ $document->id }}">Descargar original</a></div>
<p class="muted">Integridad del archivo comprobada. Que un archivo esté presente no significa que cumpla el requisito: el revisor debe comprobar su contenido.</p></section>
@endsection
