@extends('layouts.app')

@section('title', 'Nuevo usuario')

@section('content')
    <div class="page-head">
        <h1>NUEVO USUARIO</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('usuarios.index') }}">Usuarios</a>
            <span class="sep">/</span> Nuevo
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('usuarios.store') }}">
            @csrf
            @include('usuarios._form')
        </form>
    </div>
@endsection
