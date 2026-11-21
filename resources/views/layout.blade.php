<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title','Selección de auxiliares') · FICCT</title><link rel="stylesheet" href="/portal.css"></head>
<body>
<a class="skip" href="#contenido">Ir al contenido</a>
@auth
<aside class="sidebar"><a class="brand" href="/">FICCT<span>Selección de auxiliares</span></a><div class="side-section">ESPACIO DE TRABAJO</div><nav><a href="/" class="{{ request()->is('/')?'current':'' }}">Panel principal</a><a href="/#convocatorias">{{ auth()->user()->role==='student'?'Convocatorias para postular':'Mis convocatorias' }}</a>@if(auth()->user()->role==='student'||$hasOwnApplications)<a href="/#mis-postulaciones">{{ auth()->user()->role==='student'?'Mis postulaciones':'Mis expedientes anteriores' }}</a><a href="/adjudicaciones">Mis adjudicaciones</a>@elseif(in_array('authority',$workspaceRoles))<a href="/adjudicaciones">Adjudicaciones</a>@endif @if(count($workspaceRoles))<a href="/rectificaciones">Rectificaciones</a>@endif
@if(auth()->user()->role==='admin')<a href="/usuarios">Usuarios y permisos</a>@endif<a href="/ayuda">Qué puedo hacer</a></nav><div class="side-footer"><span class="demo-dot"></span> Entorno de demostración<br><small>Grupo #09 · Tecnología Web<br>Datos sintéticos · UAGRM</small></div></aside>
@endauth
<div class="shell {{ auth()->check()?'':'guest' }}">
<header class="topbar"><span>FICCT <span class="divider">/</span> {{ $label(auth()->user()?->role??'student') }}@auth @foreach($workspaceRoles as $workspaceRole) · {{ $label($workspaceRole) }}@endforeach @endauth</span><div class="account">@auth<span>{{ auth()->user()->name }}</span><form method="post" action="/salir">@csrf<button class="text-button">Cerrar sesión</button></form>@else<span class="badge">DEMO ACADÉMICA</span>@endauth</div></header>
<main id="contenido" tabindex="-1"><div class="mode-notice"><strong>DEMO</strong> Concurso ficticio. Las operaciones no emiten designaciones ni documentos oficiales.</div>
@if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>No se pudo completar la operación.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main><footer>FICCT / UAGRM · Proyecto académico Grupo #09 <span>Horario de referencia: America/La_Paz</span></footer></div>
</body></html>
