@extends('layouts.app')

@section('title', 'Editar proveedor')

@section('content')
    <div class="page-head">
        <h1>EDITAR PROVEEDOR</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('proveedores.index') }}">Proveedores</a>
            <span class="sep">/</span> Editar
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('proveedores.update', $proveedor) }}">
            @csrf @method('PUT')
            @include('proveedores._form')
        </form>
    </div>
@endsection
