@extends('layouts.app')

@section('title', 'Nuevo proveedor')

@section('content')
    <div class="page-head">
        <h1>NUEVO PROVEEDOR</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('proveedores.index') }}">Proveedores</a>
            <span class="sep">/</span> Nuevo
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('proveedores.store') }}">
            @csrf
            @include('proveedores._form')
        </form>
    </div>
@endsection
