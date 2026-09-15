@extends('layouts.app')

@section('title', $titulo)

@section('content')
    <div class="page-head">
        <h1>{{ strtoupper($titulo) }}</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> {{ $titulo }}
        </div>
    </div>

    <div class="placeholder-box">
        <div class="big"><i class="fa-solid fa-screwdriver-wrench"></i></div>
        <h2>{{ $titulo }}</h2>
        <p>{{ $descripcion ?? 'Este módulo estará disponible próximamente.' }}</p>
        <span class="tag"><i class="fa-solid fa-hammer"></i> Módulo en construcción</span>
    </div>
@endsection
