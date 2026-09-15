@extends('layouts.app')

@section('title', 'Editar usuario')

@section('content')
    <div class="page-head">
        <h1>EDITAR USUARIO</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('usuarios.index') }}">Usuarios</a>
            <span class="sep">/</span> Editar
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('usuarios.update', $usuario) }}">
            @csrf @method('PUT')
            @include('usuarios._form')
        </form>
    </div>
@endsection
