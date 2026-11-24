@extends('layout')
@section('title',$register?'Crear cuenta':'Iniciar sesión')
@section('content')
<div class="auth-grid"><div class="auth-intro"><div class="eyebrow">FACULTAD DE INGENIERÍA EN CIENCIAS DE LA COMPUTACIÓN Y TELECOMUNICACIONES</div><h1>Un proceso claro.<br>Una oportunidad<br>para aportar.</h1><p>Postulaciones, expedientes y evaluaciones en un mismo lugar, con seguimiento y responsabilidad en cada etapa.</p><div class="intro-note">Convocatoria → Expediente → Evaluación → Resultados</div></div>
<section class="card auth-card"><div class="eyebrow">PORTAL DE AUXILIATURAS</div><h2>{{ $register?'Crea tu cuenta':'Bienvenido de nuevo' }}</h2><p class="muted">{{ $register?'El registro crea exclusivamente una cuenta de postulante.':'Ingresa con tu cuenta para continuar el proceso.' }}</p>
<form method="post" action="{{ $register?'/registro':'/login' }}">@csrf
@if($register)<label>Nombre completo<input name="name" required maxlength="100" autocomplete="name" value="{{ old('name') }}"></label>@endif
<label>Correo electrónico<input type="email" name="email" required autocomplete="username" value="{{ old('email') }}"></label>
<label>Contraseña<input type="password" name="password" required minlength="{{ $register?12:1 }}" autocomplete="{{ $register?'new-password':'current-password' }}"></label>
@if($register)<label>Confirmar contraseña<input type="password" name="password_confirmation" required autocomplete="new-password"></label><div class="form-grid"><label>Facultad<input name="faculty" required value="{{ old('faculty','FICCT') }}"></label><label>Carrera<input name="career" required value="{{ old('career','Sistemas') }}"></label></div><p class="muted">Usa al menos 12 caracteres. En esta DEMO utiliza datos ficticios.</p>@endif
<button class="primary wide">{{ $register?'Crear cuenta de postulante':'Iniciar sesión' }}</button></form><p class="auth-switch"><a href="{{ $register?'/login':'/registro' }}">{{ $register?'Ya tengo una cuenta':'Crear cuenta de postulante' }} →</a></p></section></div>
@endsection
